<?php
namespace System\Cache;

/**
 * Application cache (files in storage/cache/data, no extra services needed).
 *
 *   $stats = Cache::remember('dashboard.stats', 300, fn() => expensiveQuery());
 *   Cache::put('key', $value, 60);  Cache::get('key', 'default');  Cache::forget('key');
 *
 * @method static mixed get(string $key, $default = null)
 * @method static bool has(string $key)
 * @method static bool put(string $key, $value, ?int $seconds = null)
 * @method static bool forget(string $key)
 * @method static mixed remember(string $key, ?int $seconds, callable $callback)
 * @method static int increment(string $key, int $by = 1, ?int $seconds = null)
 * @method static int|null ttl(string $key)
 * @method static int flush(bool $expiredOnly = false)
 */
class Cache
{
    protected static ?FileStore $store = null;

    public static function store(): FileStore
    {
        return self::$store ??= new FileStore((string) config('cache.path', base_path('storage/cache/data')));
    }

    public static function setStore(?FileStore $store): void
    {
        self::$store = $store;
    }

    public static function forever(string $key, $value): bool
    {
        return self::store()->put($key, $value, null);
    }

    public static function __callStatic(string $method, array $arguments)
    {
        return self::store()->$method(...$arguments);
    }
}
