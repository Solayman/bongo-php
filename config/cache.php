<?php
return [
    // file (default, nothing to install) or redis (connection in config/database.php)
    'driver' => env('CACHE_DRIVER', 'file'),

    // The file driver stores Cache::... and the rate limiter here
    'path' => env('CACHE_PATH', BASE_PATH . '/storage/cache/data'),
];
