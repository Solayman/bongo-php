<?php
namespace App\Http\Controllers;

use System\Auth\Auth;
use System\Auth\LoginThrottle;
use System\Http\Session;

class AuthController extends Controller
{
    public function showLogin()
    {
        echo $this->view('auth.login');
    }

    public function login()
    {
        $data = $this->validate([
            'email'    => 'required|email|max:150',
            'password' => 'required|string|max:255',
        ]);

        $email = strtolower($data['email']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $throttle = new LoginThrottle();

        if ($throttle->tooManyAttempts($email, $ip)) {
            $minutes = (int) ceil($throttle->availableIn($email, $ip) / 60);
            $this->failLogin("Too many login attempts. Try again in $minutes minute(s).", $email);
        }

        if (!Auth::attempt($email, $data['password'])) {
            $throttle->hit($email, $ip);
            // Same message for unknown email and wrong password
            $this->failLogin('These credentials do not match our records.', $email);
        }

        $throttle->clear($email, $ip);

        // The panel is for administrators only
        if (Auth::user()->role !== 'admin') {
            Auth::logout();
            $this->failLogin('Only administrators can sign in to the dashboard.', $email);
        }

        $intended = Session::get('url.intended', '/');
        Session::forget('url.intended');

        flash('success', 'Welcome back, ' . Auth::user()->name . '!');
        redirect(url($intended));
    }

    public function logout()
    {
        Auth::logout();
        flash('success', 'You have been signed out.');
        redirect(url('/login'));
    }

    protected function failLogin(string $message, string $email): void
    {
        Session::flash('_errors', ['email' => $message]);
        Session::flash('_old', ['email' => $email]);
        redirect(url('/login'));
    }
}
