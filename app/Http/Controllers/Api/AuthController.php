<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use System\Auth\Auth;
use System\Auth\JwtGuard;
use System\Auth\LoginThrottle;
use System\Http\Request;

/**
 * JWT login for API clients (mobile apps, SPAs, other servers).
 */
class AuthController extends Controller
{
    /** POST /api/auth/login  {email, password} → {access_token, token_type, expires_in, user} */
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
            header('Retry-After: ' . $throttle->availableIn($email, $ip));
            response(['error' => ['status' => 429, 'message' => 'Too many login attempts. Try again later.']], 'json', 429);
        }

        $token = $this->guard()->attempt($email, $data['password']);
        if ($token === null) {
            $throttle->hit($email, $ip);
            response(['error' => ['status' => 401, 'message' => 'These credentials do not match our records.']], 'json', 401);
        }

        $throttle->clear($email, $ip);
        return $this->tokenResponse($token);
    }

    /** GET /api/auth/me */
    public function me()
    {
        return ['user' => $this->publicUser($this->guard()->user())];
    }

    /** POST /api/auth/refresh  (Authorization: Bearer <old token>) → a new token; the old one stops working */
    public function refresh()
    {
        $token = $this->guard()->refresh();
        if ($token === null) {
            response(['error' => ['status' => 401, 'message' => 'Token cannot be refreshed: ' . $this->guard()->error() . '.']], 'json', 401);
        }
        return $this->tokenResponse($token);
    }

    /** POST /api/auth/logout → the token is revoked */
    public function logout()
    {
        $this->guard()->logout();
        return ['message' => 'Token revoked.'];
    }

    protected function guard(): JwtGuard
    {
        return Auth::guard('api');
    }

    protected function tokenResponse(string $token): array
    {
        $claims = $this->guard()->claims();
        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $claims['exp'] - time(),
            'user'         => $this->publicUser($this->guard()->user()),
        ];
    }

    /** Never send the password hash */
    protected function publicUser(?object $user): ?array
    {
        if (!$user) {
            return null;
        }
        $data = (array) $user;
        unset($data['password']);
        return $data;
    }
}
