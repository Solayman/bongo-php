<?php
namespace System\Console;

use RuntimeException;

/**
 * Upgrades only the framework's own files, never your code:
 *
 *   php bongo release:package          → dist/bongomvc-framework-0.9.0.json.gz (+ .sha256), for a GitHub release
 *   php bongo upgrade --check          → installed vs latest version and the release notes
 *   php bongo upgrade                  → download, verify the SHA-256, back up, replace, report new config keys
 *   php bongo upgrade --from=file.gz   → the same from a downloaded package (offline)
 *   php bongo upgrade --rollback       → put the backup back
 *
 * The package is gzip-compressed JSON: {version, files: {path: base64}, config: {file: [keys]}}.
 */
class Upgrader
{
    /** What an upgrade replaces: the framework. Your app/, routes/, resources/, config/*.php settings, database/ and storage/ are never touched. */
    public const FRAMEWORK_FILES = ['system', 'bongo', 'public/index.php', 'config/bootstrap.php'];

    public function __construct(protected string $base) {}

    // --- Packaging ------------------------------------------------------

    /** Write the package and its .sha256; returns the package path */
    public function package(string $outDir): string
    {
        $version = \System\Framework::version();
        $files = [];
        foreach (self::FRAMEWORK_FILES as $path) {
            foreach ($this->filesUnder("{$this->base}/$path") as $file) {
                $files[$this->relative($file)] = base64_encode((string) file_get_contents($file));
            }
        }
        // Every config file's keys, so an upgrade can tell which new settings you don't have yet
        $config = [];
        foreach (glob("{$this->base}/config/*.php") ?: [] as $file) {
            if (basename($file) !== 'bootstrap.php') {
                $config[basename($file)] = array_keys((array) (function () use ($file) { return require $file; })());
            }
        }

        if (!is_dir($outDir)) mkdir($outDir, 0775, true);
        $path = rtrim($outDir, '/\\') . "/bongomvc-framework-$version.json.gz";
        file_put_contents($path, gzencode(json_encode(['version' => $version, 'files' => $files, 'config' => $config], JSON_UNESCAPED_SLASHES), 9));
        file_put_contents("$path.sha256", hash_file('sha256', $path) . '  ' . basename($path) . "\n");
        return $path;
    }

    // --- Checking ---------------------------------------------------------

    /** The latest release: [version, notes, package url, sha256 url] (GitHub's releases API by default) */
    public function latest(): array
    {
        $url = (string) config('app.upgrade_url', 'https://api.github.com/repos/Solayman/BongoMvc/releases/latest');
        $release = json_decode($this->download($url), true);
        if (!is_array($release) || empty($release['tag_name'])) {
            throw new RuntimeException("No release found at $url");
        }
        $assets = array_column($release['assets'] ?? [], 'browser_download_url', 'name');
        $package = current(array_filter($assets, fn($u, $name) => str_ends_with($name, '.json.gz'), ARRAY_FILTER_USE_BOTH)) ?: null;
        $sha = current(array_filter($assets, fn($u, $name) => str_ends_with($name, '.json.gz.sha256'), ARRAY_FILTER_USE_BOTH)) ?: null;
        return [ltrim((string) $release['tag_name'], 'v'), (string) ($release['body'] ?? ''), $package, $sha];
    }

    // --- Applying -----------------------------------------------------------

