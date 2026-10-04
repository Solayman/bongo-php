<?php

namespace System;

/**
 * Facts about the installed framework: its version (system/VERSION) and size.
 */
class Framework
{
    /** "0.9.0" (semantic versioning: a change in the first number means read UPGRADE.md) */
    public static function version(): string
    {
        $file = __DIR__ . '/VERSION';
        return is_file($file) ? trim((string) file_get_contents($file)) : 'unknown';
    }

    /** Files, lines and KB of the framework code in system/ */
    public static function size(): array
    {
        $files = 0;
        $lines = 0;
        $bytes = 0;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $files++;
            $bytes += $file->getSize();
            $lines += substr_count((string) file_get_contents($file->getPathname()), "\n");
        }
        return ['files' => $files, 'lines' => $lines, 'kb' => (int) round($bytes / 1024)];
    }
}
