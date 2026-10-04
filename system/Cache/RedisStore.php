<?php
namespace System\Cache;

use System\Redis\Redis;

/**
 * Cache in Redis (CACHE_DRIVER=redis). Shared by every server of the app,
 * and Redis removes expired keys by itself.
 */
class RedisStore implements Store
{
    public function __construct(protected ?\Redis $redis = null, protected ?string $prefix = null)
    {
        $this->prefix ??= Redis::prefix() . 'cache:';
    }

    protected function redis(): \Redis
    {
        return $this->redis ??= Redis::connection();
    }

    protected function key(string $key): string
    {
        return $this->prefix . $key;
    }

    public function get(string $key, $default = null)
    {
        $raw = $this->redis()->get($this->key($key));
        return $raw === false ? $default : unserialize($raw);
    }

    public function has(string $key): bool
    {
        return (bool) $this->redis()->exists($this->key($key));
    }

    public function put(string $key, $value, ?int $seconds = null): bool
    {
        $data = serialize($value);
        return $seconds === null
            ? (bool) $this->redis()->set($this->key($key), $data)
            : (bool) $this->redis()->setex($this->key($key), max(1, $seconds), $data);
    }

    public function forget(string $key): bool
    {
        $this->redis()->del($this->key($key));
        return true;
    }

    public function remember(string $key, ?int $seconds, callable $callback)
    {
        $raw = $this->redis()->get($this->key($key));
        if ($raw !== false) {
            return unserialize($raw);
        }
        $value = $callback();
        $this->put($key, $value, $seconds);
        return $value;
    }

    public function increment(string $key, int $by = 1, ?int $seconds = null): int
    {
        // Counters are stored serialized ("i:5;") so get() reads them like any other value.
        // One Lua script keeps read + write + expiry atomic; an existing expiry is kept.
        $script = "local raw = redis.call('GET', KEYS[1])\n"
            . "local value = tonumber(ARGV[1])\n"
            . "if raw then value = value + (tonumber(string.match(raw, '^i:(%-?%d+);$')) or 0) end\n"
            . "local ttl = redis.call('PTTL', KEYS[1])\n"
            . "redis.call('SET', KEYS[1], 'i:' .. value .. ';')\n"
            . "if ttl > 0 then redis.call('PEXPIRE', KEYS[1], ttl)\n"
            . "elseif not raw and tonumber(ARGV[2]) > 0 then redis.call('EXPIRE', KEYS[1], ARGV[2]) end\n"
            . "return value";
        return (int) $this->redis()->eval($script, [$this->key($key), $by, $seconds ?? 0], 1);
    }

    public function ttl(string $key): ?int
    {
        $ttl = $this->redis()->ttl($this->key($key));
        return match (true) {
            $ttl === -1 => null,
            $ttl < 0    => 0,
            default     => $ttl,
        };
    }

    public function flush(bool $expiredOnly = false): int
    {
        if ($expiredOnly) {
            return 0;   // Redis already removed them
        }
        // Only this app's cache keys, never the whole Redis database
        $count = 0;
        $iterator = null;
        $redis = $this->redis();
        $redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        while (($keys = $redis->scan($iterator, $this->prefix . '*', 500)) !== false) {
            if ($keys) {
                $count += (int) $redis->del($keys);
            }
        }
        return $count;
    }
}
