<?php

namespace System\Cache;

/**
 * Counts hits per key in a time window (fixed window, stored in the cache).
 *
 *   $limiter = new RateLimiter();
 *   if ($limiter->tooManyAttempts('send-mail:' . $userId, 3)) { ... }
 *   $limiter->hit('send-mail:' . $userId, 60);
 */
class RateLimiter
{
    public function __construct(protected ?Store $store = null)
    {
        $this->store ??= Cache::store();
    }

    /** Count one hit; returns hits in the current window */
    public function hit(string $key, int $decaySeconds = 60): int
    {
        return $this->store->increment('rate:' . $key, 1, $decaySeconds);
    }

    public function attempts(string $key): int
    {
        return (int) $this->store->get('rate:' . $key, 0);
    }

    public function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->attempts($key) >= $maxAttempts;
    }

    public function remaining(string $key, int $maxAttempts): int
    {
        return max(0, $maxAttempts - $this->attempts($key));
    }

    /** Seconds until the window resets */
    public function availableIn(string $key): int
    {
        return (int) ($this->store->ttl('rate:' . $key) ?? 0);
    }

    public function clear(string $key): void
    {
        $this->store->forget('rate:' . $key);
    }
}
