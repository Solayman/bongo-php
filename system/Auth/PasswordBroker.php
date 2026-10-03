<?php
namespace System\Auth;

use System\Cache\RateLimiter;
use System\Database\DB;
use System\Events\Event;
use System\Log\Log;
use System\Mail\Mail;
use System\Security\Hash;

/**
 * "Forgot password" flow:
 *   1. sendResetLink($email) mails a single-use link (only if the account exists;
 *      callers always show the same message, so the form cannot reveal accounts)
 *   2. reset($email, $token, $password) checks the token and sets the new password
 *
 * Tokens are 64 random hex characters; only their SHA-256 hash is stored.
 * Links expire after config('auth.passwords.expire') minutes (default 60).
 */
class PasswordBroker
{
    public const RESET_LINK_SENT = 'sent';
    public const THROTTLED = 'throttled';
    public const PASSWORD_RESET = 'reset';
    public const INVALID_TOKEN = 'invalid_token';

    public function __construct(
        protected ?UserProvider $provider = null,
        protected string $table = 'password_resets'
    ) {
        $this->provider ??= UserProvider::fromConfig((string) config('auth.passwords.provider', 'users'));
    }

    protected function expireMinutes(): int
    {
        return (int) config('auth.passwords.expire', 60);
    }

    /**
     * Returns RESET_LINK_SENT (also when the email is unknown) or THROTTLED.
     */
    public function sendResetLink(string $email, ?callable $mailer = null): string
    {
        $email = strtolower(trim($email));
        $limiter = new RateLimiter();
        $key = 'password-reset:' . sha1($email . '|' . \System\Http\Request::ip());

        if ($limiter->tooManyAttempts($key, 3)) {
            Log::warning('Password reset throttled for {email}', ['email' => $email]);
            return self::THROTTLED;
        }
        $limiter->hit($key, 15 * 60);

        $user = $this->provider->findByIdentifier($email);
        if ($user === null) {
            Log::info('Password reset requested for unknown email {email}', ['email' => $email]);
            return self::RESET_LINK_SENT;
        }

        $token = bin2hex(random_bytes(32));
        DB::transaction(function () use ($email, $token) {
            DB::table($this->table)->where('email', $email)->delete();
            DB::table($this->table)->insert(['email' => $email, 'token_hash' => hash('sha256', $token), 'created_at' => date('Y-m-d H:i:s')]);
        });

        $link = route('password.reset', ['token' => $token, 'email' => $email]);
        if ($mailer) {
            $mailer($user, $link, $token);
        } else {
            Mail::to($user->email, $user->name ?? null)
                ->subject(__('Reset your password'))
                ->view('emails.reset-password', ['user' => $user, 'link' => $link, 'minutes' => $this->expireMinutes()])
                ->send();
        }

        Event::dispatch('auth.password_reset_requested', $user);
        Log::info('Password reset link sent to user {user_id}', ['user_id' => $user->id]);
        return self::RESET_LINK_SENT;
    }

    /** Is this token valid for this email right now? */
    public function tokenIsValid(string $email, string $token): bool
    {
        $row = DB::table($this->table)->where('email', strtolower(trim($email)))->first();
        if (!$row || !hash_equals((string) $row->token_hash, hash('sha256', $token))) {
            return false;
        }
        return strtotime((string) $row->created_at) + $this->expireMinutes() * 60 >= time();
    }

    /**
     * Returns PASSWORD_RESET or INVALID_TOKEN (wrong, used or expired link).
     */
    public function reset(string $email, string $token, string $password): string
    {
        $email = strtolower(trim($email));
        $user = $this->provider->findByIdentifier($email);

        if ($user === null || !$this->tokenIsValid($email, $token)) {
            Log::warning('Invalid password reset attempt for {email}', ['email' => $email, 'ip' => \System\Http\Request::ip()]);
            return self::INVALID_TOKEN;
        }

        DB::transaction(function () use ($user, $email, $password) {
            DB::table('users')->where('id', $user->id)->update(['password' => Hash::make($password)]);
            DB::table($this->table)->where('email', $email)->delete(); // single use
        });

        (new LoginThrottle())->clear($email, \System\Http\Request::ip());
        Event::dispatch('auth.password_reset', $user);
        Log::info('Password reset for user {user_id}', ['user_id' => $user->id]);
        return self::PASSWORD_RESET;
    }

    /** Remove expired tokens */
    public function pruneExpired(): void
    {
        DB::table($this->table)->where('created_at', '<', date('Y-m-d H:i:s', time() - $this->expireMinutes() * 60))->delete();
    }
}
