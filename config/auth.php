<?php
return [
    // Guard used by Auth::user(), auth() and plain "auth" middleware
    'default' => 'web',

    'guards' => [
        // Browser sessions (cookie)
        'web' => ['driver' => 'session', 'provider' => 'users', 'login_url' => '/login'],

        // API clients: "Authorization: Bearer <JWT>"
        'api' => ['driver' => 'jwt', 'provider' => 'users'],
    ],

    // "Forgot password": which provider, and how long a reset link works (minutes)
    'passwords' => ['provider' => 'users', 'expire' => 60],

    // Where users come from: a table plus optional fixed conditions
    'providers' => [
        'users' => ['table' => 'users'],
        // 'admins' => ['table' => 'users', 'where' => ['role' => 'admin']],
    ],
];
