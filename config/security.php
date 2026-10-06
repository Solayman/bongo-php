<?php

return [
    // Proxies/load balancers whose X-Forwarded-For / X-Forwarded-Proto headers are trusted.
    // Comma-separated IPs, e.g. TRUSTED_PROXIES=127.0.0.1,10.0.0.5 ("*" = any; only behind a proxy you control)
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', ''))))),

    // Content-Security-Policy header (empty = not sent). Example for the admin panel (Bootstrap from jsDelivr):
    // SECURITY_CSP="default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; frame-ancestors 'self'; form-action 'self'; base-uri 'self'"
    'csp' => (string) env('SECURITY_CSP', ''),

    // Permissions-Policy header. Opt-in to unload=* prevents Chrome unload deprecation violations for extensions
    'permissions_policy' => (string) env('SECURITY_PERMISSIONS_POLICY', 'camera=(), microphone=(), geolocation=(), unload=*'),

    // Cross-Origin-Opener-Policy header (same-origin-allow-popups preserves window.opener for file manager/OAuth popups)
    'cross_origin_opener_policy' => (string) env('SECURITY_COOP', 'same-origin'),
];
