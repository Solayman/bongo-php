<?php
// config/bootstrap.php
// Boots the framework: autoloaders, .env, config, helpers, session.

define('BASE_PATH', dirname(__DIR__));

// ---------------------------
// Autoloaders: App\ -> app/, System\ -> system/
// ---------------------------
spl_autoload_register(function ($class) {
    $map = [
        'App\\'    => BASE_PATH . '/app/',
        'System\\' => BASE_PATH . '/system/',
    ];
    foreach ($map as $prefix => $baseDir) {
        if (strncmp($prefix, $class, strlen($prefix)) === 0) {
            $file = $baseDir . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (file_exists($file)) {
                require_once $file;
            }
            return;
        }
    }
});

// Optional: Composer packages are loaded only if the user installed them
if (file_exists(BASE_PATH . '/vendor/autoload.php')) {
    require_once BASE_PATH . '/vendor/autoload.php';
}

// ---------------------------
// Load .env (real environment variables win, so tests can override them)
// ---------------------------
$envPath = BASE_PATH . '/.env';
if (file_exists($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;

        [$name, $value] = array_map('trim', explode('=', $line, 2));
        // Strip matching surrounding quotes: APP_NAME="My App" -> My App
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }

        if (getenv($name) !== false) {
            $_ENV[$name] = getenv($name);
            continue;
        }
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
        if ($lower === 'null') return null;

        return $value;
    }
}

// ---------------------------
// Helpers
// ---------------------------
require_once BASE_PATH . '/system/Support/helpers.php';
require_once BASE_PATH . '/system/View/helpers.php';
require_once BASE_PATH . '/app/Helpers/commonHelper.php';

// ---------------------------
// Load every config/*.php (except this file) into config('<file>.<key>')
// ---------------------------
$GLOBALS['config'] = [];
foreach (glob(__DIR__ . '/*.php') as $configFile) {
    $name = basename($configFile, '.php');
    if ($name !== 'bootstrap') {
        $GLOBALS['config'][$name] = require $configFile;
    }
}

// ---------------------------
// Timezone (PHP and, on connect, MySQL use the same one)
// ---------------------------
if (!@date_default_timezone_set((string) config('app.timezone', 'UTC'))) {
    date_default_timezone_set('UTC');
}

// ---------------------------
// Errors
// ---------------------------
error_reporting(E_ALL);
ini_set('display_errors', config('app.debug') ? '1' : '0');

// ---------------------------
// Log PHP warnings/notices and fatal errors through the framework logger
// ---------------------------
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    static $logging = false;
    if (!(error_reporting() & $severity) || $logging) {
        return false; // silenced with @, or raised while already logging
    }
    $logging = true;
    $level = in_array($severity, [E_WARNING, E_USER_WARNING, E_CORE_WARNING, E_COMPILE_WARNING], true) ? 'warning'
        : (in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true) ? 'error' : 'notice');
    try {
        System\Log\Logger::instance()->log($level, "PHP $message", ['file' => $file, 'line' => $line]);
    } finally {
        $logging = false;
    }
    return false; // let PHP continue with its normal handling (display in debug)
});

register_shutdown_function(function () {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        System\Log\Logger::instance()->critical('PHP fatal: ' . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
    }
});

// ---------------------------
// Session (web requests only)
// ---------------------------
if (PHP_SAPI !== 'cli') {
    System\Http\Session::start();
}
