<?php

namespace System\Console;

/**
 * php bongo release:package: the upgrade package (.json.gz + .sha256) that
 * `php bongo upgrade` installs. Maintainer tool, not shipped with projects.
 */
class Packager extends Upgrader
{
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
                $config[basename($file)] = array_keys((array) (function () use ($file) {
                    return require $file;
                })());
            }
        }

        if (!is_dir($outDir)) {
            mkdir($outDir, 0775, true);
        }
        $path = rtrim($outDir, '/\\') . "/bongo-framework-$version.json.gz";
        file_put_contents($path, gzencode(json_encode(['version' => $version, 'files' => $files, 'config' => $config], JSON_UNESCAPED_SLASHES), 9));
        file_put_contents("$path.sha256", hash_file('sha256', $path) . '  ' . basename($path) . "\n");
        return $path;
    }
}
