<?php
namespace App\Http\Middleware;

use System\Http\HttpException;
use System\Http\Request;
use System\Http\Session;

class VerifyCsrfToken
{
    public function handle(): void
    {
        // Reading requests never change data, so they need no token
        if (in_array(Request::realMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!is_string($token) || !hash_equals(Session::token(), $token)) {
            throw new HttpException(419);
        }
    }
}
