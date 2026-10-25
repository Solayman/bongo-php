<?php

declare(strict_types=1);

namespace System\Storage\Drivers;

use RuntimeException;
use System\Storage\Disk;

class SftpDriver extends Disk
{
    /** @var resource|null Native SSH2 connection */
    protected $connection = null;
    /** @var resource|null Native SFTP subsystem */
    protected $sftp = null;
    protected bool $connected = false;
    protected int $connectTimeout;
    protected int $timeout;
    protected bool $hostKeyVerify;
    protected ?string $chrootJailPrefix = null;

    /** @var array<string, string>|null In-memory fake filesystem for testing */
    protected static ?array $fakeFilesystem = null;

    public function __construct(string $name, array $config)
    {
        // Enforce fast deadline clamps: connection max 4s, operation max 8s
        $this->connectTimeout = min(4, max(1, (int) ($config['connect_timeout'] ?? 4)));
        $this->timeout = min(8, max(1, (int) ($config['timeout'] ?? 8)));

        // Non-interactive host key verification option (default to false for automated mirrors)
        $this->hostKeyVerify = (bool) ($config['hostkey_verify'] ?? false);

        $config['host'] ??= '127.0.0.1';
        $config['port'] ??= 22;
        $config['username'] ??= 'anonymous';
        $config['password'] ??= '';
        $config['root'] ??= '/';

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
        return 'sftp://' . ($this->config['host'] ?? '127.0.0.1') . '/' . ltrim((string) ($this->config['root'] ?? ''), '/');
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

        // If ext-ssh2 is available
        if (function_exists('ssh2_connect')) {
            $methods = [];
            $callbacks = [];

            // Bypass interactive host key prompt if hostkey verification is not enforced
            if (!$this->hostKeyVerify) {
                $callbacks['ignore'] = static fn() => true;
            }

            $conn = @ssh2_connect($host, $port, $methods, $callbacks);
            if (!$conn) {
                throw new RuntimeException("Could not connect to SFTP/SSH host [{$host}:{$port}].");
            }

            // Authentication: private key or password
            $username = (string) $this->config['username'];
            $authenticated = false;

            if (!empty($this->config['private_key'])) {
                $pub = $this->config['public_key'] ?? null;
                $passphrase = $this->config['passphrase'] ?? null;
                $authenticated = @ssh2_auth_pubkey_file($conn, $username, (string) $pub, (string) $this->config['private_key'], $passphrase);
            }

            if (!$authenticated && isset($this->config['password'])) {
                $authenticated = @ssh2_auth_password($conn, $username, (string) $this->config['password']);
            }

            if (!$authenticated) {
                throw new RuntimeException("SFTP authentication failed for user [{$username}].");
            }

            $sftp = @ssh2_sftp($conn);
            if (!$sftp) {
                throw new RuntimeException("Could not initialize SFTP subsystem on [{$host}:{$port}].");
            }

            $this->connection = $conn;
            $this->sftp = $sftp;
            $this->connected = true;
            $this->initChrootJail();
            return;
        }

        // cURL SFTP fallback (native in standard PHP with curl)
        $this->connected = true;
        $this->initChrootJail();
    }

    public function disconnect(): void
    {
        if (static::isFaking()) {
            $this->connected = false;
            return;
        }

        if ($this->connection !== null && function_exists('ssh2_disconnect')) {
            @ssh2_disconnect($this->connection);
        }

        $this->connection = null;
        $this->sftp = null;
        $this->connected = false;
    }

