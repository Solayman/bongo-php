<?php
// config/bootstrap.php

// ---------------------------
// Load .env manually
// ---------------------------

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require_once $file;
});

require_once __DIR__ . '/../app/Helpers/commonHelper.php';
// Autoload custom system
spl_autoload_register(function ($class) {
    $prefix = 'System\\';
    $baseDir = __DIR__ . '/../system/';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

require_once __DIR__ . '/../system/View/helpers.php';


$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        [$name, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$name] = $value;
        putenv("$name=$value");
    }
}

// ---------------------------
// env() helper
// ---------------------------
if (!function_exists('env')) {
    function env($key, $default = null) {
        $value = $_ENV[$key] ?? getenv($key);
        if ($value === false || $value === null) return $default;

        $lower = strtolower($value);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;

        return $value;
    }
}

// ---------------------------
// Load config files
// ---------------------------
$GLOBALS['config'] = [
    'app' => require __DIR__ . '/app.php',
    'database' => require __DIR__ . '/database.php',
];

// ---------------------------
// config() helper
// ---------------------------
if (!function_exists('config')) {
    function config($key, $default = null) {
        $keys = explode('.', $key);
        $value = $GLOBALS['config'];
        foreach ($keys as $k) {
            if (isset($value[$k])) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        return $value;
    }
}

// ---------------------------
// route() helper (optional)
// ---------------------------
if (!function_exists('route')) {
    function route($path) {
        $base = rtrim(config('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

