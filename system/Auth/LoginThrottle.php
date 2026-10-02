<?php
namespace System\Auth;

use System\Database\DB;
use System\Log\Log;

/**
 * Brute-force protection: too many failed logins for one email + IP
 * locks that pair for a while. Stored in the login_attempts table.
 */
class LoginThrottle
{
    public function __construct(
        protected int $maxAttempts = 5,
        protected int $decayMinutes = 15
    ) {}

    protected function key(string $email, string $ip): string
    {
        return hash('sha256', strtolower($email) . '|' . $ip);
    }

    protected function since(): string
    {
        return date('Y-m-d H:i:s', time() - $this->decayMinutes * 60);
    }

    public function tooManyAttempts(string $email, string $ip): bool
    {
        $locked = DB::table('login_attempts')
            ->where('throttle_key', $this->key($email, $ip))
            ->where('attempted_at', '>=', $this->since())
            ->count() >= $this->maxAttempts;

        if ($locked) {
            Log::warning('Login locked out for {email} (too many attempts)', ['email' => $email, 'ip' => $ip]);
        }
        return $locked;
    }

    public function hit(string $email, string $ip): void
    {
        Log::warning('Failed login for {email}', ['email' => $email, 'ip' => $ip]);
        DB::table('login_attempts')->insert([
            'throttle_key' => $this->key($email, $ip),
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function clear(string $email, string $ip): void
    {
        DB::table('login_attempts')->where('throttle_key', $this->key($email, $ip))->delete();
    }

    /**
     * Seconds until the oldest counted attempt expires
     */
    public function availableIn(string $email, string $ip): int
    {
        $oldest = DB::table('login_attempts')
            ->where('throttle_key', $this->key($email, $ip))
            ->where('attempted_at', '>=', $this->since())
            ->orderBy('attempted_at')
            ->first();

        return $oldest ? max(1, strtotime($oldest->attempted_at) + $this->decayMinutes * 60 - time()) : 0;
    }
}
