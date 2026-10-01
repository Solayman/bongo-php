<?php
namespace System\Auth;

use System\Http\Session;
use System\Log\Log;

/**
 * Keeps the user id in the session (browser logins). Each guard has its own
 * session key, so a user can be signed in to several guards at once.
 */
class SessionGuard extends Guard
{
    protected function key(): string
    {
        return 'auth_' . $this->name;
    }

    public function user(): ?object
    {
        if (!$this->resolved) {
            $this->resolved = true;
            $id = Session::get($this->key());
            $this->user = $id ? $this->provider->findById($id) : null;
        }
        return $this->user;
    }

    public function attempt(string $identifier, string $password): bool
    {
        $user = $this->provider->findByCredentials($identifier, $password);
        if (!$user) {
            return false;
        }
        $this->login($user);
        return true;
    }

    public function login(object $user): void
    {
        // New session id + CSRF token: prevents session fixation
        Session::regenerate();
        Session::regenerateToken();
        Session::put($this->key(), (int) $user->id);
        $this->setUser($user);
        Log::info('User {user_id} logged in ({guard} guard)', ['user_id' => (int) $user->id, 'guard' => $this->name, 'ip' => \System\Http\Request::ip()]);
    }

    public function logout(): void
    {
        if ($id = Session::get($this->key())) {
            Log::info('User {user_id} logged out ({guard} guard)', ['user_id' => $id, 'guard' => $this->name]);
        }
        Session::forget($this->key());
        Session::regenerate();
        Session::regenerateToken();
        $this->setUser(null);
    }
}
