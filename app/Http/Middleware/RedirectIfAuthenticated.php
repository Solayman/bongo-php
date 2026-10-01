<?php
namespace App\Http\Middleware;

use System\Auth\Auth;

class RedirectIfAuthenticated
{
    public function handle(): void
    {
        if (Auth::check()) {
            redirect(url('/'));
        }
    }
}
