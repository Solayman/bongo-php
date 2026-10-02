<?php
// Framework helpers (loaded by config/bootstrap.php)

use System\Auth\Auth;
use System\Http\HttpException;
use System\Http\Request;
use System\Http\Session;

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
    function route($path) {
        return url($path);
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
