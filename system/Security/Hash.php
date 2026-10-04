<?php

namespace System\Security;

/**
 * One-way password hashing with a secret pepper from APP_KEY.
 *
 *   stored = argon2id( HMAC-SHA256(password, key) )
 *
 * - Passwords can never be decrypted, only checked.
 * - A stolen database is not enough to crack them: the attacker also needs APP_KEY.
 * - Changing APP_KEY makes every stored password invalid, so keep it safe and stable.
 */
class Hash
{
    public static function make(string $password): string
    {
        return password_hash(self::pepper($password), self::algorithm());
    }

    public static function check(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            // Still spend the same time, so response time does not reveal missing users
            password_verify(self::pepper($password), self::dummyHash());
            return false;
        }

        return password_verify(self::pepper($password), $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, self::algorithm());
    }

    protected static function pepper(string $password): string
    {
        // base64 keeps it printable and under bcrypt's 72-byte limit (44 chars)
        return base64_encode(hash_hmac('sha256', $password, Key::derive('password'), true));
    }

    protected static function algorithm(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    protected static function dummyHash(): string
    {
        static $dummy = null;
        return $dummy ??= password_hash('dummy-password', self::algorithm());
    }
}
