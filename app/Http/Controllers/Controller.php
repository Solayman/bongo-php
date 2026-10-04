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
     * Validate request input. On failure: flash errors + old input and go back
     * (422 JSON for API clients). Returns only the validated fields.
     * For bigger forms, a FormRequest class keeps the rules out of the controller.
     */
    protected function validate(array $rules, ?array $data = null, array $messages = [], array $attributes = []): array
    {
        $data ??= Request::all() + Request::files();
        $errors = Validator::make($data, $rules, $messages, $attributes);

        if ($errors) {
            Validator::respondWithErrors($errors, $data);
        }

        $keys = array_unique(array_map(fn($field) => explode('.', $field)[0], array_keys($rules)));
        return array_intersect_key($data, array_flip($keys));
    }

    /** Stop with 403 unless the signed-in user may do this: $this->authorize('delete', $product) */
    protected function authorize(string $ability, mixed ...$arguments): void
    {
        \System\Auth\Gate::authorize($ability, ...$arguments);
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
