<?php

declare(strict_types=1);

namespace System\Storage\Drivers;

use RuntimeException;
use System\Storage\Disk;

class FtpDriver extends Disk
{
    /** @var resource|null Native FTP connection resource */
    protected $connection = null;
    /** @var resource|null Socket connection for pure PHP socket fallback */
    protected $socket = null;
    protected bool $connected = false;
    protected ?string $chrootJailPrefix = null;
    protected int $connectTimeout;
    protected int $timeout;

    /** @var array<string, string>|null In-memory fake filesystem for testing */
    protected static ?array $fakeFilesystem = null;

    public function __construct(string $name, array $config)
    {
        // Enforce fast deadline clamps: connection max 4s, operation max 8s
        $this->connectTimeout = min(4, max(1, (int) ($config['connect_timeout'] ?? 4)));
        $this->timeout = min(8, max(1, (int) ($config['timeout'] ?? 8)));

        $config['host'] ??= '127.0.0.1';
        $config['port'] ??= 21;
        $config['username'] ??= 'anonymous';
        $config['password'] ??= '';
        $config['root'] ??= '/';
        $config['passive'] ??= true;
        $config['ssl'] ??= false;

        parent::__construct($name, $config);
    }

    public static function fake(?array $initialFiles = []): void
    {
        static::$fakeFilesystem = $initialFiles ?? [];
    }

    public static function stopFaking(): void
    {
        static::$fakeFilesystem = null;
    }

    public static function isFaking(): bool
    {
        return static::$fakeFilesystem !== null;
    }

    public function root(): string
    {
        return 'ftp://' . ($this->config['host'] ?? '127.0.0.1') . '/' . ltrim((string) ($this->config['root'] ?? ''), '/');
    }

    public function connect(): void
    {
        if (static::isFaking()) {
            $this->connected = true;
            return;
        }

        if ($this->connected) {
            return;
        }

        $host = (string) $this->config['host'];
        $port = (int) $this->config['port'];

        if (function_exists('ftp_connect')) {
            $conn = $this->config['ssl'] && function_exists('ftp_ssl_connect')
                ? @ftp_ssl_connect($host, $port, $this->connectTimeout)
                : @ftp_connect($host, $port, $this->connectTimeout);

            if ($conn === false) {
                throw new RuntimeException("Could not connect to FTP host [{$host}:{$port}].");
            }

            $login = @ftp_login($conn, (string) $this->config['username'], (string) $this->config['password']);
            if (!$login) {
                @ftp_close($conn);
                throw new RuntimeException("FTP authentication failed for user [{$this->config['username']}].");
            }

            if (!empty($this->config['passive'])) {
                @ftp_pasv($conn, true);
            }

            if (function_exists('ftp_set_option')) {
                @ftp_set_option($conn, FTP_TIMEOUT_SEC, $this->timeout);
            }

            $this->connection = $conn;
            $this->connected = true;

            $this->initChrootJail();
            return;
        }

        // Pure PHP socket transport
        $errno = 0;
        $errstr = '';
        $sock = @fsockopen($host, $port, $errno, $errstr, (float) $this->connectTimeout);
        if (!$sock) {
            throw new RuntimeException("FTP socket connection failed to [{$host}:{$port}]: {$errstr} ({$errno})");
        }

        stream_set_timeout($sock, $this->timeout);
        $banner = fgets($sock);
        if (!$banner || !str_starts_with($banner, '220')) {
            fclose($sock);
            throw new RuntimeException("Invalid FTP greeting banner: {$banner}");
        }

        fwrite($sock, "USER {$this->config['username']}\r\n");
        $res = fgets($sock);
        if ($res && str_starts_with($res, '331')) {
            fwrite($sock, "PASS {$this->config['password']}\r\n");
            $res = fgets($sock);
        }

        if (!$res || (!str_starts_with($res, '230') && !str_starts_with($res, '200'))) {
            fclose($sock);
            throw new RuntimeException("FTP login failed: {$res}");
        }

        $this->socket = $sock;
        $this->connected = true;
        $this->initChrootJail();
    }

