<?php
namespace Tests\Unit;

use System\Auth\Auth;
use System\Auth\JwtGuard;
use System\Auth\SessionGuard;
use System\Security\Jwt;
use System\Security\JwtException;
use System\Testing\TestCase;

class JwtTest extends TestCase
{
    public function testRoundTripAddsStandardClaims(): void
    {
        $token = Jwt::encode(['sub' => 7, 'role' => 'admin'], 600);
        $this->assertCount(3, explode('.', $token));

        $claims = Jwt::decode($token);
        $this->assertSame(7, $claims['sub']);
        $this->assertSame('admin', $claims['role']);
        $this->assertSame(32, strlen($claims['jti']));
        $this->assertSame(600, $claims['exp'] - $claims['iat']);
        $this->assertSame('HS256', json_decode(Jwt::base64UrlDecode(explode('.', $token)[0]), true)['alg']);
    }

    public function testTamperedPayloadIsRejected(): void
    {
        [$h, $p, $s] = explode('.', Jwt::encode(['sub' => 7]));
        $claims = json_decode(Jwt::base64UrlDecode($p), true);
        $claims['sub'] = 1; // try to become user 1
        $forged = $h . '.' . Jwt::base64UrlEncode(json_encode($claims)) . '.' . $s;

        $this->assertThrows(JwtException::class, fn() => Jwt::decode($forged));
    }

    public function testAlgNoneAndOtherAlgorithmsAreRejected(): void
    {
        $payload = Jwt::base64UrlEncode(json_encode(['sub' => 1, 'exp' => time() + 60]));
        foreach (['none', 'HS512', 'RS256'] as $alg) {
            $header = Jwt::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => $alg]));
            $this->assertThrows(JwtException::class, fn() => Jwt::decode("$header.$payload."), "alg $alg must be rejected");
        }
    }

    public function testExpiryNotBeforeAndRefreshMode(): void
    {
        $expired = Jwt::encode(['sub' => 1, 'iat' => time() - 7200, 'nbf' => time() - 7200, 'exp' => time() - 3600]);
        $this->assertThrows(JwtException::class, fn() => Jwt::decode($expired));
        $this->assertSame(1, Jwt::decode($expired, true)['sub'], 'refresh mode accepts an expired but genuine token');

        $future = Jwt::encode(['sub' => 1, 'nbf' => time() + 3600]);
        $this->assertThrows(JwtException::class, fn() => Jwt::decode($future));
    }

    public function testWrongSecretOrIssuerIsRejected(): void
    {
        $token = Jwt::encode(['sub' => 1], 60, 'secret-one');
        $this->assertSame(1, Jwt::decode($token, false, 'secret-one')['sub']);
        $this->assertThrows(JwtException::class, fn() => Jwt::decode($token, false, 'secret-two'));

        $other = Jwt::encode(['sub' => 1, 'iss' => 'https://other-app.example']);
        $this->assertThrows(JwtException::class, fn() => Jwt::decode($other));
    }

    public function testMalformedTokens(): void
    {
        foreach (['', 'abc', 'a.b', 'a.b.c.d', '!!!.???.***'] as $bad) {
            $this->assertThrows(JwtException::class, fn() => Jwt::decode($bad), "'$bad' must be rejected");
        }
    }

    public function testGuardsComeFromConfig(): void
    {
        Auth::reset();
        $this->assertTrue(Auth::guard('web') instanceof SessionGuard);
        $this->assertTrue(Auth::guard('api') instanceof JwtGuard);
        $this->assertSame('web', Auth::defaultGuard());
        $this->assertThrows(\InvalidArgumentException::class, fn() => Auth::guard('nope'));

        Auth::shouldUse('api');
        $this->assertSame('api', Auth::defaultGuard());
        Auth::reset();
    }

    public function testProviderConditionsRestrictUsers(): void
    {
        $GLOBALS['config']['auth']['providers']['admins'] = ['table' => 'users', 'where' => ['role' => 'admin']];
        $GLOBALS['config']['auth']['guards']['admin'] = ['driver' => 'session', 'provider' => 'admins'];
        Auth::reset();
        try {
            $provider = Auth::guard('admin')->provider();
            $this->assertSame('admin@example.com', $provider->findByCredentials('admin@example.com', 'password')->email);
            $this->assertNull($provider->findByCredentials('rahim1@example.com', 'password'), 'customers are outside the admins provider');
            $this->assertNull($provider->findByCredentials('admin@example.com', 'wrong'));
        } finally {
            unset($GLOBALS['config']['auth']['providers']['admins'], $GLOBALS['config']['auth']['guards']['admin']);
            Auth::reset();
        }
    }
}
