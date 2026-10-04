<?php

return [
    // mysql, sqlite, or pgsql (DB_CONNECTION=sqlite needs no database server)
    'default' => env('DB_CONNECTION', 'mysql'),

    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'bongo'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],

        'sqlite' => [
            'driver' => 'sqlite',
            // One file holds the whole database; it is created on first use
            'database' => env('DB_SQLITE_PATH', BASE_PATH . '/database/database.sqlite'),
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => (int) env('DB_PORT', 5432),
            'database' => env('DB_DATABASE', 'bongo'),
            'username' => env('DB_USERNAME', 'postgres'),
            'password' => env('DB_PASSWORD', ''),
            'schema' => env('DB_SCHEMA', 'public'),
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],
    ],

    // Used by CACHE_DRIVER=redis and SESSION_DRIVER=redis (needs the "redis" PHP extension)
    'redis' => [
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'port' => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', ''),
        'database' => (int) env('REDIS_DB', 0),
        // Every key starts with this, so several apps can share one Redis server
        'prefix' => env('REDIS_PREFIX', 'bongo:'),
    ],
];
