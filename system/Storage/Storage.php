<?php
namespace System\Storage;

/**
 * Public file storage in public/storage (served directly by the web server).
 *
 *   Storage::put('reports/today.txt', $text);
 *   Storage::url('products/a1b2.jpg');   // http://.../storage/products/a1b2.jpg
 *   Storage::delete('products/a1b2.jpg');
 *
 * Paths are always relative and cannot leave the storage folder ("../" is rejected).
 */
class Storage
{
    public static function root(): string
    {
        return (string) config('storage.root', BASE_PATH . '/public/storage');
    }

    /** Absolute path for a relative storage path */
    public static function path(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || preg_match('/^[a-z]:/i', $relative)) {
            throw new \InvalidArgumentException("Invalid storage path [$relative]");
        }
        return rtrim(self::root(), '/\\') . '/' . ltrim($relative, '/');
    }

    public static function put(string $relative, string $contents): bool
    {
        $file = self::path($relative);
        self::ensureDirectory(dirname($file));
        return file_put_contents($file, $contents) !== false;
    }

    /** Copy (or move, for real uploads) a local file into storage */
    public static function putFile(string $relative, string $source, bool $isUpload = false): void
    {
        $file = self::path($relative);
        self::ensureDirectory(dirname($file));
        $ok = $isUpload ? move_uploaded_file($source, $file) : copy($source, $file);
        if (!$ok) {
            throw new \RuntimeException("Could not store the file at $relative");
        }
    }

    public static function get(string $relative): ?string
    {
        $file = self::path($relative);
        return is_file($file) ? file_get_contents($file) : null;
    }

    public static function exists(string $relative): bool
    {
        return is_file(self::path($relative));
    }

    public static function delete(?string $relative): bool
    {
        if ($relative === null || $relative === '') {
            return false;
        }
        $file = self::path($relative);
        return is_file($file) && @unlink($file);
    }

    public static function url(?string $relative): ?string
    {
        return $relative ? asset('storage/' . ltrim(str_replace('\\', '/', $relative), '/')) : null;
    }

    protected static function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Could not create storage directory $dir");
        }

        // Apache: never execute scripts from the storage folder
        $guard = rtrim(self::root(), '/\\') . '/.htaccess';
        if (!is_file($guard)) {
            @file_put_contents($guard, "# Uploaded files are data, never code\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n    Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
        }
    }
}