    public function disconnect(): void
    {
        if (static::isFaking()) {
            $this->connected = false;
            return;
        }

        if ($this->connection !== null && function_exists('ftp_close')) {
            @ftp_close($this->connection);
            $this->connection = null;
        }

        if ($this->socket !== null) {
            @fwrite($this->socket, "QUIT\r\n");
            @fclose($this->socket);
            $this->socket = null;
        }

        $this->connected = false;
    }

    /**
     * Detect virtual FTP users chrooted into home directory and determine path adjustment
     */
    protected function initChrootJail(): void
    {
        $root = (string) ($this->config['root'] ?? '/');
        if ($root === '/' || $root === '') {
            return;
        }

        if ($this->connection !== null && function_exists('ftp_chdir')) {
            if (@ftp_chdir($this->connection, $root)) {
                return; // Standard path works fine
            }

            // CWD failed: chroot jail fallback detection
            // e.g. /home/user/public_html or /home/user/app -> try relative or strip /home/<user>
            $candidates = [
                ltrim($root, '/'),
                preg_replace('#^/home/[^/]+/#', '/', $root),
                preg_replace('#^/home/[^/]+/#', '', $root),
                preg_replace('#^/var/www/(html/)?#', '', $root),
            ];

            foreach (array_unique(array_filter($candidates)) as $candidate) {
                if (@ftp_chdir($this->connection, (string) $candidate)) {
                    $this->chrootJailPrefix = (string) $candidate;
                    return;
                }
            }
        }
    }

    /**
     * Resolve target remote path with chroot fallback support
     */
    public function resolveRemotePath(string $relative): string
    {
        $relative = ltrim(str_replace(['\\', "\0"], ['/', ''], $relative), '/');
        $baseRoot = (string) ($this->config['root'] ?? '/');

        if ($this->chrootJailPrefix !== null) {
            $baseRoot = $this->chrootJailPrefix;
        }

        $baseRoot = rtrim($baseRoot, '/');
        if ($baseRoot === '') {
            return '/' . $relative;
        }

        return $relative === '' ? $baseRoot : $baseRoot . '/' . $relative;
    }

    public function put(string $relative, string $contents): bool
    {
        if (static::isFaking()) {
            static::$fakeFilesystem[$relative] = $contents;
            return true;
        }

        $this->connect();
        $remotePath = $this->resolveRemotePath($relative);

        if ($this->connection !== null && function_exists('ftp_fput')) {
            $temp = fopen('php://temp', 'r+');
            fwrite($temp, $contents);
            rewind($temp);
            $this->ensureRemoteDirectory(dirname($remotePath));
            $res = @ftp_fput($this->connection, $remotePath, $temp, FTP_BINARY);
            fclose($temp);
            return (bool) $res;
        }

        return false;
    }

    public function get(string $relative): ?string
    {
        if (static::isFaking()) {
            return static::$fakeFilesystem[$relative] ?? null;
        }

        $this->connect();
        $remotePath = $this->resolveRemotePath($relative);

        if ($this->connection !== null && function_exists('ftp_fget')) {
            $temp = fopen('php://temp', 'r+');
            if (@ftp_fget($this->connection, $temp, $remotePath, FTP_BINARY)) {
                rewind($temp);
                $data = stream_get_contents($temp);
                fclose($temp);
                return $data !== false ? $data : null;
            }
            fclose($temp);
            return null;
        }

        return null;
    }

    public function exists(string $relative): bool
    {
        if (static::isFaking()) {
            return isset(static::$fakeFilesystem[$relative]);
        }

        $this->connect();
        $remotePath = $this->resolveRemotePath($relative);

        if ($this->connection !== null && function_exists('ftp_size')) {
            $size = @ftp_size($this->connection, $remotePath);
            return $size >= 0;
        }

        return false;
    }

    public function size(string $relative): int
    {
        if (static::isFaking()) {
            return isset(static::$fakeFilesystem[$relative]) ? strlen(static::$fakeFilesystem[$relative]) : 0;
        }

        $this->connect();
        $remotePath = $this->resolveRemotePath($relative);

        if ($this->connection !== null && function_exists('ftp_size')) {
            $size = @ftp_size($this->connection, $remotePath);
            return $size >= 0 ? $size : 0;
        }

        return 0;
    }