    protected function initChrootJail(): void
    {
        $root = (string) ($this->config['root'] ?? '/');
        if ($root === '/' || $root === '') {
            return;
        }

        // If sftp stream is active, test directory stat
        if ($this->sftp !== null) {
            $url = 'ssh2.sftp://' . intval($this->sftp) . $root;
            if (@is_dir($url)) {
                return;
            }

            $candidates = [
                ltrim($root, '/'),
                preg_replace('#^/home/[^/]+/#', '/', $root),
                preg_replace('#^/home/[^/]+/#', '', $root),
                preg_replace('#^/var/www/(html/)?#', '', $root),
            ];

            foreach (array_unique(array_filter($candidates)) as $candidate) {
                $testUrl = 'ssh2.sftp://' . intval($this->sftp) . '/' . ltrim((string) $candidate, '/');
                if (@is_dir($testUrl)) {
                    $this->chrootJailPrefix = (string) $candidate;
                    return;
                }
            }
        }
    }

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

        if ($this->sftp !== null) {
            $url = 'ssh2.sftp://' . intval($this->sftp) . $remotePath;
            $this->ensureRemoteDirectory(dirname($remotePath));
            return @file_put_contents($url, $contents) !== false;
        }

        // cURL SFTP fallback
        if (extension_loaded('curl')) {
            $url = "sftp://{$this->config['host']}:{$this->config['port']}{$remotePath}";
            $ch = curl_init($url);
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $contents);
            rewind($stream);

            $opts = [
                CURLOPT_UPLOAD => true,
                CURLOPT_INFILE => $stream,
                CURLOPT_INFILESIZE => strlen($contents),
                CURLOPT_USERPWD => "{$this->config['username']}:{$this->config['password']}",
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_TIMEOUT => $this->timeout,
                CURLOPT_FTP_CREATE_MISSING_DIRS => true,
            ];

            if (!$this->hostKeyVerify) {
                // Non-interactive host key verification bypass
                $opts[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PASSWORD | CURLSSH_AUTH_DEFAULT;
            }

            curl_setopt_array($ch, $opts);
            $res = curl_exec($ch);
            $err = curl_errno($ch);
            curl_close($ch);
            fclose($stream);
            return $res !== false && $err === 0;
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

        if ($this->sftp !== null) {
            $url = 'ssh2.sftp://' . intval($this->sftp) . $remotePath;
            $data = @file_get_contents($url);
            return $data !== false ? $data : null;
        }

        if (extension_loaded('curl')) {
            $url = "sftp://{$this->config['host']}:{$this->config['port']}{$remotePath}";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD => "{$this->config['username']}:{$this->config['password']}",
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_TIMEOUT => $this->timeout,
            ]);
            $data = curl_exec($ch);
            $err = curl_errno($ch);
            curl_close($ch);
            return ($err === 0 && is_string($data)) ? $data : null;
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

        if ($this->sftp !== null) {
            $url = 'ssh2.sftp://' . intval($this->sftp) . $remotePath;
            return file_exists($url);
        }

        return $this->get($relative) !== null;
    }

    public function size(string $relative): int
    {
        if (static::isFaking()) {
            return isset(static::$fakeFilesystem[$relative]) ? strlen(static::$fakeFilesystem[$relative]) : 0;
        }

        $content = $this->get($relative);
        return $content !== null ? strlen($content) : 0;
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
            if ($this->sftp !== null) {
                $url = 'ssh2.sftp://' . intval($this->sftp) . $remotePath;
                if (!@unlink($url)) {
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

        if ($this->sftp !== null) {
            $url = 'ssh2.sftp://' . intval($this->sftp) . $remotePath;
            $items = @scandir($url);
            if (is_array($items)) {
                return array_values(array_filter($items, fn($item) => $item !== '.' && $item !== '..'));
            }
        }

        return [];
    }

    protected function ensureRemoteDirectory(string $dir): void
    {
        if ($dir === '.' || $dir === '/' || $dir === '' || $this->sftp === null) {
            return;
        }

        $parts = explode('/', trim($dir, '/'));
        $current = str_starts_with($dir, '/') ? '' : '.';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            $url = 'ssh2.sftp://' . intval($this->sftp) . $current;
            if (!is_dir($url)) {
                @mkdir($url);
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

    public function isHostKeyVerify(): bool
    {
        return $this->hostKeyVerify;
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
