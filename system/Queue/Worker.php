<?php
namespace System\Queue;

use System\Container\Container;
use System\Database\DB;
use System\Log\Log;

/**
 * Takes jobs from the jobs table and runs them (`php bongo queue:work`).
 *
 * A job is reserved inside a transaction with a row lock, so two workers never
 * run the same job. A job reserved longer than queue.retry_after seconds (the
 * worker crashed) becomes available again.
 */
class Worker
{
    protected bool $stop = false;

    public function __construct(protected ?\Closure $output = null) {}

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
                if (!empty($options['once'])) break;
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
            DB::table('jobs')->where('id', $row->id)->delete();
            $this->say(sprintf('  done in %d ms', (microtime(true) - $start) * 1000));
            Log::info('Job {job} #{id} done', ['job' => $job->displayName(), 'id' => $row->id]);
        } catch (\Throwable $e) {
            if ($job->attempt >= max(1, $job->tries)) {
                $this->fail($row, $job, $e);
                return;
            }
            $delay = $job->backoffFor($job->attempt);
            DB::table('jobs')->where('id', $row->id)->update(['reserved_at' => null, 'available_at' => time() + $delay]);
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
            DB::table('jobs')->where('id', $row->id)->delete();
        });
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
        foreach ($query->get() as $failed) {
            DB::table('jobs')->insert(['queue' => $failed->queue, 'payload' => $failed->payload, 'attempts' => 0, 'available_at' => time(), 'created_at' => time()]);
            DB::table('failed_jobs')->where('id', $failed->id)->delete();
            $count++;
        }
        return $count;
    }

    protected function say(string $line): void
    {
        if ($this->output) ($this->output)($line);
    }
}
