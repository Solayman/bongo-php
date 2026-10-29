<?php

namespace System\Security;

/**
 * Deployment checklist used by `php bongo security:check` and the System status page.
 * Each result: ['check' => label, 'status' => ok|warn|fail, 'detail' => what to do].
 */
class SecurityCheck
{
    public static function run(): array
    {
        $production = config('app.env') === 'production';
        $results = [];
        $add = function (string $check, string $status, string $detail) use (&$results) {
            $results[] = compact('check', 'status', 'detail');
        };

        // Database schema
        $pending = \System\Database\MigrationHint::pending();
        if ($pending) {
            $add('Database migrations', 'fail', count($pending) . ' pending: ' . implode(', ', $pending) . '. Run: php bongo migrate');
        } else {
            $add('Database migrations', 'ok', 'All migrations have run.');
        }

        // Debug mode
        if (config('app.debug')) {
            $add('Debug mode', $production ? 'fail' : 'warn', $production
                ? 'APP_DEBUG is on in production: visitors can see source code and stack traces. Set APP_DEBUG=false.'
                : 'APP_DEBUG is on (fine for development). Turn it off on a live site.');
        } else {
            $add('Debug mode', 'ok', 'Off: visitors see plain error pages.');
        }

        // Keys
        try {
            Key::get();
            $add('APP_KEY', 'ok', 'Set (used for password pepper, encryption and JWT).');
        } catch (\Throwable $e) {
            $add('APP_KEY', 'fail', 'Missing or too short. Run: php bongo key:generate');
        }
        $jwtSecret = (string) env('JWT_SECRET', '');
        if ($jwtSecret !== '' && strlen($jwtSecret) < 32) {
            $add('JWT_SECRET', 'fail', 'Shorter than 32 characters: tokens could be brute-forced. Use a long random value or leave it empty.');
        }

        // HTTPS
        $url = (string) config('app.url', '');
        if (str_starts_with($url, 'https://')) {
            $add('HTTPS', 'ok', 'APP_URL uses https.');
        } else {
            $add('HTTPS', $production ? 'fail' : 'warn', 'APP_URL is not https. Passwords and session cookies travel unencrypted. Use HTTPS on a live site.');
        }

        // Proxies
        $proxies = (array) config('security.trusted_proxies', []);
        if (in_array('*', $proxies, true)) {
            $add('Trusted proxies', 'warn', 'TRUSTED_PROXIES=* trusts X-Forwarded-For from anyone. List your proxy IPs instead, unless only your proxy can reach the app.');
        } else {
            $add('Trusted proxies', 'ok', $proxies ? 'Only ' . implode(', ', $proxies) . '.' : 'None (direct connections).');
        }

        // Session
        $idle = (int) config('session.idle_minutes', 120);
        $add('Session timeout', $idle > 24 * 60 ? 'warn' : 'ok', "Signed-in users are logged out after $idle idle minutes.");

        // Content-Security-Policy
        $add(
            'Content-Security-Policy',
            trim((string) config('security.csp', '')) === '' ? 'warn' : 'ok',
            trim((string) config('security.csp', '')) === '' ? 'Not set. Consider SECURITY_CSP to limit where scripts can load from (see config/security.php).' : 'Sent on every response.'
        );

        // PHP settings
        if ($production && in_array(strtolower((string) ini_get('display_errors')), ['1', 'on', 'stdout'], true)) {
            $add('display_errors', 'fail', 'PHP shows errors to visitors. Set display_errors=Off (APP_DEBUG=false does this).');
        }
        $add('PHP version', version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'warn', 'PHP ' . PHP_VERSION . (version_compare(PHP_VERSION, '8.1.0', '>=') ? '' : ': PHP 8.0 no longer gets security fixes. Upgrade to 8.1+.'));
        $add('Password hashing', defined('PASSWORD_ARGON2ID') ? 'ok' : 'warn', defined('PASSWORD_ARGON2ID') ? 'Argon2id available.' : 'Argon2id not available; bcrypt is used.');

        // Writable folders
        foreach (['storage/logs', 'storage/cache', 'public/storage'] as $dir) {
            $path = BASE_PATH . '/' . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
            $add("Writable: $dir", is_writable($path) ? 'ok' : 'fail', is_writable($path) ? 'OK' : 'PHP cannot write here; logs, cache or uploads will fail.');
        }

        // Secrets not under the web root
        $add('Web root', is_file(BASE_PATH . '/public/.env') ? 'fail' : 'ok', is_file(BASE_PATH . '/public/.env')
            ? 'A .env file is inside public/ and can be downloaded. Move it to the project root.'
            : 'Point the web server at public/ only, so .env, config and source stay private.');

        // Root .htaccess safety
        $rootHtaccess = BASE_PATH . '/.htaccess';
        if (is_file($rootHtaccess)) {
            $htContent = (string) @file_get_contents($rootHtaccess);
            if (stripos($htContent, '<VirtualHost') !== false || stripos($htContent, '<Directory') !== false) {
                $add('Root .htaccess', 'fail', 'Root .htaccess contains Apache server configuration (<VirtualHost> or <Directory>) which causes 500 errors on shared hosting. Remove it.');
            }
        }

        // Demo credentials
        try {
            $demoAccounts = [
                'admin@example.com' => 'Demo admin login',
                'user@example.com'  => 'Demo user login',
            ];
            foreach ($demoAccounts as $email => $title) {
                $demoUser = \System\Database\DB::table('users')->where('email', $email)->first();
                if ($demoUser && Hash::check('password', $demoUser->password)) {
                    $add($title, $production ? 'fail' : 'warn', "$email still has the demo password \"password\". Change it before going live.");
                }
            }
        } catch (\Throwable $e) {
            // no database or no users table: nothing to check
        }

        return $results;
    }

    /** Worst status: fail > warn > ok */
    public static function summary(array $results): string
    {
        $statuses = array_column($results, 'status');
        return in_array('fail', $statuses, true) ? 'fail' : (in_array('warn', $statuses, true) ? 'warn' : 'ok');
    }
}
