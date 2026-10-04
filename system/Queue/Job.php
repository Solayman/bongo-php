<?php

namespace System\Queue;

/**
 * Base class for queued work.
 *
 *   class SendInvoice extends Job
 *   {
 *       public int $tries = 5;
 *       public function __construct(public int $orderId) {}
 *       public function handle(Mailer $mailer): void { ... }   // parameters are injected
 *   }
 *
 *   dispatch(new SendInvoice($order->id));            // or Queue::push($job, delaySeconds: 60)
 *
 * Keep constructor data small and serializable (ids, strings, arrays), not open connections.
 */
abstract class Job
{
    /** How many times to try before the job is moved to failed_jobs */
    public int $tries = 3;

    /** Seconds to wait before a retry (an int, or one value per retry: [10, 60, 300]) */
    public int|array $backoff = 10;

    /** Queue name, so you can run separate workers per queue */
    public string $queue = 'default';

    /** Set by the worker: the current attempt (1, 2, ...) */
    public int $attempt = 1;

    /** Called after the last attempt failed */
    public function failed(\Throwable $exception): void
    {
    }

    public function backoffFor(int $attempt): int
    {
        if (is_array($this->backoff)) {
            return (int) ($this->backoff[min($attempt, count($this->backoff)) - 1] ?? end($this->backoff));
        }
        return $this->backoff;
    }

    public function displayName(): string
    {
        return static::class;
    }
}
