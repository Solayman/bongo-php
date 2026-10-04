<?php

namespace System\Storage;

use System\Http\Response;

/**
 * Multi-disk file storage manager (facade).
 *
 *   Storage::disk('public')->put('products/photo.jpg', $data);
 *   Storage::disk('local')->put('invoices/101.pdf', $pdf);     // private storage/app/
 *   Storage::url('products/photo.jpg');                        // http://.../storage/products/photo.jpg
 *   Storage::disk('local')->download('invoices/101.pdf');      // streaming response
 *
 * Paths are always relative and cannot leave the storage folder ("../" is rejected).
 */
class Storage
{
    /** @var array<string, Disk> */
    protected static array $disks = [];

    /** @var array<string, callable> */
    protected static array $customDrivers = [];

    /** Register a custom storage driver creator */
    public static function extend(string $driver, callable $callback): void
    {
        self::$customDrivers[$driver] = $callback;
    }

    /** Explicitly set or mock a disk instance */
    public static function set(string $name, Disk $disk): void
    {
        self::$disks[$name] = $disk;
    }

    /** Retrieve a disk instance by name (defaults to config('storage.default', 'public')) */
    public static function disk(?string $name = null): Disk
    {
        $name ??= (string) config('storage.default', 'public');
        if (isset(self::$disks[$name])) {
            return self::$disks[$name];
        }

        $config = config('storage.disks.' . $name);
        if ($config === null) {
            if ($name === 'public') {
                $config = [
                    'driver' => 'local',
                    'root' => (string) config('storage.root', BASE_PATH . '/public/storage'),
                    'url' => asset('storage'),
                    'visibility' => 'public',
                ];
            } elseif ($name === 'local') {
                $config = [
                    'driver' => 'local',
                    'root' => BASE_PATH . '/storage/app',
                    'visibility' => 'private',
                ];
            } elseif ($name === 's3' && is_file(BASE_PATH . '/config/s3.php')) {
                $config = (array) config('s3', []);
                $config['driver'] ??= 's3';
            } else {
                throw new \InvalidArgumentException("Storage disk [$name] is not configured.");
            }
        }

        $driver = (string) ($config['driver'] ?? 'local');
        if (isset(self::$customDrivers[$driver])) {
            return self::$disks[$name] = (self::$customDrivers[$driver])($config, $name);
        }

        return self::$disks[$name] = new Disk($name, $config);
    }

    public static function root(): string
    {
        return self::disk()->root();
    }

    public static function path(string $relative): string
    {
        return self::disk()->path($relative);
    }

    public static function put(string $relative, string $contents): bool
    {
        return self::disk()->put($relative, $contents);
    }

    public static function putFile(string $relative, string $source, bool $isUpload = false): void
    {
        self::disk()->putFile($relative, $source, $isUpload);
    }

    public static function get(string $relative): ?string
    {
        return self::disk()->get($relative);
    }

    public static function exists(string $relative): bool
    {
        return self::disk()->exists($relative);
    }

    public static function missing(string $relative): bool
    {
        return self::disk()->missing($relative);
    }

    public static function delete(string|array|null $relative): bool
    {
        return self::disk()->delete($relative);
    }

    public static function url(?string $relative): ?string
    {
        return self::disk('public')->url($relative);
    }

    public static function download(string $relative, ?string $filename = null, array $headers = []): DownloadResponse
    {
        return self::disk()->download($relative, $filename, $headers);
    }

    public static function size(string $relative): int
    {
        return self::disk()->size($relative);
    }

    public static function lastModified(string $relative): int
    {
        return self::disk()->lastModified($relative);
    }

    public static function files(string $directory = ''): array
    {
        return self::disk()->files($directory);
    }

    public static function allFiles(string $directory = ''): array
    {
        return self::disk()->allFiles($directory);
    }

    public static function makeDirectory(string $directory): bool
    {
        return self::disk()->makeDirectory($directory);
    }

    public static function deleteDirectory(string $directory): bool
    {
        return self::disk()->deleteDirectory($directory);
    }

    public static function temporaryUrl(string $relative, mixed $expiration, array $options = []): string
    {
        $disk = self::disk();
        if (method_exists($disk, 'temporaryUrl')) {
            return $disk->temporaryUrl($relative, $expiration, $options);
        }
        return (string) $disk->url($relative);
    }

    public static function reset(): void
    {
        self::$disks = [];
        self::$customDrivers = [];
    }
}
