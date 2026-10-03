<?php
return [
    // sync: run jobs immediately (no worker needed) · database: store in the jobs table for `php bongo queue:work`
    'driver' => env('QUEUE_DRIVER', 'sync'),

    // A job reserved longer than this (seconds) is assumed lost (worker crashed) and runs again
    'retry_after' => (int) env('QUEUE_RETRY_AFTER', 90),
];
