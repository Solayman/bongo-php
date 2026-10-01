<?php
// Framework helpers (loaded by config/bootstrap.php)

use System\Auth\Auth;
use System\Http\HttpException;
use System\Http\Request;
use System\Http\Session;
use System\Log\Logger;

if (!function_exists('config')) {
    function config($key, $default = null) {
        $value = $GLOBALS['config'] ?? [];
        foreach (explode('.', $key) as $k) {
            if (is_array($value) && array_key_exists($k, $value)) $value = $value[$k];
            else return $default;
        }
        return $value;
    }
}

if (!function_exists('base_path')) {
    function base_path($path = '') {
        return BASE_PATH . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('url')) {
    /** Absolute URL for a path inside the app: url('/products') */
    function url($path = '/') {
        return rtrim(config('app.url', ''), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    /**
     * URL of a named route: route('products.edit', ['id' => 5]).
     * A path still works too: route('/products').
     */
    function route(string $nameOrPath, array $params = []) {
        $router = \System\Routing\Router::current();
        if (!str_starts_with($nameOrPath, '/') && $router && $router->has($nameOrPath)) {
            return url($router->url($nameOrPath, $params));
        }
        return url($nameOrPath);
    }
}

if (!function_exists('asset')) {
    function asset($path) {
        return url($path);
    }
}

if (!function_exists('e')) {
    /** HTML-escape a value */
    function e($value) {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('request')) {
    /** request() -> all input, request('key', $default) -> one value */
    function request($key = null, $default = null) {
        return $key === null ? Request::all() : Request::input($key, $default);
    }
}

if (!function_exists('session')) {
    function session($key = null, $default = null) {
        return $key === null ? ($_SESSION ?? []) : Session::get($key, $default);
    }
}

if (!function_exists('flash')) {
    /** flash('success', 'Saved') sets a message; flash('success') reads one */
    function flash($key, $value = null) {
        if (func_num_args() === 1) return Session::getFlash($key);
        Session::flash($key, $value);
        return null;
    }
}

if (!function_exists('old')) {
    /** Previous form input after a failed validation */
    function old($key, $default = '') {
        $old = Session::getFlash('_old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    function errors() {
        return Session::getFlash('_errors', []);
    }
}

if (!function_exists('error')) {
    /** First validation error for a field, or null */
    function error($field) {
        return errors()[$field] ?? null;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        return Session::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    /** Hidden _method field so an HTML form can send PUT, PATCH or DELETE */
    function method_field(string $method) {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('auth')) {
    /** Currently logged in user (stdClass) or null */
    function auth() {
        return Auth::user();
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = '') {
        throw new HttpException($status, $message);
    }
}

if (!function_exists('redirect')) {
    function redirect($url) {
        header('Location: ' . $url, true, 302);
        exit;
    }
}

if (!function_exists('back')) {
    /** Redirect to the previous page: last GET page in the session, else a same-host Referer, else "/" */
    function back() {
        $previous = Session::get('_previous_url');
        if ($previous) {
            redirect(url($previous));
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = parse_url(config('app.url', ''), PHP_URL_HOST);
        redirect(($referer && parse_url($referer, PHP_URL_HOST) === $host) ? $referer : url('/'));
    }
}

if (!function_exists('response')) {
    function response($data, $type = 'json', int $status = 200) {
        http_response_code($status);
        switch ($type) {
            case 'json':
                header('Content-Type: application/json');
                echo json_encode($data);
                break;
            case 'html':
                header('Content-Type: text/html');
                echo $data;
                break;
        }
        exit;
    }
}

if (!function_exists('logger')) {
    /** logger() -> the Logger; logger('message', $context) -> write a debug line */
    function logger(?string $message = null, array $context = []) {
        if ($message === null) return Logger::instance();
        Logger::instance()->debug($message, $context);
        return null;
    }
}

if (!function_exists('dump')) {
    /**
     * Show values and keep going. HTML in the browser, text in the terminal,
     * JSON for API requests. Returns the first value, so it can wrap expressions.
     */
    function dump(...$values) {
        $dumper = new \System\Debug\Dumper();
        $at = \System\Debug\Dumper::caller();
        switch (\System\Debug\Dumper::format()) {
            case 'text':
                echo "\u{25B6} $at\n";
                foreach ($values as $value) echo $dumper->text($value);
                break;
            case 'json':
                echo json_encode(['dump' => array_map([$dumper, 'plain'], $values), 'at' => $at], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
                break;
            default:
                echo \System\Debug\Dumper::styles();
                echo '<div class="bongo-dump-src">' . htmlspecialchars($at) . '</div>';
                foreach ($values as $value) echo $dumper->html($value);
        }
        return $values[0] ?? null;
    }
}

if (!function_exists('dd')) {
    /** Dump the values and stop the request ("dump and die") */
    function dd(...$values) {
        $dumper = new \System\Debug\Dumper();
        $at = \System\Debug\Dumper::caller();
        $format = \System\Debug\Dumper::format();

        if ($format === 'text') {
            echo "\u{25B6} $at\n";
            foreach ($values as $value) echo $dumper->text($value);
            exit(1);
        }

        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) http_response_code(500);

        if ($format === 'json') {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['dd' => array_map([$dumper, 'plain'], $values), 'at' => $at], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            exit;
        }

        if (!headers_sent()) header('Content-Type: text/html; charset=UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>dd() · ' . htmlspecialchars($at) . '</title>'
            . \System\Debug\Dumper::styles()
            . '<style>body{background:#11121a;margin:0;padding:24px;font-family:system-ui,sans-serif}h1{color:#cdd6f4;font-size:15px;font-weight:500}h1 code{color:#89b4fa}</style>'
            . '</head><body><h1>dd() called at <code>' . htmlspecialchars($at) . '</code></h1>';
        foreach ($values as $value) echo $dumper->html($value);
        echo '</body></html>';
        exit;
    }
}

if (!function_exists('money')) {
    function money($amount) {
        return '$' . number_format((float) $amount, 2);
    }
}

if (!function_exists('is_active')) {
    /** 'active' when the current path starts with $prefix (for nav links) */
    function is_active(string $prefix) {
        $path = Request::path();
        return ($prefix === '/' ? $path === '/' : str_starts_with($path, $prefix)) ? 'active' : '';
    }
}
