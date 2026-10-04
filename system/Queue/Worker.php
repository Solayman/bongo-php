<?php

namespace System\Queue;

use System\Container\Container;
use System\Database\DB;
use System\Log\Log;

/**
 * Takes jobs from the queue and runs them (`php bongo queue:work`).
 *
 * database: a job is reserved inside a transaction with a row lock.
 * redis: a job is reserved by one atomic Lua script and kept in a :reserved set.
 * Either way two workers never run the same job, and a job reserved longer than
 * queue.retry_after seconds (its worker crashed) becomes available again.
 */
class Worker
{
    protected bool $stop = false;

    public function __construct(protected ?\Closure $output = null)
    {
    }

    /**
     * @param array $options queue, sleep, max_jobs, max_time, stop_when_empty, once
     * @return int number of jobs processed
     */
    public function work(array $options = []): int
    {
        $queue = (string) ($options['queue'] ?? 'default');
        $sleep = max(0, (int) ($options['sleep'] ?? 3));
        $maxJobs = (int) ($options['max_jobs'] ?? 0);
        $maxTime = (int) ($options['max_time'] ?? 0);
        $started = time();
        $processed = 0;

        if (function_exists('pcntl_signal')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->stop = true);
            pcntl_signal(SIGINT, fn() => $this->stop = true);
        }

        while (!$this->stop) {
            $row = $this->reserve($queue);

            if ($row === null) {
                if (!empty($options['stop_when_empty']) || !empty($options['once'])) {
                    break;
                }
                sleep($sleep);
            } else {
                $this->process($row);
                $processed++;
                if (!empty($options['once'])) {
                    break;
                }
            }

            if (($maxJobs && $processed >= $maxJobs) || ($maxTime && time() - $started >= $maxTime)) {
                break;
            }
        }

