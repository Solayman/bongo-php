<?php
namespace System\Security;

use RuntimeException;

/**
 * Reads the application secret from APP_KEY in .env.
 * Format: APP_KEY=base64:<32 random bytes, base64 encoded>
 * Create one with: php bongo key:generate
 */
class Key
{
    public static function get(): string
    {
        $key = (string) env('APP_KEY', '');

        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7), true) ?: '';
        }

        if (strlen($key) < 32) {
            throw new RuntimeException('APP_KEY is missing or too short. Run: php bongo key:generate');
        }

        return $key;
    }

    /**
     * A separate sub-key per purpose, so the same APP_KEY is never reused directly
     */
    public static function derive(string $purpose): string
    {
        return hash_hkdf('sha256', self::get(), 32, "bongomvc:$purpose");
    }

    public static function generate(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }
}
