<?php

namespace System\Package;

use System\Framework;
use RuntimeException;

/**
 * Pure-PHP package downloader, extractor, publisher, and verifier.
 * Zero external dependencies.
 */
class Installer
{
    protected string $basePath;

    public function __construct(?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? BASE_PATH, '/\\');
    }

    /**
     * Retrieve the catalog index.
     * Prefers local directory (BONGO_PACKAGES_PATH) if configured; falls back to GitHub.
     */
    public function catalog(): array
    {
        $localPath = $this->localPackagesPath();
        if ($localPath !== null && is_file($localPath . '/index.json')) {
            $json = json_decode((string) file_get_contents($localPath . '/index.json'), true);
            if (is_array($json) && isset($json['packages']) && is_array($json['packages'])) {
                return $json['packages'];
            }
        }

        $url = 'https://raw.githubusercontent.com/Solayman/bongo-php-packages/master/index.json';
        $token = $this->resolveToken();

        $headers = ["User-Agent: Bongo-Package-Manager/" . Framework::version()];
        if ($token !== '') {
            $headers[] = "Authorization: token $token";
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException("Could not fetch package catalog. Check network or set BONGO_PACKAGES_TOKEN in .env.");
        }

        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['packages']) || !is_array($json['packages'])) {
            throw new RuntimeException("Invalid package catalog response from repository.");
        }

        return $json['packages'];
    }

    /**
     * Install a package into packages/<name>/ and register in .lock.json.
     */
    public function install(string $name, ?string $version = null, bool $force = false, bool $backup = false): array
    {
        $name = strtolower(trim($name));
        $catalog = $this->catalog();

        if (!isset($catalog[$name])) {
            $similar = $this->findSimilarPackage($name, array_keys($catalog));
            $hint = $similar ? " Did you mean '$similar'?" : "";
            throw new RuntimeException("Package '$name' not found in official catalog.$hint");
        }

        $pkgMeta = $catalog[$name];
        $targetVersion = $version ?? $pkgMeta['latest'] ?? '1.0.0';

        // Check Bongo core compatibility
        $versions = $pkgMeta['versions'] ?? [];
        $verMeta = $versions[$targetVersion] ?? null;
        if ($verMeta === null && !empty($versions)) {
            $verMeta = reset($versions);
            $targetVersion = key($versions);
        }

        if ($verMeta !== null && isset($verMeta['requires']['bongo'])) {
            $this->checkCoreCompatibility($verMeta['requires']['bongo']);
        }

        $destDir = $this->basePath . '/packages/' . $name;
        $backupPath = null;

        // Check if existing installation was edited
        if (is_dir($destDir) && !$force) {
            $diff = $this->diff($name);
            if (!empty($diff['modified']) || !empty($diff['deleted'])) {
                $count = count($diff['modified']) + count($diff['deleted']);
                if ($backup) {
                    $backupPath = $this->backup($name);
                } else {
                    $changed = array_merge($diff['modified'], $diff['deleted']);
                    $filesSummary = implode(', ', array_slice($changed, 0, 3)) . (count($changed) > 3 ? '...' : '');
                    throw new RuntimeException(
                        "You changed $count file(s) in packages/$name: $filesSummary.\n" .
                        "Update stopped. Use --backup (save to storage/package-backups/$name-<date>/ then update) " .
                        "or --force (discard). Tip: move changes to app/ or publish the views."
                    );
                }
            }
        }

        // Copy from local repository or download
        $localPath = $this->localPackagesPath();
        $fileMap = [];

        if ($localPath !== null && is_dir($localPath . '/' . $name)) {
            $fileMap = $this->copyDirectorySafe($localPath . '/' . $name, $destDir);
        } else {
            $fileMap = $this->downloadAndExtract($name, $targetVersion, $destDir);
        }

        // Update lockfile
        $installed = PackageManager::installed();
        $installed[$name] = [
            'version' => $targetVersion,
            'description' => $pkgMeta['description'] ?? '',
            'files' => $fileMap,
            'installed_at' => date('c'),
        ];
        PackageManager::writeLock($installed);

        // Auto-publish config if missing
        $configCreated = false;
        $pkgConfig = $destDir . '/config/' . $name . '.php';
        $appConfig = $this->basePath . '/config/' . $name . '.php';
        if (is_file($pkgConfig) && !is_file($appConfig)) {
            if (!is_dir(dirname($appConfig))) {
                mkdir(dirname($appConfig), 0775, true);
            }
            copy($pkgConfig, $appConfig);
            $configCreated = true;
        }

        // Discover migrations and environment keys
        $migrations = glob($destDir . '/database/migrations/*.php') ?: [];
        $envKeys = $this->extractEnvKeys($destDir);

        return [
            'name' => $name,
            'version' => $targetVersion,
            'backup' => $backupPath,
            'config_created' => $configCreated,
            'env_keys' => $envKeys,
            'migrations' => array_map('basename', $migrations),
            'readme' => is_file($destDir . '/README.md') ? "packages/$name/README.md" : null,
        ];
    }

    /**
     * Install all packages configured in config/packages.php.
     */
    public function installAll(bool $force = false, bool $backup = false): array
    {
        $configFile = $this->basePath . '/config/packages.php';
        if (!is_file($configFile)) {
            return [];
        }
        $configured = require $configFile;
        if (!is_array($configured)) {
            return [];
        }

        $results = [];
        foreach ($configured as $key => $val) {
            $name = is_int($key) ? (string) $val : (string) $key;
            $version = is_int($key) ? null : (string) $val;
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            $results[$name] = $this->install($name, $version, $force, $backup);
        }
        return $results;
    }

    /**
     * Update an installed package or all installed packages.
     */
    public function update(?string $name = null, bool $force = false, bool $backup = false): array
    {
        if ($name !== null) {
            $name = strtolower(trim($name));
            if (!PackageManager::isInstalled($name)) {
                throw new RuntimeException("Package '$name' is not installed. Use 'php bongo add $name' to install it.");
            }
            $catalog = $this->catalog();
            $latest = $catalog[$name]['latest'] ?? null;
            $current = PackageManager::installed()[$name]['version'] ?? null;
            if ($latest && $current && version_compare($current, $latest, '>=') && !$force) {
                return ['name' => $name, 'status' => 'up_to_date', 'version' => $current];
            }
            $res = $this->install($name, $latest, $force, $backup);
            $res['status'] = 'updated';
            $res['old_version'] = $current;
            return $res;
        }

        $installed = PackageManager::installed();
        $results = [];
        foreach (array_keys($installed) as $pkg) {
            $results[$pkg] = $this->update($pkg, $force, $backup);
        }
        return $results;
    }

    /**
     * Backup an installed package directory to storage/package-backups/<name>-<date>.
     */
    public function backup(string $name): string
    {
        $name = strtolower(trim($name));
        $src = $this->basePath . '/packages/' . $name;
        if (!is_dir($src)) {
            throw new RuntimeException("Cannot backup packages/$name: directory does not exist.");
        }
        $dest = $this->basePath . '/storage/package-backups/' . $name . '-' . date('Ymd-His');
        $this->copyTreeSafe($src, $dest);
        return $dest;
    }

    /**
     * Add a package: enable in config/packages.php and install it.
     */
    public function add(string $name, ?string $version = null, bool $force = false, bool $backup = false): array
    {
        $this->enableInConfig($name, $version);
        return $this->install($name, $version, $force, $backup);
    }

    /**
     * Remove an installed package.
     */
    public function remove(string $name, bool $force = false): array
    {
        $name = strtolower(trim($name));
        $destDir = $this->basePath . '/packages/' . $name;

        if (!is_dir($destDir) && !PackageManager::isInstalled($name)) {
            throw new RuntimeException("Package '$name' is not installed.");
        }

        if (is_dir($destDir) && !$force) {
            $diff = $this->diff($name);
            if (!empty($diff['modified'])) {
                $count = count($diff['modified']);
                throw new RuntimeException("You modified $count file(s) in packages/$name. Use --force to delete anyway.");
            }
        }

        $migrations = is_dir($destDir . '/database/migrations')
            ? glob($destDir . '/database/migrations/*.php') ?: []
            : [];

        // Delete package folder
        if (is_dir($destDir)) {
            $this->deleteTree($destDir);
        }

        // Update lockfile
        $installed = PackageManager::installed();
        unset($installed[$name]);
        PackageManager::writeLock($installed);

        // Comment out in config/packages.php
        $this->disableInConfig($name);

        return [
            'name' => $name,
            'migrations' => array_map('basename', $migrations),
        ];
    }

    /**
     * Check for differences between installed files and lockfile checksums.
     */
    public function diff(string $name): array
    {
        $installed = PackageManager::installed();
        if (!isset($installed[$name]['files'])) {
            return ['modified' => [], 'added' => [], 'deleted' => []];
        }

        $expected = $installed[$name]['files'];
        $pkgDir = $this->basePath . '/packages/' . $name;

        $modified = [];
        $deleted = [];
        $added = [];

        // Check expected files
        foreach ($expected as $relPath => $expectedHash) {
            $fullPath = $pkgDir . '/' . $relPath;
            if (!is_file($fullPath)) {
                $deleted[] = $relPath;
            } elseif (hash_file('sha256', $fullPath) !== $expectedHash) {
                $modified[] = $relPath;
            }
        }

        // Check for untracked added files
        if (is_dir($pkgDir)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pkgDir, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile()) {
                    $rel = substr(str_replace('\\', '/', $file->getPathname()), strlen(str_replace('\\', '/', $pkgDir)) + 1);
                    if (!isset($expected[$rel])) {
                        $added[] = $rel;
                    }
                }
            }
        }

        return ['modified' => $modified, 'added' => $added, 'deleted' => $deleted];
    }

    /**
     * Publish customisable package files into the application.
     */
    public function publish(string $name, array $tags = []): array
    {
        $pkgDir = $this->basePath . '/packages/' . $name;
        if (!is_dir($pkgDir)) {
            throw new RuntimeException("Package '$name' is not installed.");
        }

        $published = [];
        $all = empty($tags);

        // Publish config: packages/<name>/config/<name>.php -> config/<name>.php
        if ($all || in_array('config', $tags, true)) {
            $src = $pkgDir . '/config/' . $name . '.php';
            $dest = $this->basePath . '/config/' . $name . '.php';
            if (is_file($src)) {
                if (!is_dir(dirname($dest))) {
                    mkdir(dirname($dest), 0775, true);
                }
                copy($src, $dest);
                $published[] = "config/$name.php";
            }
        }

        // Publish views: packages/<name>/views/ -> resources/views/vendor/<name>/
        if ($all || in_array('views', $tags, true)) {
            $src = $pkgDir . '/views';
            $dest = $this->basePath . '/resources/views/vendor/' . $name;
            if (is_dir($src)) {
                $this->copyTreeSafe($src, $dest);
                $published[] = "resources/views/vendor/$name/";
            }
        }

        // Publish lang: packages/<name>/lang/ -> lang/vendor/<name>/
        if ($all || in_array('lang', $tags, true)) {
            $src = $pkgDir . '/lang';
            $dest = $this->basePath . '/lang/vendor/' . $name;
            if (is_dir($src)) {
                $this->copyTreeSafe($src, $dest);
                $published[] = "lang/vendor/$name/";
            }
        }

        return $published;
    }

    /**
     * Copy directory safely with path traversal and symlink prevention.
     */
    protected function copyDirectorySafe(string $from, string $to): array
    {
        if (!is_dir($to)) {
            mkdir($to, 0775, true);
        }

        $fileMap = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS));

        foreach ($it as $item) {
            $rel = substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $from)) + 1);

            // Path traversal defense
            if (str_contains($rel, '..') || str_starts_with($rel, '/') || str_starts_with($rel, '\\')) {
                throw new RuntimeException("Illegal path traversal detected in package: $rel");
            }
            if ($item->isLink()) {
                continue; // Skip symlinks for safety
            }

            $dest = $to . '/' . $rel;
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0775, true);
            }

            copy($item->getPathname(), $dest);
            $fileMap[$rel] = hash_file('sha256', $dest);
        }

        return $fileMap;
    }

    protected function copyTreeSafe(string $from, string $to): void
    {
        if (!is_dir($to)) {
            mkdir($to, 0775, true);
        }

        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($from, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $item) {
            if ($item->isLink()) {
                continue;
            }
            $rel = substr(str_replace('\\', '/', $item->getPathname()), strlen(str_replace('\\', '/', $from)) + 1);
            $dest = $to . '/' . $rel;
            if (!is_dir(dirname($dest))) {
                mkdir(dirname($dest), 0775, true);
            }
            copy($item->getPathname(), $dest);
        }
    }

    protected function deleteTree(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }

    /**
     * Download package from remote GitHub repository.
     */
    protected function downloadAndExtract(string $name, string $version, string $destDir): array
    {
        $url = "https://api.github.com/repos/Solayman/bongo-php-packages/contents/$name?ref=master";
        $token = $this->resolveToken();

        $headers = [
            "User-Agent: Bongo-Package-Manager/" . Framework::version(),
            "Accept: application/vnd.github.v3+json",
        ];
        if ($token !== '') {
            $headers[] = "Authorization: token $token";
        }

        // Recursively fetch file tree via GitHub API
        $fileMap = [];
        $this->downloadGithubFolder($name, '', $destDir, $headers, $fileMap);

        return $fileMap;
    }

    protected function downloadGithubFolder(string $pkg, string $subPath, string $destDir, array $headers, array &$fileMap): void
    {
        $apiPath = "https://api.github.com/repos/Solayman/bongo-php-packages/contents/{$pkg}" . ($subPath !== '' ? "/{$subPath}" : "") . "?ref=master";

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $body = @file_get_contents($apiPath, false, $context);
        if ($body === false) {
            throw new RuntimeException("Failed downloading package files for '$pkg'. Check permissions or token.");
        }

        $items = json_decode($body, true);
        if (!is_array($items)) {
            throw new RuntimeException("Unexpected response downloading package '$pkg'.");
        }

        foreach ($items as $item) {
            $type = $item['type'] ?? '';
            $itemPath = $item['path'] ?? '';
            // Strip package name prefix
            $relPath = preg_replace("#^" . preg_quote($pkg, '#') . "/?#", '', $itemPath);

            if ($type === 'dir') {
                $this->downloadGithubFolder($pkg, $relPath, $destDir, $headers, $fileMap);
            } elseif ($type === 'file' && isset($item['download_url'])) {
                $targetFile = $destDir . '/' . $relPath;
                if (!is_dir(dirname($targetFile))) {
                    mkdir(dirname($targetFile), 0775, true);
                }

                $fileContent = @file_get_contents($item['download_url'], false, $context);
                if ($fileContent === false) {
                    throw new RuntimeException("Could not download file: {$item['name']}");
                }

                file_put_contents($targetFile, $fileContent);
                $fileMap[$relPath] = hash_file('sha256', $targetFile);
            }
        }
    }

    protected function extractEnvKeys(string $destDir): array
    {
        $keys = [];
        $readme = $destDir . '/README.md';

        if (is_file($readme)) {
            $content = (string) file_get_contents($readme);
            if (preg_match_all('/^[A-Z0-9_]{3,}=/m', $content, $matches)) {
                $keys = array_unique($matches[0]);
            }
        }

        return $keys;
    }

    protected function checkCoreCompatibility(string $constraint): void
    {
        $current = Framework::version();
        // Remove simple operators for version check
        $required = ltrim($constraint, '^~>=< ');

        if (version_compare($current, $required, '<')) {
            throw new RuntimeException("Package requires Bongo $constraint, but you have $current. Run: php bongo upgrade");
        }
    }

    protected function findSimilarPackage(string $name, array $available): ?string
    {
        $best = null;
        $bestDist = 4;

        foreach ($available as $candidate) {
            $dist = levenshtein($name, $candidate);
            if ($dist < $bestDist) {
                $bestDist = $dist;
                $best = $candidate;
            }
        }

        return $best;
    }

    protected function localPackagesPath(): ?string
    {
        $env = env('BONGO_PACKAGES_PATH', config('packages.path'));
        if (!$env) {
            // Check default sibling folder if present
            $sibling = realpath($this->basePath . '/../bongo-php-packages');
            return ($sibling && is_dir($sibling)) ? $sibling : null;
        }

        $resolved = str_starts_with($env, '/') || (strlen($env) > 2 && $env[1] === ':')
            ? $env
            : $this->basePath . '/' . $env;

        $real = realpath($resolved);
        return ($real && is_dir($real)) ? $real : null;
    }

    protected function resolveToken(): string
    {
        return (string) (env('BONGO_PACKAGES_TOKEN') ?: env('GITHUB_TOKEN') ?: '');
    }

    protected function enableInConfig(string $name, ?string $version = null): void
    {
        $file = $this->basePath . '/config/packages.php';
        if (!is_file($file)) {
            return;
        }

        $content = (string) file_get_contents($file);
        $entry = $version ? "'$name' => '$version'" : "'$name'";

        // 1. Uncomment if commented
        if (preg_match("#//\s*['\"]" . preg_quote($name, '#') . "['\"].*#", $content)) {
            $content = preg_replace("#//\s*(['\"]" . preg_quote($name, '#') . "['\"][^,\n]*),?#", "$entry,", $content, 1);
            file_put_contents($file, $content);
            return;
        }

        // 2. If not in file, append before closing ];
        if (!str_contains($content, "'$name'")) {
            $content = preg_replace("/(\];\s*)$/", "    $entry,\n$1", $content);
            file_put_contents($file, $content);
        }
    }

    protected function disableInConfig(string $name): void
    {
        $file = $this->basePath . '/config/packages.php';
        if (!is_file($file)) {
            return;
        }

        $content = (string) file_get_contents($file);
        $pattern = "#^\s*(['\"]" . preg_quote($name, '#') . "['\"][^,\n]*,?)\s*$#m";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, "    // $1", $content);
            file_put_contents($file, $content);
        }
    }
}
