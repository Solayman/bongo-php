<?php
use System\View\ViewEngine;

if (!function_exists('view')) {
    /** Render a template to a string: view('products.index', ['products' => $products]) */
    function view(string $view, array $data = []): string
    {
        return ViewEngine::shared()->render($view, $data);
    }
}
