<?php
return [
    // Cookie name (does not reveal the language like "PHPSESSID")
    'cookie' => env('SESSION_COOKIE', 'bongo_session'),

    // Signed-in users are logged out after this many minutes without a request
    'idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 120),

    // A session never lives longer than this (minutes), even when active
    'absolute_minutes' => (int) env('SESSION_ABSOLUTE_MINUTES', 720),

    // Lax: the cookie is not sent on cross-site POSTs (extra CSRF protection)
    'same_site' => env('SESSION_SAME_SITE', 'Lax'),
];
