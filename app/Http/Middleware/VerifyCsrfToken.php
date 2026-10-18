<?php

namespace App\Http\Middleware;

use System\Http\HttpException;
use System\Http\Request;
use System\Http\Session;

class VerifyCsrfToken
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected array $except = [
        'api/*',
    ];

    public function handle(): void
    {
        // Reading requests never change data, so they need no token
        if (in_array(Request::realMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        if ($this->inExceptArray()) {
            return;
        }

        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!is_string($token) || !Session::isValidToken($token)) {
            throw new HttpException(419);
        }
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     */
    protected function inExceptArray(): bool
    {
        $path = trim(Request::path(), '/');
        foreach ($this->except as $except) {
            $except = trim($except, '/');
            if ($except === $path) {
                return true;
            }
            $pattern = '#^' . str_replace('\*', '.*', preg_quote($except, '#')) . '$#i';
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        return false;
    }
}
