<?php
namespace App\Http\Middleware;

use System\Auth\Auth;
use System\Http\Request;
use System\Http\Session;

class Authenticate
{
    public function handle(): void
    {
        if (Auth::check()) {
            return;
        }

        if (Request::method() === 'GET') {
            Session::put('url.intended', Request::path());
        }

        flash('error', 'Please sign in to continue.');
        redirect(url('/login'));
    }
}
