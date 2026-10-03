<?php
namespace System\Cache;

/**
 * Key/value cache in files (storage/cache/data). One file per key, written
 * atomically; values are PHP-serialized with their expiry time.
 */
class FileStore
{
    /** Seconds after which an unused .lock file counts as stale */
    protected const LOCK_TTL = 3600;

    public function __construct(protected string $path) {}

    protected function file(string $key): string
    {
        $hash = sha1($key);
        return $this->path . '/' . substr($hash, 0, 2) . '/' . $hash . '.cache';
    }

    public function get(string $key, $default = null)
    {
        $record = $this->read($key);
        return $record === null ? $default : $record['value'];
    }

    public function has(string $key): bool
    {
        return $this->read($key) !== null;
    }

    /** @param int|null $seconds null = forever */
    public function put(string $key, $value, ?int $seconds = null): bool
    {
        $file = $this->file($key);
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return false;
        }

        $data = serialize(['expires' => $seconds === null ? 0 : time() + $seconds, 'value' => $value]);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $data) === false) {
            return false;
        }
        if (!@rename($tmp, $file)) {
            // Windows cannot replace a file another request has open; write it in place instead
            @unlink($tmp);
            return @file_put_contents($file, $data, LOCK_EX) !== false;
        }
        return true;
    }

    public function forget(string $key): bool
    {
        $file = $this->file($key);
        return !is_file($file) || @unlink($file);
    }

    /** The cached value, or compute it with $callback, store it for $seconds and return it */
    public function remember(string $key, ?int $seconds, callable $callback)
    {
        $record = $this->read($key);
        if ($record !== null) {
            return $record['value'];
        }
        $value = $callback();
        $this->put($key, $value, $seconds);
        return $value;
    }

    /**
     * Atomically add $by to an integer (created with $seconds lifetime if missing).
     * The expiry of an existing counter is kept. Returns the new value.
     */
    public function increment(string $key, int $by = 1, ?int $seconds = null): int
    {
        $lock = $this->lock($key);
        try {
            $record = $this->read($key);
            $value = (int) ($record['value'] ?? 0) + $by;
            $expires = $record['expires'] ?? ($seconds === null ? 0 : time() + $seconds);
            $remaining = $expires === 0 ? null : max(1, $expires - time());
            $this->put($key, $value, $remaining);
            return $value;
        } finally {
            $this->unlock($lock);
        }
    }

    /** Seconds until $key expires (null = forever, 0 = missing) */
    public function ttl(string $key): ?int
    {
        $record = $this->read($key);
        if ($record === null) return 0;
        return $record['expires'] === 0 ? null : max(0, $record['expires'] - time());
    }

    /** Remove everything (or only expired entries) */
    public function flush(bool $expiredOnly = false): int
    {
        $count = 0;
        foreach (glob($this->path . '/*/*.cache') ?: [] as $file) {
            if ($expiredOnly) {
                $record = @unserialize((string) @file_get_contents($file));
                if (!is_array($record) || $record['expires'] === 0 || $record['expires'] > time()) continue;
            }
            $count += (int) @unlink($file);
        }

        // Lock files are held for microseconds; one unused for an hour is left over from an old key
        $staleBefore = time() - self::LOCK_TTL;
        foreach (glob($this->path . '/*.lock') ?: [] as $lock) {
            if (!$expiredOnly || (int) @filemtime($lock) < $staleBefore) @unlink($lock);
        }
        if (!$expiredOnly) {
            foreach (glob($this->path . '/*', GLOB_ONLYDIR) ?: [] as $dir) {
                @rmdir($dir);   // only succeeds when empty
            }
        }
        return $count;
    }

    protected function read(string $key): ?array
    {
        $file = $this->file($key);
        if (!is_file($file)) {
            return null;
        }
        $record = @unserialize((string) @file_get_contents($file), ['allowed_classes' => true]);
        if (!is_array($record) || !array_key_exists('value', $record)) {
            return null;
        }
        if ($record['expires'] !== 0 && $record['expires'] <= time()) {
            @unlink($file);
            return null;
        }
        return $record;
    }

    /** @return resource|null */
    protected function lock(string $key)
    {
        if (!is_dir($this->path)) @mkdir($this->path, 0775, true);
        $file = $this->path . '/' . sha1($key) . '.lock';
        $handle = @fopen($file, 'c');
        if ($handle) {
            flock($handle, LOCK_EX);
            @touch($file);   // last use, so flush(true) can tell stale locks apart
        }
        return $handle ?: null;
    }

    protected function unlock($handle): void
    {
        if ($handle) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
