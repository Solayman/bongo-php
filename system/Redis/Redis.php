<?php

namespace System\Redis;

use RuntimeException;

/**
 * The shared Redis connection (PHP's "redis" extension), configured in
 * config/database.php under 'redis'. Used by CACHE_DRIVER=redis and SESSION_DRIVER=redis.
 *
 *   Redis::connection()->set('key', 'value');
 */
class Redis
{
    protected static ?\Redis $client = null;

    public static function connection(): \Redis
    {
        if (self::$client !== null) {
            return self::$client;
        }
        if (!extension_loaded('redis')) {
            throw new RuntimeException('Redis needs the "redis" PHP extension. Add "extension=redis" to php.ini, or set CACHE_DRIVER=file and SESSION_DRIVER=file.');
        }

        $config = (array) config('database.redis', []);
        $host = (string) ($config['host'] ?? '127.0.0.1');
        $port = (int) ($config['port'] ?? 6379);
        $client = new \Redis();
        try {
            $client->connect($host, $port, (float) ($config['timeout'] ?? 2.0));
            if (($config['password'] ?? null) !== null && $config['password'] !== '') {
                $client->auth($config['password']);
            }
            $client->select((int) ($config['database'] ?? 0));
        } catch (\RedisException $e) {
            throw new RuntimeException("Cannot connect to Redis at $host:$port: " . $e->getMessage(), 0, $e);
        }

        return self::$client = $client;
    }

    /** Every key the framework writes starts with this, so apps sharing a Redis server stay apart */
    public static function prefix(): string
    {
        return (string) config('database.redis.prefix', 'bongo:');
    }

    /** Forget the connection (after changing config) */
    public static function reset(): void
    {
        self::$client = null;
    }
}
