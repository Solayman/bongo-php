<?php
namespace App\Http\Middleware;

use System\Http\HttpException;
use System\Http\Request;
use System\Http\Session;

class VerifyCsrfToken
{
    public function handle(): void
    {
        if (Request::method() === 'GET') {
            return;
        }

        $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if (!is_string($token) || !hash_equals(Session::token(), $token)) {
            throw new HttpException(419);
        }
    }
}
