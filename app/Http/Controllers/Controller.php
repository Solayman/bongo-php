<?php
namespace App\Http\Controllers;

use System\View\ViewEngine;

class Controller
{
    protected ViewEngine $viewEngine;

    public function __construct()
    {
        $base = dirname(__DIR__, 3);
        $this->viewEngine = new ViewEngine(
            $base . '/resources/views',
            $base . '/storage/cache/views'
        );
    }

    protected function view(string $view, array $data = []): string
    {
        return $this->viewEngine->render($view, $data);
    }
    protected function respondSuccess(string $message, array|string|null $data = null)
    {
        return json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ]);
    }

    protected function respondError(string $message)
    {
        return json_encode([
            'status' => 'error',
            'message' => $message
        ]);
    }
}
