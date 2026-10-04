<?php
namespace System\Storage;

use System\Http\Response;

/**
 * Represents a single configured storage disk (local filesystem).
 */
class Disk
{
    public function __construct(
        protected string $name,
        protected array $config
    ) {}

    public function root(): string
    {
        return rtrim($this->config['root'] ?? (BASE_PATH . '/storage/app'), '/\\');
    }

    /** Absolute path for a relative storage path */
    public function path(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || str_contains($relative, '..') || str_starts_with($relative, '/') || preg_match('/^[a-z]:/i', $relative)) {
            throw new \InvalidArgumentException("Invalid storage path [$relative]");
        }
        return $this->root() . '/' . ltrim($relative, '/');
    }

    public function put(string $relative, string $contents): bool
    {
        $file = $this->path($relative);
        $this->ensureDirectory(dirname($file));
        return file_put_contents($file, $contents) !== false;
    }

    /** Copy (or move, for real uploads) a local file into storage */
    public function putFile(string $relative, string $source, bool $isUpload = false): void
    {
        $file = $this->path($relative);
        $this->ensureDirectory(dirname($file));
        $ok = $isUpload ? move_uploaded_file($source, $file) : copy($source, $file);
        if (!$ok) {
            throw new \RuntimeException("Could not store the file at $relative");
        }
    }

    public function get(string $relative): ?string
    {
        $file = $this->path($relative);
        return is_file($file) ? file_get_contents($file) : null;
    }

    public function exists(string $relative): bool
    {
        return is_file($this->path($relative));
    }

    public function missing(string $relative): bool
    {
        return !$this->exists($relative);
    }

    public function size(string $relative): int
    {
        $file = $this->path($relative);
        return is_file($file) ? (int) filesize($file) : 0;
    }

    public function lastModified(string $relative): int
    {
        $file = $this->path($relative);
        return is_file($file) ? (int) filemtime($file) : 0;
    }

    public function delete(string|array|null $relative): bool
    {
        if ($relative === null || $relative === '' || $relative === []) {
            return false;
        }
        $paths = (array) $relative;
        $success = true;
        foreach ($paths as $p) {
            $file = $this->path($p);
            if (is_file($file) && !@unlink($file)) {
                $success = false;
            }
        }
        return $success;
    }

    public function url(?string $relative): ?string
    {
        if ($relative === null || $relative === '') {
            return null;
        }
        $urlBase = $this->config['url'] ?? null;
        if (!$urlBase) {
            throw new \RuntimeException("Disk [{$this->name}] does not have a public URL configured.");
        }
        return rtrim($urlBase, '/') . '/' . ltrim(str_replace('\\', '/', $relative), '/');
    }

    /** Stream a file download response with proper HTTP headers */
    public function download(string $relative, ?string $filename = null, array $headers = []): DownloadResponse
    {
        $file = $this->path($relative);
        if (!is_file($file)) {
            throw new \System\Http\HttpException(404, "File not found [$relative]");
        }

        $filename ??= basename($file);
        return new DownloadResponse($file, $filename, $headers);
    }

    public function files(string $directory = ''): array
    {
        $dir = $directory === '' ? $this->root() : $this->path($directory);
        if (!is_dir($dir)) {
            return [];
        }
        $files = [];
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..' || $item === '.htaccess') continue;
            $full = $dir . '/' . $item;
            if (is_file($full)) {
                $files[] = trim($directory . '/' . $item, '/');
            }
        }
        return $files;
    }

    public function allFiles(string $directory = ''): array
    {
        $dir = $directory === '' ? $this->root() : $this->path($directory);
        if (!is_dir($dir)) {
            return [];
        }
        $results = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        $rootLen = strlen($this->root()) + 1;
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() !== '.htaccess') {
                $rel = str_replace('\\', '/', substr($file->getPathname(), $rootLen));
                $results[] = $rel;
            }
        }
        return $results;
    }

    public function makeDirectory(string $directory): bool
    {
        $path = $this->path($directory);
        return $this->ensureDirectory($path);
    }

    public function deleteDirectory(string $directory): bool
    {
        $dir = $this->path($directory);
        if (!is_dir($dir)) {
            return false;
        }
        $it = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
        // Remove links themselves, never what they point to (a link may lead outside the disk)
        foreach ($files as $file) {
            $path = $file->getPathname();
            if ($file->isLink()) {
                @unlink($path) || @rmdir($path);   // Windows directory links need rmdir
            } elseif ($file->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        return @rmdir($dir);
    }

    protected function ensureDirectory(string $dir): bool
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException("Could not create storage directory $dir");
        }

        // Guard web-accessible directories against executing uploaded scripts
        if (($this->config['visibility'] ?? 'public') === 'public') {
            $guard = $this->root() . '/.htaccess';
            if (!is_file($guard)) {
                @file_put_contents($guard, "# Uploaded files are data, never code\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh)$\">\n    Require all denied\n</FilesMatch>\nOptions -ExecCGI -Indexes\n");
            }
        }
        return true;
    }
}
