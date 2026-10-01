<?php
namespace App\Http\Middleware;

use System\Auth\Auth;

/**
 * "guest" or "guest:web": signed-in users are sent away from pages like /login.
 */
class RedirectIfAuthenticated
{
    public function handle(string ...$guards): void
    {
        foreach ($guards ?: [Auth::defaultGuard()] as $name) {
            if (Auth::guard($name)->check()) {
                redirect(url('/'));
            }
        }
    }
}
