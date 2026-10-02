<?php
namespace System\Http;

class Request
{
    /**
     * HTTP method. HTML forms can only send GET/POST, so a POST may carry
     * _method=PUT|PATCH|DELETE (or the X-HTTP-Method-Override header).
     */
    public static function method(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = strtoupper((string) ($_POST['_method'] ?? $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? ''));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    /**
     * The client's IP address. X-Forwarded-For is only believed when the request
     * comes from a trusted proxy (config security.trusted_proxies), so clients
     * cannot fake their IP to dodge rate limits or login lockouts.
     */
    public static function ip(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (!self::fromTrustedProxy($remote)) {
            return $remote;
        }

        $forwarded = array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')));
        // Walk from the closest hop back; the first address that is not a trusted proxy is the client
        for ($i = count($forwarded) - 1; $i >= 0; $i--) {
            $candidate = $forwarded[$i];
            if (filter_var($candidate, FILTER_VALIDATE_IP) && !self::fromTrustedProxy($candidate)) {
                return $candidate;
            }
        }
        return $remote;
    }

    /** HTTPS, directly or (from a trusted proxy) via X-Forwarded-Proto: https */
    public static function isSecure(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }
        return self::fromTrustedProxy((string) ($_SERVER['REMOTE_ADDR'] ?? ''))
            && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    protected static function fromTrustedProxy(string $ip): bool
    {
        $trusted = (array) config('security.trusted_proxies', []);
        return $ip !== '' && (in_array('*', $trusted, true) || in_array($ip, $trusted, true));
    }

    /** The method the client really sent, ignoring _method */
    public static function realMethod(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public static function header(string $name, ?string $default = null): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? ($name === 'Content-Type' ? ($_SERVER['CONTENT_TYPE'] ?? $default) : $default);
    }

    /** True when the client wants JSON (Accept header, an AJAX call, or a JSON body) */
    public static function wantsJson(): bool
    {
        $accept = (string) self::header('Accept', '');
        return str_contains($accept, '/json') || str_contains($accept, '+json')
            || self::header('X-Requested-With') === 'XMLHttpRequest'
            || str_starts_with(self::path(), '/api/') || self::path() === '/api';
    }

    /** Decoded JSON body ([] if none or invalid) */
    public static function json(): array
    {
        static $json = null;
        if ($json === null) {
            $type = (string) self::header('Content-Type', '');
            $json = str_contains($type, 'json') ? (json_decode((string) file_get_contents('php://input'), true) ?: []) : [];
        }
        return $json;
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

    /** One uploaded file, or null: Request::file('photo') */
    public static function file(string $key): ?UploadedFile
    {
        $file = $_FILES[$key] ?? null;
        if (!is_array($file) || !isset($file['error']) || is_array($file['error'])) {
            return null;
        }
        return UploadedFile::fromArray($file);
    }

    public static function hasFile(string $key): bool
    {
        $file = self::file($key);
        return $file !== null && $file->error() !== UPLOAD_ERR_NO_FILE;
    }

    /** Every single-file upload: [field => UploadedFile] */
    public static function files(): array
    {
        $files = [];
        foreach (array_keys($_FILES) as $key) {
            if ($file = self::file((string) $key)) {
                $files[$key] = $file;
            }
        }
        return $files;
    }

    /** All input: query string, form fields and a JSON body; strings trimmed */
    public static function all(): array
    {
        $input = array_merge($_GET, $_POST, self::json());
        unset($input['_method']);
        return array_map(fn($v) => is_string($v) ? trim($v) : $v, $input);
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
