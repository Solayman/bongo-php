<?php

namespace System\Security;

use RuntimeException;

/**
 * Reversible encryption for data you need to read back (NOT for passwords).
 * AES-256-GCM with a key derived from APP_KEY: without the same APP_KEY the
 * data cannot be decrypted, and any tampering is detected.
 *
 *   $token = Crypt::encrypt('secret value');
 *   $value = Crypt::decrypt($token);
 */
class Crypt
{
    protected const CIPHER = 'aes-256-gcm';

    public static function encrypt(string $plaintext): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, Key::derive('encryption'), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $payload): string
    {
        $raw = base64_decode($payload, true);

        if ($raw === false || strlen($raw) < 28) {
            throw new RuntimeException('The encrypted payload is invalid.');
        }

        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $ciphertext = substr($raw, 28);

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, Key::derive('encryption'), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw new RuntimeException('The payload could not be decrypted (wrong APP_KEY or tampered data).');
        }

        return $plaintext;
    }
}
