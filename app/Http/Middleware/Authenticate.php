<?php

namespace App\Http\Middleware;

use System\Auth\Auth;
use System\Auth\JwtGuard;
use System\Http\Request;
use System\Http\Session;

/**
 * "auth" (default guard), "auth:api", or "auth:web,api" (the first guard that has a user wins).
 * Browsers are sent to the login page; API clients get 401 JSON.
 */
class Authenticate
{
    public function handle(string ...$guards): void
    {
        $guards = $guards ?: [Auth::defaultGuard()];

        foreach ($guards as $name) {
            if (Auth::guard($name)->check()) {
                Auth::shouldUse($name);
                return;
            }
        }

        $first = Auth::guard($guards[0]);

        if (Request::wantsJson() || $first instanceof JwtGuard) {
            if (!headers_sent()) {
                header('WWW-Authenticate: Bearer');
            }
            $reason = $first instanceof JwtGuard ? $first->error() : null;
            response(['error' => ['status' => 401, 'message' => 'Unauthenticated.' . ($reason ? " $reason." : '')]], 'json', 401);
        }

        if (Request::method() === 'GET') {
            Session::put('url.intended', Request::path());
        }

        flash('error', __('Please sign in to continue.'));
        redirect(url((string) $first->config('login_url', '/login')));
    }
}
