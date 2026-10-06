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

// If the file exists in the public directory and is not a directory, let PHP serve it directly.
if ($uri !== '/' && file_exists(__DIR__ . '/public' . $uri) && !is_dir(__DIR__ . '/public' . $uri)) {
    return false;
}

require_once __DIR__ . '/public/index.php';
