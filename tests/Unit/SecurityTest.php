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

    public function testCsrfTokenGraceWindow(): void
    {
        $_SESSION = [];
        $token1 = \System\Http\Session::token();
        $this->assertTrue(\System\Http\Session::isValidToken($token1));

        // Regenerate token (e.g. rapid sequential POST or in-flight request)
        \System\Http\Session::regenerateToken();
        $token2 = \System\Http\Session::token();
        $this->assertNotSame($token1, $token2);

        // Within grace window, both new token and previous token are valid
        $this->assertTrue(\System\Http\Session::isValidToken($token2));
        $this->assertTrue(\System\Http\Session::isValidToken($token1));

        // Invalid token still fails
        $this->assertFalse(\System\Http\Session::isValidToken('invalid-token'));
        $this->assertFalse(\System\Http\Session::isValidToken(''));

        // Manually expire previous tokens
        $_SESSION['_previous_tokens'][0]['expires_at'] = time() - 1;
        $this->assertFalse(\System\Http\Session::isValidToken($token1));
    }

    public function testVerifyCsrfTokenExcludesApiRoutes(): void
    {
        $middleware = new \App\Http\Middleware\VerifyCsrfToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/api/devices/ping';
        unset($_POST['_token'], $_SERVER['HTTP_X_CSRF_TOKEN']);

        // Should not throw HttpException(419) because /api/* is excluded
        $middleware->handle();
        $this->assertTrue(true);
    }
}
