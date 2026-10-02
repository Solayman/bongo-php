<?php
namespace App\Http\Controllers;

use System\Http\Request;
use System\Http\Session;
use System\Validation\Validator;
use System\View\ViewEngine;

class Controller
{
    /**
     * Render a template. Without a name the action's conventional view is used:
     * ProductController::index → products.index
     */
    protected function view(?string $view = null, array $data = []): string
    {
        $view ??= ViewEngine::guessName(static::class, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'index');
        return ViewEngine::shared()->render($view, $data);
    }

    /**
     * Validate request input. On failure: flash errors + old input and go back.
     * Returns only the validated fields.
     */
    protected function validate(array $rules, ?array $data = null): array
    {
        $data ??= Request::all() + Request::files();
        $errors = Validator::make($data, $rules);

        if ($errors) {
            // API clients get 422 JSON instead of a redirect
            if (Request::wantsJson()) {
                response(['message' => 'The given data was invalid.', 'errors' => $errors], 'json', 422);
            }
            Session::flash('_errors', $errors);
            Session::flash('_old', array_filter(
                $data,
                fn($value, $key) => !str_contains((string) $key, 'password') && $key !== '_token' && !$value instanceof \System\Http\UploadedFile,
                ARRAY_FILTER_USE_BOTH
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
