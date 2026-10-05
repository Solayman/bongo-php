<?php

namespace App\Http\Controllers;

use System\Auth\Auth;
use System\Auth\LoginThrottle;
use System\Http\Request;
use System\Http\Session;

class AuthController extends Controller
{
    public function showLogin()
    {
        return $this->view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email|max:150',
            'password' => 'required|string|max:255',
        ]);

        $email = strtolower($data['email']);
        $ip = $request->ip();
        $throttle = new LoginThrottle();

        if ($throttle->tooManyAttempts($email, $ip)) {
            $minutes = (int) ceil($throttle->availableIn($email, $ip) / 60);
            $this->failLogin(__('Too many login attempts. Try again in :minutes minute(s).', ['minutes' => $minutes]), $email);
        }

        if (!Auth::attempt($email, $data['password'], $request->boolean('remember'))) {
            $throttle->hit($email, $ip);
            // Same message for unknown email and wrong password
            $this->failLogin(__('These credentials do not match our records.'), $email);
        }

        $throttle->clear($email, $ip);

        // The panel is for administrators only
        if (Auth::user()->role !== 'admin') {
            Auth::logout();
            $this->failLogin(__('Only administrators can sign in to the dashboard.'), $email);
        }

        $intended = Session::get('url.intended', '/dashboard');
        Session::forget('url.intended');

        flash('success', __('Welcome back, :name!', ['name' => Auth::user()->name]));
        redirect(url($intended));
    }

    public function logout()
    {
        Auth::logout();
        flash('success', __('You have been signed out.'));
        redirect(url('/login'));
    }

    protected function failLogin(string $message, string $email): void
    {
        Session::flash('_errors', ['email' => $message]);
        Session::flash('_old', ['email' => $email]);
        redirect(url('/login'));
    }
}
