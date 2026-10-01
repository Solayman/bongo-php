<?php
namespace System\Auth;

use System\Database\DB;
use System\Http\Session;
use System\Log\Log;
use System\Security\Hash;

class Auth
{
    protected static ?object $user = null;

    /**
     * Check credentials against the users table and log in on success
     */
    public static function attempt(string $email, string $password): bool
    {
        $user = DB::table('users')->where('email', $email)->first();

        // Hash::check() runs even for unknown emails, so timing does not reveal which exist
        if (!Hash::check($password, $user->password ?? null)) {
            return false;
        }

        if (Hash::needsRehash($user->password)) {
            DB::table('users')->where('id', $user->id)->update(['password' => Hash::make($password)]);
        }

        self::login($user);
        return true;
    }

    public static function login(object $user): void
    {
        Session::regenerate();
        Session::regenerateToken();
        Session::put('auth_id', (int) $user->id);
        self::$user = $user;
        Log::info('User {user_id} logged in', ['user_id' => (int) $user->id, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null]);
    }

    public static function logout(): void
    {
        if (self::id()) {
            Log::info('User {user_id} logged out', ['user_id' => self::id()]);
        }
        Session::forget('auth_id');
        Session::regenerate();
        Session::regenerateToken();
        self::$user = null;
    }

    public static function id(): ?int
    {
        return Session::get('auth_id');
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?object
    {
        if (self::$user === null && self::id()) {
            self::$user = DB::table('users')->where('id', self::id())->first();
        }
        return self::$user;
    }

    /**
     * Forget the cached user so the next user() call reloads it
     */
    public static function refresh(): void
    {
        self::$user = null;
    }
}
