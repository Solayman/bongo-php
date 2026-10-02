<?php
namespace App\Http\Controllers;

use System\Http\Request;
use System\Http\Session;
use System\Validation\Validator;
use System\View\ViewEngine;

class Controller
{
    protected ViewEngine $viewEngine;

    public function __construct()
    {
        $this->viewEngine = new ViewEngine(
            base_path('resources/views'),
            base_path('storage/cache/views')
        );
    }

    protected function view(string $view, array $data = []): string
    {
        return $this->viewEngine->render($view, $data);
    }

    /**
     * Validate request input. On failure: flash errors + old input and go back.
     * Returns only the validated fields.
     */
    protected function validate(array $rules, ?array $data = null): array
    {
        $data ??= Request::all();
        $errors = Validator::make($data, $rules);

        if ($errors) {
            Session::flash('_errors', $errors);
            Session::flash('_old', array_filter(
                $data,
                fn($key) => !str_contains((string) $key, 'password') && $key !== '_token',
                ARRAY_FILTER_USE_KEY
            ));
            back();
        }

        return array_intersect_key($data, $rules);
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
