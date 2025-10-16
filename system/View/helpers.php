<?php
use System\View\ViewEngine;

if (!function_exists('view')) {
    function view(string $view, array $data = []): string
    {
        static $engine;

        if (!$engine) {
            $base = dirname(__DIR__, 2);
            $engine = new ViewEngine(
                $base . '/resources/views',
                $base . '/storage/cache/views'
            );
        }

        return $engine->render($view, $data);
    }
}
