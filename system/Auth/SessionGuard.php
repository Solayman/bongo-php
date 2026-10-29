<?php

namespace System\Auth;

use System\Http\Request;
use System\Http\Session;
use System\Log\Log;

/**
 * Keeps the user id in the session (browser logins). Each guard has its own
 * session key, so a user can be signed in to several guards at once.
 *
 * "Remember me": attempt($email, $password, remember: true) also sets a long-lived
 * cookie (remember_{guard}, AUTH_REMEMBER_DAYS, default 30) that signs the user back
 * in after the browser was closed. Only a SHA-256 hash of its token is stored, and
 * logout() forgets it.
 */
class SessionGuard extends Guard
{
    protected function key(): string
    {
        return 'auth_' . $this->name;
    }

    protected function cookieName(): string
    {
        return 'remember_' . $this->name;
    }

    public function user(): ?object
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $id = Session::get($this->key());
            $this->user = $id ? $this->provider->findById($id) : $this->userFromRememberCookie();
        }
        return $this->user;
    }

    public function attempt(string $identifier, string $password, bool $remember = false): bool
    {
        $user = $this->provider->findByCredentials($identifier, $password);
        if (!$user) {
            return false;
        }
        $this->login($user, $remember);
        return true;
    }

    public function login(object $user, bool $remember = false): void
    {
        // New session id + CSRF token: prevents session fixation
        Session::regenerate();
        Session::regenerateToken();
        Session::put($this->key(), (int) $user->id);
        $this->setUser($user);
        if ($remember) {
            $this->remember((int) $user->id);
        }
        Log::info('User {user_id} logged in ({guard} guard)', ['user_id' => (int) $user->id, 'guard' => $this->name, 'ip' => Request::ip(), 'remember' => $remember]);
    }

    public function logout(): void
    {
        if ($id = Session::get($this->key())) {
            Log::info('User {user_id} logged out ({guard} guard)', ['user_id' => $id, 'guard' => $this->name]);
            $this->provider->setRememberToken((int) $id, null);   // signs out "remember me" on every device
        }
        $this->setCookie('', time() - 3600);
        Session::forget($this->key());
        Session::regenerate();
        Session::regenerateToken();
        $this->setUser(null);
    }

    /** A new random token: the cookie holds "id|token", the database only its hash */
    protected function remember(int $id): void
    {
        $token = bin2hex(random_bytes(32));
        $this->provider->setRememberToken($id, hash('sha256', $token));
        $days = max(1, (int) $this->config('remember_days', config('auth.remember_days', 30)));
        $this->setCookie("$id|$token", time() + $days * 86400);
    }

    /** The user from a valid remember cookie (then signed in to the session), or null */
    protected function userFromRememberCookie(): ?object
    {
        $cookie = (string) ($_COOKIE[$this->cookieName()] ?? '');
        if (!preg_match('/^(\d+)\|([a-f0-9]{64})$/', $cookie, $m)) {
            return null;
        }
        $user = $this->provider->findById((int) $m[1]);
        $stored = (string) ($user->remember_token ?? '');
        if (!$user || $stored === '' || !hash_equals($stored, hash('sha256', $m[2]))) {
            $this->setCookie('', time() - 3600);   // forged or outdated: drop it
            return null;
        }
        // Guard separation: web guard is restricted to administrators
        if ($this->name === 'web' && isset($user->role) && $user->role !== 'admin') {
            $this->setCookie('', time() - 3600);
            return null;
        }
        Session::regenerate();
        Session::put($this->key(), (int) $user->id);
        Log::info('User {user_id} signed in by "remember me" ({guard} guard)', ['user_id' => (int) $user->id, 'guard' => $this->name, 'ip' => Request::ip()]);
        return $user;
    }

    protected function setCookie(string $value, int $expires): void
    {
        if ($value === '') {
            unset($_COOKIE[$this->cookieName()]);
        }
        if (headers_sent() || PHP_SAPI === 'cli') {
            return;
        }
        setcookie($this->cookieName(), $value, [
            'expires'  => $expires,
            'path'     => '/',
            'httponly' => true,
            'secure'   => Request::isSecure(),
            'samesite' => 'Lax',
        ]);
    }
}
