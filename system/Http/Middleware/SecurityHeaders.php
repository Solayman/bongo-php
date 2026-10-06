<?php

namespace System\Http\Middleware;

/**
 * Safe default response headers, applied to every request (config/app.php → middleware_global).
 */
class SecurityHeaders
{
    public function handle(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');           // no MIME sniffing
        header('X-Frame-Options: SAMEORIGIN');               // no clickjacking from other sites
        header('Referrer-Policy: strict-origin-when-cross-origin');

        $permissionsPolicy = (string) config('security.permissions_policy', 'camera=(), microphone=(), geolocation=(), unload=*');
        if ($permissionsPolicy !== '') {
            header('Permissions-Policy: ' . $permissionsPolicy);
        }

        header_remove('X-Powered-By');                       // don't advertise the PHP version

        $coop = (string) config('security.cross_origin_opener_policy', 'same-origin');
        if ($coop !== '') {
            header('Cross-Origin-Opener-Policy: ' . $coop);
        }

        if (\System\Http\Request::isSecure()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Optional Content-Security-Policy (config/security.php → SECURITY_CSP)
        $csp = trim((string) config('security.csp', ''));
        if ($csp !== '') {
            header('Content-Security-Policy: ' . $csp);
        }
    }
}