    public function lastModified(string $relative): int
    {
        if (static::isFaking()) {
            return isset(static::$fakeFilesystem[$relative]) ? time() : 0;
        }

        $this->connect();
        $remotePath = $this->resolveRemotePath($relative);

        if ($this->connection !== null && function_exists('ftp_mdtm')) {
            $time = @ftp_mdtm($this->connection, $remotePath);
            return $time > 0 ? $time : 0;
        }

        return 0;
    }

    public function delete(string|array|null $relative): bool
    {
        if ($relative === null || $relative === '' || $relative === []) {
            return false;
        }

        $paths = (array) $relative;
        $success = true;

        foreach ($paths as $p) {
            $rel = (string) $p;
            if (static::isFaking()) {
                if (isset(static::$fakeFilesystem[$rel])) {
                    unset(static::$fakeFilesystem[$rel]);
                } else {
                    $success = false;
                }
                continue;
            }

            $this->connect();
            $remotePath = $this->resolveRemotePath($rel);
            if ($this->connection !== null && function_exists('ftp_delete')) {
                if (!@ftp_delete($this->connection, $remotePath)) {
                    $success = false;
                }
            } else {
                $success = false;
            }
        }

        return $success;
    }

    public function files(string $directory = ''): array
    {
        if (static::isFaking()) {
            $matched = [];
            $prefix = $directory !== '' ? rtrim($directory, '/') . '/' : '';
            foreach (array_keys(static::$fakeFilesystem ?? []) as $path) {
                if ($prefix === '' || str_starts_with($path, $prefix)) {
                    $rel = substr($path, strlen($prefix));
                    if (!str_contains($rel, '/')) {
                        $matched[] = $path;
                    }
                }
            }
            return $matched;
        }

        $this->connect();
        $remotePath = $this->resolveRemotePath($directory);

        if ($this->connection !== null && function_exists('ftp_nlist')) {
            $list = @ftp_nlist($this->connection, $remotePath ?: '.');
            return is_array($list) ? $list : [];
        }

        return [];
    }

    public function allFiles(string $directory = ''): array
    {
        if (static::isFaking()) {
            $matched = [];
            $prefix = $directory !== '' ? rtrim($directory, '/') . '/' : '';
            foreach (array_keys(static::$fakeFilesystem ?? []) as $path) {
                if ($prefix === '' || str_starts_with($path, $prefix)) {
                    $matched[] = $path;
                }
            }
            return $matched;
        }

        return $this->files($directory);
    }

    public function makeDirectory(string $directory): bool
    {
        if (static::isFaking()) {
            return true;
        }

        $this->connect();
        $remotePath = $this->resolveRemotePath($directory);

        if ($this->connection !== null && function_exists('ftp_mkdir')) {
            return (bool) @ftp_mkdir($this->connection, $remotePath);
        }

        return false;
    }

    public function deleteDirectory(string $directory): bool
    {
        if (static::isFaking()) {
            $prefix = rtrim($directory, '/') . '/';
            foreach (array_keys(static::$fakeFilesystem ?? []) as $f) {
                if (str_starts_with($f, $prefix)) {
                    unset(static::$fakeFilesystem[$f]);
                }
            }
            return true;
        }

        $this->connect();
        $remotePath = $this->resolveRemotePath($directory);

        if ($this->connection !== null && function_exists('ftp_rmdir')) {
            return @ftp_rmdir($this->connection, $remotePath);
        }

        return false;
    }

    protected function ensureRemoteDirectory(string $dir): void
    {
        if ($dir === '.' || $dir === '/' || $dir === '') {
            return;
        }

        $parts = explode('/', trim($dir, '/'));
        $current = str_starts_with($dir, '/') ? '' : '.';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            if ($this->connection !== null && function_exists('ftp_mkdir')) {
                @ftp_mkdir($this->connection, $current);
            }
        }
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getChrootJailPrefix(): ?string
    {
        return $this->chrootJailPrefix;
    }

    public function setChrootJailPrefix(?string $prefix): void
    {
        $this->chrootJailPrefix = $prefix;
    }
}
