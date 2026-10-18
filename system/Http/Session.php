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
            // Never accept a session id the server did not create (blocks session fixation),
            // and never put the id in URLs
            ini_set('session.use_strict_mode', '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.use_trans_sid', '0');
            ini_set('session.gc_maxlifetime', (string) self::idleSeconds());

            session_name((string) config('session.cookie', 'bongo_session'));
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'httponly' => true,
                'samesite' => (string) config('session.same_site', 'Lax'),
                'secure'   => Request::isSecure(),
            ]);
            if (config('session.driver', 'file') === 'redis') {
                session_set_save_handler(new RedisSessionHandler(self::idleSeconds()), true);
            }
            session_start();
        }

        self::enforceLifetime(time());

        $_SESSION['_flash_now'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    /** Signed-in users are logged out after this long without a request */
    protected static function idleSeconds(): int
    {
        return max(1, (int) config('session.idle_minutes', 120)) * 60;
    }

    /**
     * End sessions that were idle too long or are older than the absolute limit.
     * Returns true when the session was expired (and emptied).
     */
    public static function enforceLifetime(int $now): bool
    {
        $idle = self::idleSeconds();
        $absolute = max(1, (int) config('session.absolute_minutes', 720)) * 60;

        $last = (int) ($_SESSION['_last_activity'] ?? $now);
        $started = (int) ($_SESSION['_started_at'] ?? $now);
        $expired = ($now - $last) > $idle || ($now - $started) > $absolute;

        if ($expired) {
            $hadUser = (bool) array_filter(array_keys($_SESSION), fn($k) => str_starts_with((string) $k, 'auth_'));
            $_SESSION = [];
            self::regenerate();
            if ($hadUser && class_exists(\System\Log\Log::class)) {
                \System\Log\Log::info('Session expired (idle or too old); user signed out');
            }
            $started = $now;
        }

        $_SESSION['_last_activity'] = $now;
        $_SESSION['_started_at'] = $started;
        return $expired;
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
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
        if (empty($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_token'];
    }

    public static function regenerateToken(): void
    {
        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
        if (!empty($_SESSION['_token'])) {
            $window = (int) config('session.token_grace_window', 10);
            $_SESSION['_previous_tokens'][] = [
                'token'      => $_SESSION['_token'],
                'expires_at' => time() + $window,
            ];
            // Prune expired previous tokens
            $_SESSION['_previous_tokens'] = array_values(array_filter(
                $_SESSION['_previous_tokens'],
                fn($item) => ($item['expires_at'] ?? 0) >= time()
            ));
        }
        $_SESSION['_token'] = bin2hex(random_bytes(32));
    }

    public static function isValidToken(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }
        if (hash_equals(self::token(), $token)) {
            return true;
        }
        if (!empty($_SESSION['_previous_tokens']) && is_array($_SESSION['_previous_tokens'])) {
            $now = time();
            foreach ($_SESSION['_previous_tokens'] as $prev) {
                if (isset($prev['token'], $prev['expires_at']) && $prev['expires_at'] >= $now) {
                    if (hash_equals((string) $prev['token'], $token)) {
                        return true;
                    }
                }
            }
        }
        return false;
    }
}
