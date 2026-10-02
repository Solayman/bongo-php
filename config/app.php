<?php
return [
    'name'  => env('APP_NAME', 'BongoMVC'),
    'env'   => env('APP_ENV', 'local'),
    'debug' => env('APP_DEBUG', env('APP_ENV', 'local') !== 'production'),
    'url'   => env('APP_URL', 'http://127.0.0.1:8000'),

    // Route middleware aliases: 'GET /path' => [Controller, method, ['auth', 'csrf']]
    'middleware' => [
        'auth'  => App\Http\Middleware\Authenticate::class,
        'guest' => App\Http\Middleware\RedirectIfAuthenticated::class,
        'csrf'  => App\Http\Middleware\VerifyCsrfToken::class,
    ],
];
