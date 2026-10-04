<?php

return [
    // Default disk: 'public' or 'local'
    'default' => env('STORAGE_DISK', 'public'),

    // Legacy fallback for config('storage.root')
    'root' => env('STORAGE_ROOT', BASE_PATH . '/public/storage'),

    'disks' => [
        // Public uploads: served at APP_URL/storage/... (see Storage::url())
        'public' => [
            'driver' => 'local',
            'root' => env('STORAGE_PUBLIC_ROOT', BASE_PATH . '/public/storage'),
            'url' => env('APP_URL', 'http://127.0.0.1:8000') . '/storage',
            'visibility' => 'public',
        ],

        // Private storage: files not directly accessible over web (invoices, exports, KYC)
        'local' => [
            'driver' => 'local',
            'root' => env('STORAGE_LOCAL_ROOT', BASE_PATH . '/storage/app'),
            'visibility' => 'private',
        ],
    ],
];
