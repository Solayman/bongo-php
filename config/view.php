<?php
return [
    // Where templates live (dot notation: products.index → products/index.php)
    'path' => BASE_PATH . '/resources/views',

    // Compiled templates are cached here and rebuilt when the template changes
    'compiled' => BASE_PATH . '/storage/cache/views',

    // Turn off to compile on every render (only useful when debugging the compiler)
    'cache' => (bool) env('VIEW_CACHE', true),
];
