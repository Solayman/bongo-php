<?php

namespace System\Http;

/**
 * Maintenance mode: php bongo down / php bongo up.
 * While down every request gets 503 "We'll be back soon" (JSON for APIs), except a browser
 * that visited the secret URL once (php bongo down --secret=let-me-in → /let-me-in).
 */
class Maintenance
{
    public const COOKIE = 'bongo_maintenance';

    public static function file(): string
    {
        return BASE_PATH . '/storage/framework/down.json';
    }

    public static function isDown(): bool
    {
        return is_file(self::file());
    }

    /** ['time', 'message', 'retry', 'secret'] or null */
    public static function info(): ?array
    {
        return self::isDown() ? (json_decode((string) file_get_contents(self::file()), true) ?: []) : null;
    }

    public static function down(string $message = '', int $retry = 60, ?string $secret = null): void
    {
        if (!is_dir(dirname(self::file()))) {
            mkdir(dirname(self::file()), 0775, true);
        }
        file_put_contents(self::file(), json_encode([
            'time' => time(), 'message' => $message, 'retry' => max(0, $retry),
            'secret' => $secret === null || $secret === '' ? null : trim($secret, '/'),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public static function up(): bool
    {
        return self::isDown() && @unlink(self::file());
    }

    /** Called first by public/index.php: stops the request with the 503 page while down */
    public static function handle(): void
    {
        $info = self::info();
        if ($info === null) {
            return;
        }
        $secret = $info['secret'] ?? null;
        if ($secret) {
            $token = hash_hmac('sha256', $secret, (string) ($info['time'] ?? ''));
            if (trim(Request::path(), '/') === $secret) {
                setcookie(self::COOKIE, $token, ['expires' => time() + 12 * 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => Request::isSecure()]);
                header('Location: ' . url('/'));
                http_response_code(302);
                exit;
            }
            if (hash_equals($token, (string) ($_COOKIE[self::COOKIE] ?? ''))) {
                return;   // the developer's browser keeps working
            }
        }
        self::render($info);
    }

    protected static function render(array $info): void
    {
        http_response_code(503);
        if (!empty($info['retry'])) {
            header('Retry-After: ' . (int) $info['retry']);
        }
        $message = (string) ($info['message'] ?? '') ?: 'We are doing some maintenance. Please check back soon.';

        if (Request::wantsJson()) {
            header('Content-Type: application/json');
            echo json_encode(['message' => $message, 'retry_after' => (int) ($info['retry'] ?? 0)]);
            exit;
        }
        if (function_exists('view') && is_file(BASE_PATH . '/resources/views/errors/503.php')) {
            echo view('errors.503', ['message' => $message]);
            exit;
        }
        $e = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Back soon</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f4f5f7;color:#1f2328;'
            . 'font:16px/1.6 system-ui,sans-serif}@media (prefers-color-scheme:dark){body{background:#0d1117;color:#e6edf3}}main{max-width:480px;padding:24px;text-align:center}'
            . 'h1{font-size:26px;margin:0 0 8px}p{color:#57606a;margin:0}</style></head><body><main><h1>We\'ll be back soon</h1><p>' . $e . '</p></main></body></html>';
        exit;
    }
}
