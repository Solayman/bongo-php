<?php
return [
    // sync: run jobs immediately · database: jobs table · redis: Redis lists & delayed sorted sets
    'driver' => env('QUEUE_DRIVER', 'sync'),

    // A job reserved longer than this (seconds) is assumed lost (worker crashed) and runs again
    'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),
];
