<?php
namespace App\Http\Controllers;

use System\Auth\PasswordBroker;
use System\Http\Request;
use System\Http\Session;

class PasswordResetController extends Controller
{
    public function __construct(protected PasswordBroker $broker) {}

    /** GET /forgot-password */
    public function showForgot()
    {
        return $this->view('auth.forgot-password');
    }

    /** POST /forgot-password */
    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:150']);

        if ($this->broker->sendResetLink($data['email']) === PasswordBroker::THROTTLED) {
            Session::flash('_errors', ['email' => __('Too many reset requests. Please wait a few minutes.')]);
            Session::flash('_old', ['email' => $data['email']]);
            redirect(route('password.request'));
        }

        // Same answer whether or not the account exists
        flash('success', __('If that email has an account, we have sent a link to reset the password.'));
        redirect(route('password.request'));
    }

    /** GET /reset-password/{token}?email=... */
    public function showReset(Request $request, string $token)
    {
        $email = (string) $request->query('email', '');
        return $this->view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'valid' => $email !== '' && $this->broker->tokenIsValid($email, $token),
        ]);
    }

    /** POST /reset-password */
    public function reset(Request $request)
    {
        $data = $request->validate([
            'token'    => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'email'    => 'required|email|max:150',
            'password' => 'required|string|min:8|max:255|confirmed',
        ]);

        if ($this->broker->reset($data['email'], $data['token'], $data['password']) !== PasswordBroker::PASSWORD_RESET) {
            Session::flash('_errors', ['email' => __('This reset link is invalid or has expired.')]);
            redirect(route('password.request'));
        }

        flash('success', __('Your password has been reset. You can sign in now.'));
        redirect(route('login'));
    }
}
