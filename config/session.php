<?php

return [
    // file (PHP's default) or redis (shared by every server; connection in config/database.php)
    'driver' => env('SESSION_DRIVER', 'file'),

    // Cookie name (does not reveal the language like "PHPSESSID")
    'cookie' => env('SESSION_COOKIE', 'bongo_session'),

    // Signed-in users are logged out after this many minutes without a request
    'idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 120),

    // A session never lives longer than this (minutes), even when active
    'absolute_minutes' => (int) env('SESSION_ABSOLUTE_MINUTES', 720),

    // Lax: the cookie is not sent on cross-site POSTs (extra CSRF protection)
    'same_site' => env('SESSION_SAME_SITE', 'Lax'),

    // Grace period (seconds) during which recently rotated CSRF tokens remain valid
    // to prevent rapid sequential or in-flight requests from failing with 419.
    'token_grace_window' => (int) env('SESSION_TOKEN_GRACE_WINDOW', 10),
];
