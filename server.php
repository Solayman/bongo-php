<?php

/**
 * Bongo PHP Framework - Built-in Server Router Script
 *
 * This file allows us to emulate Apache/Nginx rewrite rules when using
 * the PHP built-in development web server (`php bongo serve`).
 */

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
);

$publicDir = realpath(__DIR__ . '/public');
$targetPath = realpath(__DIR__ . '/public' . $uri);

// Only let PHP built-in server serve static files directly if:
// 1. $uri does not attempt directory traversal (no '..' or leading hidden segments '/.')
// 2. $targetPath exists and is strictly located inside public/
// 3. $targetPath is a file (not a directory)
if ($uri !== '/' && !str_contains($uri, '..') && !str_contains($uri, '/.') && $targetPath && $publicDir && str_starts_with($targetPath, $publicDir . DIRECTORY_SEPARATOR) && is_file($targetPath)) {
    return false;
}

require_once __DIR__ . '/public/index.php';
