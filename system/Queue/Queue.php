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

    /** Push a job; returns its id (database driver) or null (sync, run immediately) */
    public static function push(Job $job, int $delaySeconds = 0, ?string $queue = null): ?int
    {
        $queue ??= $job->queue;

        if (self::driver() === 'sync') {
            self::runNow($job);
            return null;
        }

        $now = time();
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

    public static function later(int $delaySeconds, Job $job): ?int
    {
        return self::push($job, $delaySeconds);
    }

    /** Run a job in this process, with retries handled inline (sync driver) */
    protected static function runNow(Job $job): void
    {
        for ($attempt = 1; ; $attempt++) {
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
