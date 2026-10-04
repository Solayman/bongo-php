<?php

return [
    // Where `php bongo websocket:serve` listens (0.0.0.0 = every network interface)
    'host' => env('BROADCAST_HOST', '127.0.0.1'),
    'port' => (int) env('BROADCAST_PORT', 6001),

    // The address browsers connect to, e.g. wss://example.com/ws behind a proxy.
    // Empty: ws://<host of APP_URL>:<port>
    'public_url' => env('BROADCAST_PUBLIC_URL', ''),

    // Pages allowed to connect (comma separated). Empty: only APP_URL.
    'allowed_origins' => array_values(array_filter(array_map(
        fn($origin) => rtrim(trim($origin), '/'),
        explode(',', (string) env('BROADCAST_ALLOWED_ORIGINS', (string) env('APP_URL', '')))
    ))),
];
