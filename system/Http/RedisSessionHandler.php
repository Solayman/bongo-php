<?php

namespace System\Http;

use System\Redis\Redis;

/**
 * Sessions in Redis (SESSION_DRIVER=redis): every server of the app sees the
 * same sessions, and idle ones expire by themselves.
 */
class RedisSessionHandler implements \SessionHandlerInterface, \SessionUpdateTimestampHandlerInterface
{
    public function __construct(protected int $lifetime, protected ?\Redis $redis = null, protected ?string $prefix = null)
    {
        $this->prefix ??= Redis::prefix() . 'session:';
    }

    protected function redis(): \Redis
    {
        return $this->redis ??= Redis::connection();
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = $this->redis()->get($this->prefix . $id);
        return $data === false ? '' : $data;
    }

    public function write(string $id, string $data): bool
    {
        return (bool) $this->redis()->setex($this->prefix . $id, $this->lifetime, $data);
    }

    public function destroy(string $id): bool
    {
        $this->redis()->del($this->prefix . $id);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return 0;   // Redis expires sessions by itself
    }

    /** session.use_strict_mode: only ids this server created are accepted */
    public function validateId(string $id): bool
    {
        return (bool) $this->redis()->exists($this->prefix . $id);
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        return (bool) $this->redis()->expire($this->prefix . $id, $this->lifetime);
    }
}
