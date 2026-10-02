<?php
namespace Tests\Unit;

use System\Database\DB;
use System\Security\Crypt;
use System\Security\Hash;
use System\Security\Key;
use System\Testing\TestCase;

class SecurityTest extends TestCase
{
    public function testPasswordHashIsOneWayAndVerifies(): void
    {
        $hash = Hash::make('correct horse');
        $this->assertNotContains('correct horse', $hash);
        $this->assertTrue(Hash::check('correct horse', $hash));
        $this->assertFalse(Hash::check('wrong', $hash));
        $this->assertFalse(Hash::check('anything', null));
    }

    public function testSamePasswordGivesDifferentHashes(): void
    {
        $this->assertNotSame(Hash::make('secret'), Hash::make('secret'), 'Each hash should use its own salt');
    }

    public function testHashDependsOnAppKey(): void
    {
        $hash = Hash::make('secret');
        $this->withKey(Key::generate(), fn() => $this->assertFalse(Hash::check('secret', $hash), 'A different APP_KEY must not verify'));
        $this->assertTrue(Hash::check('secret', $hash));
    }

    public function testStoredSeedPasswordsAreHashed(): void
    {
        $stored = DB::table('users')->where('email', 'admin@example.com')->first()->password;
        $this->assertTrue(str_starts_with($stored, '$argon2id$') || str_starts_with($stored, '$2y$'));
        $this->assertTrue(Hash::check('password', $stored));
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $token = Crypt::encrypt('+880 1700 000000');
        $this->assertNotContains('1700', $token);
        $this->assertSame('+880 1700 000000', Crypt::decrypt($token));
        $this->assertNotSame($token, Crypt::encrypt('+880 1700 000000'), 'Each encryption uses a fresh IV');
    }

    public function testDecryptFailsWithWrongKeyOrTampering(): void
    {
        $token = Crypt::encrypt('secret');
        $this->withKey(Key::generate(), fn() => $this->assertThrows(\RuntimeException::class, fn() => Crypt::decrypt($token)));

        $raw = base64_decode($token);
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);
        $this->assertThrows(\RuntimeException::class, fn() => Crypt::decrypt(base64_encode($raw)));
    }

    public function testMissingKeyIsRejected(): void
    {
        $this->withKey('', fn() => $this->assertThrows(\RuntimeException::class, fn() => Hash::make('x')));
    }

    protected function withKey(string $key, callable $callback): void
    {
        $original = $_ENV['APP_KEY'];
        $_ENV['APP_KEY'] = $key;
        putenv("APP_KEY=$key");
        try {
            $callback();
        } finally {
            $_ENV['APP_KEY'] = $original;
            putenv("APP_KEY=$original");
        }
    }
}
