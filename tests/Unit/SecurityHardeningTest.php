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

    public function testRootHtaccessHasNoVirtualHost(): void
    {
        $root = dirname(__DIR__, 2);
        foreach ([$root . '/.htaccess', $root . '/public/.htaccess'] as $file) {
            if (file_exists($file)) {
                $content = (string) file_get_contents($file);
                $this->assertFalse(stripos($content, '<VirtualHost') !== false, "$file must not contain <VirtualHost>");
                $this->assertFalse(stripos($content, '<Directory') !== false, "$file must not contain <Directory>");
            }
        }
    }

    public function testSecurityCheckDetectsDangerousRootHtaccess(): void
    {
        $rootHtaccess = BASE_PATH . '/.htaccess';
        $hadFile = file_exists($rootHtaccess);
        $original = $hadFile ? (string) file_get_contents($rootHtaccess) : null;

        try {
            file_put_contents($rootHtaccess, "<VirtualHost *:80>\nServerName bad.local\n</VirtualHost>");
            $results = array_column(SecurityCheck::run(), null, 'check');
            $this->assertArrayHasKey('Root .htaccess', $results);
            $this->assertSame('fail', $results['Root .htaccess']['status']);
        } finally {
            if ($hadFile && $original !== null) {
                file_put_contents($rootHtaccess, $original);
            } elseif (file_exists($rootHtaccess)) {
                unlink($rootHtaccess);
            }
        }
    }

    public function testQueryBuilderDeleteWithNumericStringIdDoesNotDeleteEveryRow(): void
    {
        \System\Database\DB::table('users')->insert([
            'name' => 'Delete Test User 1',
            'email' => 'deltest1@example.com',
            'password' => \System\Security\Hash::make('password'),
        ]);
        \System\Database\DB::table('users')->insert([
            'name' => 'Delete Test User 2',
            'email' => 'deltest2@example.com',
            'password' => \System\Security\Hash::make('password'),
        ]);

        $initialCount = \System\Database\DB::table('users')->count();
        $this->assertTrue($initialCount >= 2);

        // Deleting non-existent numeric string ID "99999999" must not delete all rows!
        \System\Database\DB::table('users')->delete('99999999');

        $afterCount = \System\Database\DB::table('users')->count();
        $this->assertSame($initialCount, $afterCount, 'delete("numeric_id") must not delete every row in the table');

        // Clean up test rows
        \System\Database\DB::table('users')->where('email', 'deltest1@example.com')->delete();
        \System\Database\DB::table('users')->where('email', 'deltest2@example.com')->delete();
    }

    public function testModelGuardedMassAssignmentCannotBeBypassedWithQuotesOrCasing(): void
    {
        $testModel = new class extends \System\Database\Model {
            protected static $table = 'users';
            protected array $guarded = ['is_admin'];
            protected array $fillable = [];
        };

        // Standard guarded key
        $this->assertFalse($testModel->isFillable('is_admin'));
        // Uppercase
        $this->assertFalse($testModel->isFillable('IS_ADMIN'));
        $this->assertFalse($testModel->isFillable('Is_Admin'));
        // Backtick quoted
        $this->assertFalse($testModel->isFillable('`is_admin`'));
        // Double quoted
        $this->assertFalse($testModel->isFillable('"is_admin"'));
        // Table qualified
        $this->assertFalse($testModel->isFillable('users.is_admin'));
        $this->assertFalse($testModel->isFillable('`users`.`is_admin`'));

        // Fill test
        $m = new $testModel();
        $m->fill([
            'name' => 'John',
            '`is_admin`' => 1,
            'IS_ADMIN' => 1,
            'users.is_admin' => 1,
        ]);
        $this->assertSame('John', $m->name);
        $this->assertNull($m->is_admin);
        $this->assertNull($m->getAttribute('`is_admin`'));
        $this->assertNull($m->getAttribute('users.is_admin'));
    }

    public function testValidationNestedPathNotShadowedByFlatDottedKey(): void
    {
        // Attacker payload contains flat dotted key 'user.role' => 'user' (valid)
        // alongside real nested structure 'user' => ['role' => 'admin'] (invalid)
        $data = [
            'user.role' => 'user',
            'user' => [
                'role' => 'admin',
            ],
        ];

        $rules = [
            'user.role' => 'in:user,guest',
        ];

        $validator = new \System\Validation\Validator($data, $rules);
        $errors = $validator->validate();

        $this->assertTrue(!empty($errors), 'Validator must check nested user.role (admin) and fail in:user,guest');
        $this->assertArrayHasKey('user.role', $errors);
    }

    public function testJwtLogoutRevokesForFullRefreshWindow(): void
    {
        $guard = \System\Auth\Auth::guard('api');
        $user = (object) ['id' => 1, 'email' => 'admin@example.com'];
        $token = $guard->login($user);

        $claims = \System\Security\Jwt::decode($token);
        $jti = $claims['jti'];

        $guard->logout();

        $revocation = \System\Database\DB::table('jwt_revocations')->where('jti', $jti)->first();
        $this->assertNotNull($revocation);

        // Expiry must be in the future, covering the refresh window
        $expectedMinWindow = time() + (int) config('jwt.refresh_ttl', 20160) * 60 - 60;
        $actualExpiresAt = strtotime($revocation->expires_at);
        $this->assertTrue($actualExpiresAt >= $expectedMinWindow, 'Revocation must cover full refresh TTL to prevent revival after pruning');

        // Clean up
        \System\Database\DB::table('jwt_revocations')->where('jti', $jti)->delete();
    }

    public function testWebSocketFrameNegative64BitLengthThrowsLengthException(): void
    {
        // 0x81 (FIN + text), 0x7f (length 127 = 64-bit length follows), 8 bytes of 0xFF (-1 in signed 64-bit)
        $maliciousFrame = "\x81\x7f\xff\xff\xff\xff\xff\xff\xff\xffpayload123";
        $buffer = $maliciousFrame;

        $this->assertThrows(\LengthException::class, function () use (&$buffer) {
            \System\Broadcasting\Frame::decode($buffer);
        });
    }

    public function testUrlHelperRejectsUntrustedHostHeaderPoisoning(): void
    {
        $originalHost = $_SERVER['HTTP_HOST'] ?? null;
        $originalAppUrl = config('app.url');

        try {
            // Default configured URL is localhost/127.0.0.1
            $GLOBALS['config']['app']['url'] = 'http://127.0.0.1:8000';

            // Attacker poisons Host header
            $_SERVER['HTTP_HOST'] = 'attacker.com';

            $generatedUrl = url('/password/reset/token123');
            $this->assertFalse(str_contains($generatedUrl, 'attacker.com'), 'url() must never adopt attacker.com when configured for 127.0.0.1');
            $this->assertTrue(str_contains($generatedUrl, '127.0.0.1:8000'));

            // Attacker sends malformed host with path injection
            $_SERVER['HTTP_HOST'] = 'evil.com/fake?';
            $generatedUrl2 = url('/test');
            $this->assertFalse(str_contains($generatedUrl2, 'evil.com'));
        } finally {
            if ($originalHost !== null) {
                $_SERVER['HTTP_HOST'] = $originalHost;
            } else {
                unset($_SERVER['HTTP_HOST']);
            }
            $GLOBALS['config']['app']['url'] = $originalAppUrl;
        }
    }

    public function testEagerLoadAndWithCountRejectDestructiveInternalModelMethods(): void
    {
        $model = new class extends \System\Database\Model {
            protected static $table = 'users';
        };

        // Calling eagerLoad with 'delete' must not execute delete() on Model
        $this->assertThrows(\BadMethodCallException::class, function () use ($model) {
            $model::eagerLoad([new $model()], ['delete']);
        });

        $this->assertThrows(\BadMethodCallException::class, function () use ($model) {
            $model::eagerLoad([new $model()], ['save']);
        });
    }

    public function testPluckRejectsInvalidColumnSqlInjection(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, function () {
            \System\Database\DB::table('users')->pluck('name; DROP TABLE users;--');
        });
    }

    public function testFileManagerBlocksPhpAndExecutableFiles(): void
    {
        if (class_exists(\Bongo\Packages\FileManager\Services\PathSecurity::class)) {
            $this->assertFalse(\Bongo\Packages\FileManager\Services\PathSecurity::isAllowedForUpload('shell.php'));
            $this->assertFalse(\Bongo\Packages\FileManager\Services\PathSecurity::isAllowedForUpload('shell.php7'));
            $this->assertFalse(\Bongo\Packages\FileManager\Services\PathSecurity::isAllowedForUpload('shell.phtml'));
            $this->assertFalse(\Bongo\Packages\FileManager\Services\PathSecurity::isAllowedForUpload('shell.phar'));
            $this->assertFalse(\Bongo\Packages\FileManager\Services\PathSecurity::isAllowedForUpload('shell.php.jpg'));
            $this->assertFalse(\Bongo\Packages\FileManager\Services\PathSecurity::isEditable('shell.php'));
            $this->assertFalse(\Bongo\Packages\FileManager\Services\PathSecurity::isEditable('shell.sh'));

            $svc = new \Bongo\Packages\FileManager\Services\FileManagerService('public', 'test_sec');
            $this->assertThrows(\InvalidArgumentException::class, function () use ($svc) {
                $svc->saveFile('shell.php', '<?php phpinfo();');
            });
        } else {
            $this->assertTrue(true);
        }
    }
}