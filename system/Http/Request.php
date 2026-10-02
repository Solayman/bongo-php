<?php
namespace System\Http;

class Request
{
    public static function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    /**
     * Request path relative to APP_URL, always starting with "/"
     */
    public static function path(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $base = rtrim(parse_url(config('app.url', ''), PHP_URL_PATH) ?? '', '/');

        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        return '/' . trim($uri, '/');
    }

    /**
     * All input ($_GET merged with $_POST), strings trimmed
     */
    public static function all(): array
    {
        return array_map(
            fn($v) => is_string($v) ? trim($v) : $v,
            array_merge($_GET, $_POST)
        );
    }

    public static function input(string $key, $default = null)
    {
        $value = self::all()[$key] ?? $default;
        return $value === '' ? $default : $value;
    }

    public static function only(array $keys): array
    {
        $all = self::all();
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $all[$key] ?? null;
        }
        return $result;
    }

    public static function query(string $key, $default = null)
    {
        $value = isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : null;
        return ($value === null || $value === '') ? $default : $value;
    }
}
