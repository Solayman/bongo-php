<?php

/**
 * OpCache preloading (Linux/macOS production servers; PHP does not support it on Windows).
 *
 * Compiles the framework once when PHP-FPM starts, so requests skip loading those files.
 * In php.ini (restart PHP-FPM after deploys):
 *
 *   opcache.enable=1
 *   opcache.preload=/var/www/your-app/system/preload.php
 *   opcache.preload_user=www-data
 *
 * Only system/ is compiled (your app/ code changes often and still loads normally).
 * Console, testing and the CLI-only parts are skipped: web requests never use them.
 */

if (!function_exists('opcache_compile_file')) {
    return;
}

$skip = ['/Console/', '/Testing/', '/Broadcasting/Server.php', '/preload.php'];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    $path = str_replace('\\', '/', $file->getPathname());
    if ($file->getExtension() !== 'php') {
        continue;
    }
    foreach ($skip as $part) {
        if (str_contains($path, $part)) {
            continue 2;
        }
    }
    @opcache_compile_file($path);   // a class whose parent is not compiled yet is linked on first use
}
