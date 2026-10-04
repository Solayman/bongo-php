<?php

return [
    // log: write emails to the log (development) · smtp: send for real · file: JSON files (tests)
    'driver' => env('MAIL_DRIVER', 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@example.com'),
        'name'    => env('MAIL_FROM_NAME', env('APP_NAME', 'Bongo')),
    ],

    'smtp' => [
        'host'        => env('MAIL_HOST', 'localhost'),
        'port'        => (int) env('MAIL_PORT', 587),
        'encryption'  => env('MAIL_ENCRYPTION', 'tls'),   // tls (STARTTLS), ssl, or none
        'username'    => env('MAIL_USERNAME', ''),
        'password'    => env('MAIL_PASSWORD', ''),
        'timeout'     => 15,
        'verify_peer' => (bool) env('MAIL_VERIFY_PEER', true),
    ],

    // "file" driver: where the JSON files go
    'file_path' => env('MAIL_PATH', BASE_PATH . '/storage/mail'),
];