        return $processed;
    }

    /** Claim the next available job, or null */
    public function reserve(string $queue): ?object
    {
        if (Queue::driver() === 'redis') {
            return $this->reserveRedis($queue);
        }

        $now = time();
        $retryAfter = (int) config('queue.retry_after', 90);

        return DB::transaction(function () use ($queue, $now, $retryAfter) {
            $row = DB::table('jobs')
                ->where('queue', $queue)
                ->where('available_at', '<=', $now)
                ->where(fn($q) => $q->whereNull('reserved_at')->orWhere('reserved_at', '<', $now - $retryAfter))
                ->orderBy('id')
                ->limit(1)
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                return null;
            }
            DB::table('jobs')->where('id', $row->id)->update(['reserved_at' => $now, 'attempts' => $row->attempts + 1]);
            $row->attempts++;
            return $row;
        });
    }

    /**
     * One atomic step in Redis (a Lua script), so two workers never get the same job:
     *   1. jobs whose worker died (reserved longer than retry_after) go back to the queue
     *   2. delayed jobs that are due join the queue
     *   3. the next job is taken and recorded in :reserved until it finishes
     * A crash mid-job therefore never loses the job: step 1 brings it back.
     */
    protected const REDIS_RESERVE = <<<'LUA'
        local expired = redis.call('ZRANGEBYSCORE', KEYS[3], '-inf', ARGV[1])
        for _, item in ipairs(expired) do
            redis.call('ZREM', KEYS[3], item)
            redis.call('RPUSH', KEYS[1], item)
        end
        local due = redis.call('ZRANGEBYSCORE', KEYS[2], '-inf', ARGV[1])
        for _, item in ipairs(due) do
            redis.call('ZREM', KEYS[2], item)
            redis.call('RPUSH', KEYS[1], item)
        end
        local job = redis.call('LPOP', KEYS[1])
        if job then
            redis.call('ZADD', KEYS[3], ARGV[2], job)
        end
        return job
        LUA;

    protected function reserveRedis(string $queue): ?object
    {
        $redis = \System\Redis\Redis::connection();
        $queueKey = \System\Redis\Redis::prefix() . 'queue:' . $queue;
        $now = time();
        $retryAfter = (int) config('queue.retry_after', 90);

        $entry = $redis->eval(self::REDIS_RESERVE, [$queueKey, "$queueKey:delayed", "$queueKey:reserved", $now, $now + $retryAfter], 3);
        if (!is_string($entry) || $entry === '') {
            return null;
        }

        $data = Queue::verifyRedisEntry($entry);
        if ($data === null) {
            // Not written by this app (or tampered with): never unserialize it
            $redis->zRem("$queueKey:reserved", $entry);
            Log::warning('Dropped a Redis queue entry with a missing or invalid signature', ['queue' => $queue]);
            return null;
        }

        return (object) [
            'id'           => $data['id'] ?? bin2hex(random_bytes(8)),
            'queue'        => $data['queue'] ?? $queue,
            'payload'      => $data['job'],
            'attempts'     => ((int) ($data['attempts'] ?? 0)) + 1,
            'available_at' => (int) ($data['available_at'] ?? $now),
            'created_at'   => (int) ($data['created_at'] ?? $now),
            '_driver'      => 'redis',
            '_raw'         => $data,
            '_entry'       => $entry,
        ];
    }

    /** The job is finished (done, failed or rescheduled): it is no longer reserved */
    protected function releaseRedis(object $row): void
    {
        \System\Redis\Redis::connection()->zRem(\System\Redis\Redis::prefix() . 'queue:' . $row->queue . ':reserved', $row->_entry);
    }

    public function process(object $row): void
    {
        $job = @unserialize(base64_decode((string) $row->payload));
        if (!$job instanceof Job) {
            $this->fail($row, null, new \RuntimeException('Job payload could not be restored'));
            return;
        }
        $job->attempt = (int) $row->attempts;

        $this->say("Processing {$job->displayName()} #{$row->id} (attempt {$job->attempt})");
        $start = microtime(true);

        try {
            Container::getInstance()->call([$job, 'handle']);
            if (($row->_driver ?? null) === 'redis') {
                $this->releaseRedis($row);
            } else {
                DB::table('jobs')->where('id', $row->id)->delete();
            }
            $this->say(sprintf('  done in %d ms', (microtime(true) - $start) * 1000));
            Log::info('Job {job} #{id} done', ['job' => $job->displayName(), 'id' => $row->id]);
        } catch (\Throwable $e) {
            if ($job->attempt >= max(1, $job->tries)) {
                $this->fail($row, $job, $e);
                return;
            }
            $delay = $job->backoffFor($job->attempt);
            if (($row->_driver ?? null) === 'redis') {
                $raw = $row->_raw ?? [];
                $raw['attempts'] = $job->attempt;
                $raw['available_at'] = time() + $delay;
                $redis = \System\Redis\Redis::connection();
                $prefix = \System\Redis\Redis::prefix();
                $redis->zAdd($prefix . 'queue:' . $row->queue . ':delayed', time() + $delay, Queue::signRedisEntry($raw));
                $this->releaseRedis($row);
            } else {
                DB::table('jobs')->where('id', $row->id)->update(['reserved_at' => null, 'available_at' => time() + $delay]);
            }
            $this->say("  failed: {$e->getMessage()} (retry in {$delay}s)");
            Log::warning('Job {job} #{id} failed, retry {attempt}/{tries} in {delay}s: ' . $e->getMessage(), [
                'job' => $job->displayName(), 'id' => $row->id, 'attempt' => $job->attempt, 'tries' => $job->tries, 'delay' => $delay,
            ]);
        }
    }

    protected function fail(object $row, ?Job $job, \Throwable $e): void
    {
        DB::transaction(function () use ($row, $e) {
            DB::table('failed_jobs')->insert([
                'queue' => $row->queue, 'payload' => $row->payload,
                'exception' => get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getTraceAsString(),
            ]);
            if (($row->_driver ?? null) !== 'redis') {
                DB::table('jobs')->where('id', $row->id)->delete();
            }
        });
        if (($row->_driver ?? null) === 'redis') {
            $this->releaseRedis($row);
        }
        try {
            $job?->failed($e);
        } catch (\Throwable $ignored) {
        }
        $this->say('  FAILED permanently: ' . $e->getMessage());
        Log::error('Job {job} #{id} failed permanently: ' . $e->getMessage(), ['job' => $job ? $job->displayName() : '?', 'id' => $row->id, 'exception' => $e]);
    }

    /** Move failed jobs back onto the queue; returns how many */
    public static function retry(string|int $id = 'all'): int
    {
        $query = DB::table('failed_jobs');
        if ($id !== 'all') {
            $query->where('id', (int) $id);
        }
        $count = 0;
        $isRedis = Queue::driver() === 'redis';
        $redis = $isRedis ? \System\Redis\Redis::connection() : null;
        $prefix = $isRedis ? \System\Redis\Redis::prefix() : '';

        foreach ($query->get() as $failed) {
            if ($isRedis) {
                $payload = Queue::signRedisEntry([
                    'id'           => bin2hex(random_bytes(8)),
                    'queue'        => $failed->queue,
                    'job'          => $failed->payload,
                    'attempts'     => 0,
                    'available_at' => time(),
                    'created_at'   => time(),
                ]);
                $redis->rPush($prefix . 'queue:' . $failed->queue, $payload);
            } else {
                DB::table('jobs')->insert(['queue' => $failed->queue, 'payload' => $failed->payload, 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
            }
            DB::table('failed_jobs')->where('id', $failed->id)->delete();
            $count++;
        }
        return $count;
    }

    protected function say(string $line): void
    {
        if ($this->output) {
            ($this->output)($line);
        }
    }
}
