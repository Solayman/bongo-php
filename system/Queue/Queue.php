<?php

namespace System\Queue;

use System\Container\Container;
use System\Database\DB;
use System\Log\Log;

/**
 * Queue::push($job) runs a Job now (driver "sync", the default) or stores it in the
 * jobs table (driver "database") for `php bongo queue:work`. Settings: config/queue.php.
 */
class Queue
{
    public static function driver(): string
    {
        return (string) config('queue.driver', 'sync');
    }

    /** Push a job; returns its id or null (sync, run immediately) */
    public static function push(Job $job, int $delaySeconds = 0, ?string $queue = null): string|int|null
    {
        $queue ??= $job->queue;

        if (self::driver() === 'sync') {
            self::runNow($job);
            return null;
        }

        $now = time();

        if (self::driver() === 'redis') {
            $redis = \System\Redis\Redis::connection();
            $prefix = \System\Redis\Redis::prefix();
            $queueKey = $prefix . 'queue:' . $queue;
            $id = bin2hex(random_bytes(8));
            $payload = self::signRedisEntry([
                'id'           => $id,
                'queue'        => $queue,
                'job'          => base64_encode(serialize($job)),
                'attempts'     => 0,
                'available_at' => $now + max(0, $delaySeconds),
                'created_at'   => $now,
            ]);

            if ($delaySeconds > 0) {
                $redis->zAdd($queueKey . ':delayed', $now + $delaySeconds, $payload);
            } else {
                $redis->rPush($queueKey, $payload);
            }

            Log::debug('Queued {job} (#{id}) on Redis queue {queue}', ['job' => $job->displayName(), 'id' => $id, 'queue' => $queue]);
            return $id;
        }

        $id = DB::table('jobs')->insertGetId([
            'queue'        => $queue,
            'payload'      => base64_encode(serialize($job)),
            'attempts'     => 0,
            'available_at' => $now + max(0, $delaySeconds),
            'created_at'   => $now,
        ]);
        Log::debug('Queued {job} (#{id}) on {queue}', ['job' => $job->displayName(), 'id' => $id, 'queue' => $queue]);
        return $id;
    }

    /**
     * Redis entries are signed with APP_KEY: a worker only unserializes jobs this app queued,
     * never objects someone else wrote into a shared or unprotected Redis.
     */
    public static function signRedisEntry(array $data): string
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES);
        return json_encode(['body' => $body, 'sig' => hash_hmac('sha256', $body, \System\Security\Key::derive('queue'))]);
    }

    /** The job data of a signed entry, or null when it is malformed or the signature doesn't match */
    public static function verifyRedisEntry(string $entry): ?array
    {
        $outer = json_decode($entry, true);
        if (!is_array($outer) || !is_string($outer['body'] ?? null) || !is_string($outer['sig'] ?? null)) {
            return null;
        }
        if (!hash_equals(hash_hmac('sha256', $outer['body'], \System\Security\Key::derive('queue')), $outer['sig'])) {
            return null;
        }
        $data = json_decode($outer['body'], true);
        return is_array($data) && !empty($data['job']) ? $data : null;
    }

    public static function later(int $delaySeconds, Job $job): string|int|null
    {
        return self::push($job, $delaySeconds);
    }

    /** Run a job in this process, with retries handled inline (sync driver) */
    protected static function runNow(Job $job): void
    {
        for ($attempt = 1;; $attempt++) {
            $job->attempt = $attempt;
            try {
                Container::getInstance()->call([$job, 'handle']);
                return;
            } catch (\Throwable $e) {
                if ($attempt >= max(1, $job->tries)) {
                    $job->failed($e);
                    Log::error('Job {job} failed: ' . $e->getMessage(), ['job' => $job->displayName(), 'exception' => $e]);
                    throw $e;
                }
            }
        }
    }

    /** Numbers for status pages: pending, delayed, reserved, failed */
    public static function stats(?string $queue = null): array
    {
        try {
            $now = time();

            if (self::driver() === 'redis') {
                $redis = \System\Redis\Redis::connection();
                $prefix = \System\Redis\Redis::prefix();
                $q = $queue ?? 'default';
                $queueKey = $prefix . 'queue:' . $q;
                return [
                    'pending'  => (int) $redis->lLen($queueKey) + (int) $redis->zCount($queueKey . ':delayed', '-inf', (string) $now),
                    'delayed'  => (int) $redis->zCount($queueKey . ':delayed', (string) ($now + 1), '+inf'),
                    'reserved' => (int) $redis->zCard($queueKey . ':reserved'),
                    'failed'   => (int) DB::table('failed_jobs')->where('queue', $q)->count(),
                ];
            }

            $base = fn() => $queue ? DB::table('jobs')->where('queue', $queue) : DB::table('jobs');
            return [
                'pending'  => $base()->whereNull('reserved_at')->where('available_at', '<=', $now)->count(),
                'delayed'  => $base()->whereNull('reserved_at')->where('available_at', '>', $now)->count(),
                'reserved' => $base()->whereNotNull('reserved_at')->count(),
                'failed'   => ($queue ? DB::table('failed_jobs')->where('queue', $queue) : DB::table('failed_jobs'))->count(),
            ];
        } catch (\Throwable $e) {
            return ['pending' => 0, 'delayed' => 0, 'reserved' => 0, 'failed' => 0, 'error' => $e->getMessage()];
        }
    }
}
