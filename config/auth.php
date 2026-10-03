<?php
return [
    // Guard used by Auth::user(), auth() and plain "auth" middleware
    'default' => 'web',

    'guards' => [
        // Browser sessions (cookie)
        'web' => ['driver' => 'session', 'provider' => 'users', 'login_url' => '/login', 'home' => '/'],

        // API clients: "Authorization: Bearer <JWT>"
        'api' => ['driver' => 'jwt', 'provider' => 'users'],

        // Storefront shoppers: their own session key, so they never reach the admin panel
        'customer' => ['driver' => 'session', 'provider' => 'customers', 'login_url' => '/shop/login', 'home' => '/shop'],
    ],

    // "Forgot password": which provider, and how long a reset link works (minutes)
    'passwords' => ['provider' => 'users', 'expire' => 60],

    // "Remember me" on the login forms: how long the cookie keeps a user signed in (days)
    'remember_days' => (int) env('AUTH_REMEMBER_DAYS', 30),

    // Where users come from: a table plus optional fixed conditions
    'providers' => [
        'users' => ['table' => 'users'],
        'customers' => ['table' => 'users', 'where' => ['role' => 'customer']],
        // 'admins' => ['table' => 'users', 'where' => ['role' => 'admin']],
    ],
];
