<?php
namespace System\Http\Middleware;

use System\Http\Session;

/**
 * Starts the PHP session (cookie, flash data, CSRF token).
 * On the "web" group only: API routes stay stateless and set no cookie.
 */
class StartSession
{
    public function handle(): void
    {
        Session::start();
    }
}
