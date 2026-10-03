<?php

return [
    // mysql or sqlite (DB_CONNECTION=sqlite needs no database server)
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
    ],
];
