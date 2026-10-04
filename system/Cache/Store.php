<?php
namespace System\Cache;

/**
 * What every cache store can do. FileStore (default) and RedisStore implement it,
 * so Cache::, the rate limiter and your code work the same with either one.
 */
interface Store
{
    public function get(string $key, $default = null);

    public function has(string $key): bool;

    /** @param int|null $seconds null = forever */
    public function put(string $key, $value, ?int $seconds = null): bool;

    public function forget(string $key): bool;

    public function remember(string $key, ?int $seconds, callable $callback);

    /** Atomically add $by (created with $seconds lifetime if missing); returns the new value */
    public function increment(string $key, int $by = 1, ?int $seconds = null): int;

    /** Seconds until $key expires (null = forever, 0 = missing) */
    public function ttl(string $key): ?int;

    /** Remove everything this store wrote (or only expired entries); returns how many */
    public function flush(bool $expiredOnly = false): int;
}
