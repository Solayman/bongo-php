<?php

// Cross-Origin Resource Sharing for the "cors" middleware (on the api group by default)

$list = fn(string $value) => array_values(array_filter(array_map('trim', explode(',', $value))));

return [
    // "*" allows any origin. For a SPA on another domain list it: CORS_ALLOWED_ORIGINS=https://app.example.com
    'allowed_origins' => $list((string) env('CORS_ALLOWED_ORIGINS', '*')),

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With', 'X-CSRF-TOKEN', 'Accept'],

    // Response headers the browser may read
    'exposed_headers' => [],

    // Cookies / Authorization from the browser. Not allowed together with origin "*".
    'supports_credentials' => (bool) env('CORS_CREDENTIALS', false),

    // How long (seconds) the browser may cache a preflight answer
    'max_age' => 600,
];
