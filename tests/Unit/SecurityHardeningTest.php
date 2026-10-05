<?php

namespace Tests\Unit;

use System\Http\Request;
use System\Http\Session;
use System\Security\SecurityCheck;
use System\Testing\TestCase;

class SecurityHardeningTest extends TestCase
{
    protected array $server;
    protected array $config;

    public function setUp(): void
    {
        $this->server = $_SERVER;
        $this->config = $GLOBALS['config'];
    }

    public function tearDown(): void
    {
        $_SERVER = $this->server;
        $GLOBALS['config'] = $this->config;
        $_SESSION = [];
    }

    public function testClientIpIgnoresForwardedHeadersFromStrangers(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';
        $GLOBALS['config']['security']['trusted_proxies'] = [];
        $this->assertSame('203.0.113.9', Request::ip(), 'a client cannot fake its IP');
    }

    public function testClientIpThroughTrustedProxies(): void
    {
        $GLOBALS['config']['security']['trusted_proxies'] = ['10.0.0.1', '10.0.0.2'];
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';

        // client, then a second proxy of ours
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.7, 10.0.0.2';
        $this->assertSame('198.51.100.7', Request::ip());

        // a spoofed value the client prepended is skipped: the closest untrusted hop wins
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '6.6.6.6, 198.51.100.7';
        $this->assertSame('198.51.100.7', Request::ip());

        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'not-an-ip';
        $this->assertSame('10.0.0.1', Request::ip());
    }

    public function testHttpsDetection(): void
    {
        $GLOBALS['config']['security']['trusted_proxies'] = ['10.0.0.1'];
        unset($_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = '80';

        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        $this->assertFalse(Request::isSecure(), 'X-Forwarded-Proto from a stranger is ignored');

        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $this->assertTrue(Request::isSecure());

        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(Request::isSecure());
    }

    public function testIdleAndAbsoluteSessionLimits(): void
    {
        $GLOBALS['config']['session']['idle_minutes'] = 30;
        $GLOBALS['config']['session']['absolute_minutes'] = 240;
        $now = 1_800_000_000;

        $_SESSION = ['auth_web' => 1, '_last_activity' => $now - 60, '_started_at' => $now - 3600];
        $this->assertFalse(Session::enforceLifetime($now));
        $this->assertSame(1, $_SESSION['auth_web'], 'active session is kept');
        $this->assertSame($now, $_SESSION['_last_activity']);

        $_SESSION = ['auth_web' => 1, '_last_activity' => $now - 31 * 60, '_started_at' => $now - 3600];
        $this->assertTrue(Session::enforceLifetime($now), 'idle longer than 30 minutes');
        $this->assertFalse(isset($_SESSION['auth_web']), 'the user is signed out');

        $_SESSION = ['auth_web' => 1, '_last_activity' => $now - 60, '_started_at' => $now - 241 * 60];
        $this->assertTrue(Session::enforceLifetime($now), 'older than the absolute limit, even though active');
    }

    public function testSecurityCheckFlagsDangerousProductionSettings(): void
    {
        $GLOBALS['config']['app']['env'] = 'production';
        $GLOBALS['config']['app']['debug'] = true;
        $GLOBALS['config']['app']['url'] = 'http://shop.example.com';
        $GLOBALS['config']['security']['trusted_proxies'] = ['*'];

        $results = array_column(SecurityCheck::run(), null, 'check');
        $this->assertSame('fail', $results['Debug mode']['status']);
        $this->assertSame('fail', $results['HTTPS']['status']);
        $this->assertSame('warn', $results['Trusted proxies']['status']);
        $this->assertSame('fail', $results['Demo admin login']['status'], 'the seeded demo password is detected');
        $this->assertSame('fail', SecurityCheck::summary(array_values($results)));

        $GLOBALS['config']['app']['debug'] = false;
        $GLOBALS['config']['app']['url'] = 'https://shop.example.com';
        $results = array_column(SecurityCheck::run(), null, 'check');
        $this->assertSame('ok', $results['Debug mode']['status']);
        $this->assertSame('ok', $results['HTTPS']['status']);
        $this->assertSame('ok', $results['APP_KEY']['status']);
    }

    public function testRedirectSanitizesCrlfHeaderInjection(): void
    {
        $dirty = "https://example.com/page\r\nSet-Cookie: session=hacked\r\n";
        $clean = str_replace(["\r", "\n", "\0"], '', $dirty);
        $this->assertSame('https://example.com/pageSet-Cookie: session=hacked', $clean);
        $this->assertFalse(str_contains($clean, "\r"));
        $this->assertFalse(str_contains($clean, "\n"));
    }

    public function testCorsOriginSanitizesCrlf(): void
    {
        $dirty = "https://example.com\r\nInjected-Header: evil";
        $clean = str_replace(["\r", "\n", "\0"], '', $dirty);
        $this->assertSame('https://example.comInjected-Header: evil', $clean);
        $this->assertFalse(str_contains($clean, "\r"));
        $this->assertFalse(str_contains($clean, "\n"));
    }
}
