<?php
namespace System\Http;

class Session
{
    /**
     * Start the session and age flash data (flashed values live for one request)
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'httponly' => true,
                'samesite' => 'Lax',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
            session_start();
        }

        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    public static function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Store a value for the next request only
     */
    public static function flash(string $key, $value): void
    {
        $_SESSION['_flash_next'][$key] = $value;
    }

    /**
     * Read a value flashed by the previous request
     */
    public static function getFlash(string $key, $default = null)
    {
        return $_SESSION['_flash_now'][$key] ?? $default;
    }

    /**
     * New session id (call on login/logout to prevent session fixation)
     */
    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function token(): string
    {
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }

    public static function regenerateToken(): void
    {
        $_SESSION['_token'] = bin2hex(random_bytes(32));
    }
}
