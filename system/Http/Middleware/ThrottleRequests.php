<?php
namespace System\Http\Middleware;

use System\Auth\Auth;
use System\Cache\RateLimiter;
use System\Http\HttpException;
use System\Http\Request;
use System\Log\Log;

/**
 * "throttle:60,1" → at most 60 requests per 1 minute, per user (or IP) per route.
 * Sends X-RateLimit-Limit / X-RateLimit-Remaining; over the limit → 429 with Retry-After.
 */
class ThrottleRequests
{
    public function handle(string $maxAttempts = '60', string $decayMinutes = '1'): void
    {
        $max = max(1, (int) $maxAttempts);
        $decay = max(1, (int) round((float) $decayMinutes * 60));
        $limiter = new RateLimiter();
        $key = $this->key();

        if ($limiter->tooManyAttempts($key, $max)) {
            $retry = max(1, $limiter->availableIn($key));
            if (!headers_sent()) {
                header('Retry-After: ' . $retry);
                header("X-RateLimit-Limit: $max");
                header('X-RateLimit-Remaining: 0');
            }
            Log::warning('Rate limit hit on {path}', ['path' => Request::path(), 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
            throw new HttpException(429);
        }

        $hits = $limiter->hit($key, $decay);
        if (!headers_sent()) {
            header("X-RateLimit-Limit: $max");
            header('X-RateLimit-Remaining: ' . max(0, $max - $hits));
        }
    }

    /** Per signed-in user when possible, otherwise per IP; separate counter per route path */
    protected function key(): string
    {
        $who = null;
        try {
            $who = Auth::id();
        } catch (\Throwable $e) {
            // no auth configured for this request
        }
        $who = $who !== null ? "user:$who" : 'ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        return 'throttle:' . sha1($who . '|' . Request::path());
    }
}