    /**
     * Install a package file after checking its SHA-256 (when given). Returns
     * ['from' => old version, 'to' => new version, 'files' => n, 'backup' => dir, 'newConfig' => [file => [keys]]].
     */
    public function apply(string $packageFile, ?string $expectedSha256 = null): array
    {
        if ($expectedSha256 !== null && !hash_equals(strtolower(trim($expectedSha256)), hash_file('sha256', $packageFile))) {
            throw new RuntimeException('The download does not match its SHA-256 checksum. Nothing was changed.');
        }
        $package = json_decode((string) @gzdecode((string) file_get_contents($packageFile)), true);
        if (!is_array($package) || empty($package['version']) || empty($package['files'])) {
            throw new RuntimeException('Not a BongoMVC framework package.');
        }

        // Only framework paths, no "../" tricks
        foreach (array_keys($package['files']) as $path) {
            $inFramework = false;
            foreach (self::FRAMEWORK_FILES as $allowed) {
                if ($path === $allowed || str_starts_with($path, "$allowed/")) $inFramework = true;
            }
            if (!$inFramework || str_contains($path, '..') || str_contains($path, '\\')) {
                throw new RuntimeException("The package wants to write outside the framework: $path");
            }
        }

        $from = trim((string) @file_get_contents("{$this->base}/system/VERSION")) ?: 'unknown';
        $backup = $this->backup($from);

        // Replace system/ completely (removed framework files go too), the other files one by one
        $this->deleteTree("{$this->base}/system");
        foreach ($package['files'] as $path => $content) {
            $target = "{$this->base}/$path";
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
            file_put_contents($target, base64_decode($content));
        }
        foreach (glob("{$this->base}/storage/cache/views/*.php") ?: [] as $compiled) @unlink($compiled);

        $newConfig = [];
        foreach ((array) ($package['config'] ?? []) as $file => $keys) {
            $mine = is_file("{$this->base}/config/$file") ? array_keys((array) (function () use ($file) { return require "{$this->base}/config/$file"; })()) : [];
            $missing = array_values(array_diff($keys, $mine));
            if ($missing) $newConfig[$file] = $missing;
        }

        return ['from' => $from, 'to' => (string) $package['version'], 'files' => count($package['files']), 'backup' => $backup, 'newConfig' => $newConfig];
    }

    /** Restore the newest backup; returns the version restored */
    public function rollback(): string
    {
        $backups = glob("{$this->base}/storage/upgrade-backup/*", GLOB_ONLYDIR) ?: [];
        rsort($backups);
        if (!$backups) {
            throw new RuntimeException('There is no backup to restore.');
        }
        $dir = $backups[0];
        $this->deleteTree("{$this->base}/system");
        foreach ($this->filesUnder($dir) as $file) {
            $target = "{$this->base}/" . substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $dir)) + 1);
            if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
            copy($file, $target);
        }
        return trim((string) @file_get_contents("$dir/system/VERSION")) ?: basename($dir);
    }

    /** GET a URL (with UPGRADE_TOKEN for private repositories) */
    public function download(string $url): string
    {
        $headers = "User-Agent: BongoMVC-upgrade\r\nAccept: application/vnd.github+json, application/octet-stream\r\n";
        if ($token = (string) config('app.upgrade_token', '')) {
            $headers .= "Authorization: Bearer $token\r\n";
        }
        $body = @file_get_contents($url, false, stream_context_create(['http' => ['header' => $headers, 'timeout' => 30, 'follow_location' => 1]]));
        if ($body === false) {
            throw new RuntimeException("Could not download $url" . (str_contains($url, 'github') ? ' (a private repository needs UPGRADE_TOKEN in .env)' : ''));
        }
        return $body;
    }

    // --- Helpers -----------------------------------------------------------

    protected function backup(string $version): string
    {
        $dir = "{$this->base}/storage/upgrade-backup/" . date('Ymd_His') . "_$version";
        foreach (self::FRAMEWORK_FILES as $path) {
            foreach ($this->filesUnder("{$this->base}/$path") as $file) {
                $target = "$dir/" . $this->relative($file);
                if (!is_dir(dirname($target))) mkdir(dirname($target), 0775, true);
                copy($file, $target);
            }
        }
        return $dir;
    }

    protected function filesUnder(string $path): array
    {
        if (is_file($path)) return [$path];
        if (!is_dir($path)) return [];
        $files = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile()) $files[] = $file->getPathname();
        }
        sort($files);
        return $files;
    }

    protected function relative(string $file): string
    {
        return substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', $this->base)) + 1);
    }

    protected function deleteTree(string $dir): void
    {
        if (!is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }
}
