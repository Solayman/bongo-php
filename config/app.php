<?php
return [
    'name'  => env('APP_NAME', 'BongoMVC'),
    'env'   => env('APP_ENV', 'local'),
    'debug' => env('APP_DEBUG', env('APP_ENV', 'local') !== 'production'),
    'url'   => env('APP_URL', 'http://127.0.0.1:8000'),

    // Used by PHP dates, log timestamps and the MySQL session (e.g. Asia/Dhaka)
    'timezone' => env('APP_TIMEZONE', 'UTC'),

    // Languages: default, fallback for missing text, and the ones users may choose (code => name)
    'locale'          => env('APP_LOCALE', 'en'),
    'fallback_locale' => 'en',
    'locales'         => ['en' => 'English', 'bn' => 'বাংলা'],

    // Route files: each is loaded inside a group with its prefix and middleware
    'routes' => [
        'web' => ['file' => 'routes/web.php', 'middleware' => ['web']],
        'api' => ['file' => 'routes/api.php', 'prefix' => '/api', 'middleware' => ['api'], 'name' => 'api.'],
    ],

    // Middleware aliases, used on routes as ->middleware('auth') or 'auth:param1,param2'
    'middleware' => [
        'auth'  => App\Http\Middleware\Authenticate::class,
        'guest' => App\Http\Middleware\RedirectIfAuthenticated::class,
        'csrf'  => App\Http\Middleware\VerifyCsrfToken::class,
        'cors'    => System\Http\Middleware\Cors::class,
        'session'  => System\Http\Middleware\StartSession::class,
        'throttle' => System\Http\Middleware\ThrottleRequests::class,
        'locale'   => System\Http\Middleware\SetLocale::class,
        'can'      => System\Http\Middleware\Authorize::class,   // can:ability[,routeParam]
    ],

    // Named sets of middleware
    'middleware_groups' => [
        'web' => ['session', 'locale', 'csrf'],
        'api' => ['cors', 'throttle:' . env('API_RATE_LIMIT', 60) . ',1'],
    ],

    // Run on every request, before routing
    'middleware_global' => [
        System\Http\Middleware\SecurityHeaders::class,
    ],
];
