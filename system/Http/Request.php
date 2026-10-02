<?php
namespace System\Http;

class Request
{
    /** Parameters of the matched route, e.g. ['product' => '20'] for /products/{product} */
    protected static array $routeParameters = [];

    /** Set by the router before the action runs */
    public static function setRouteParameters(array $parameters): void
    {
        self::$routeParameters = $parameters;
    }

    /** A route parameter: Request::route('product') on /products/20 → "20" (all of them without a key) */
    public static function route(?string $key = null, $default = null)
    {
        return $key === null ? self::$routeParameters : (self::$routeParameters[$key] ?? $default);
    }

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

    /** One uploaded file, or null: Request::file('photo'). For <input name="photos[]" multiple> use files('photos'). */
    public static function file(string $key): ?UploadedFile
    {
        $file = self::normalizeUpload($_FILES[$key] ?? null);
        return $file instanceof UploadedFile ? $file : null;
    }

    public static function hasFile(string $key): bool
    {
        return self::files($key) !== [];
    }

    /**
     * Uploads. With a key: the files of that field as a list (photos[] → [UploadedFile, ...];
     * a single-file field gives a list of one). Without: every field, [field => UploadedFile | list].
     */
    public static function files(?string $key = null): array
    {
        if ($key !== null) {
            $files = self::normalizeUpload($_FILES[$key] ?? null);
            return $files instanceof UploadedFile ? [$files] : ($files ?? []);
        }

        $all = [];
        foreach ($_FILES as $field => $spec) {
            $files = self::normalizeUpload($spec);
            if ($files !== null) {
                $all[$field] = $files;
            }
        }
        return $all;
    }

    /**
     * $_FILES keeps lists "inside out" (name[0], type[0] ...); turn them into UploadedFile objects.
     * Fields left empty (UPLOAD_ERR_NO_FILE: nothing was chosen) are skipped.
     */
    protected static function normalizeUpload($spec): UploadedFile|array|null
    {
        if (!is_array($spec) || !array_key_exists('error', $spec)) {
            return null;
        }
        if (!is_array($spec['error'])) {
            return (int) $spec['error'] === UPLOAD_ERR_NO_FILE ? null : UploadedFile::fromArray($spec);
        }

        $list = [];
        foreach (array_keys($spec['error']) as $index) {
            $item = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size', 'full_path'] as $part) {
                if (isset($spec[$part]) && is_array($spec[$part]) && array_key_exists($index, $spec[$part])) {
                    $item[$part] = $spec[$part][$index];
                }
            }
            if (($file = self::normalizeUpload($item)) !== null) {
                $list[$index] = $file;
            }
        }
        return $list ?: null;
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
