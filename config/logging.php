<?php

return [
    // daily: storage/logs/bongo-YYYY-MM-DD.log   single: storage/logs/bongo.log   none: logging off
    'channel' => env('LOG_CHANNEL', 'daily'),

    // Lowest level written: debug, info, notice, warning, error, critical, alert, emergency
    'level' => env('LOG_LEVEL', env('APP_ENV', 'local') === 'production' ? 'info' : 'debug'),

    // Output format: text (bracketed plain text) or json (structured single-line JSON)
    'format' => env('LOG_FORMAT', 'text'),

    'path' => env('LOG_PATH', BASE_PATH . '/storage/logs'),

    // Daily files older than this many days are deleted (0 = keep forever)
    'days' => (int) env('LOG_DAYS', 14),
];
