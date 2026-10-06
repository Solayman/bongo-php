<?php

namespace Tests\Feature;

use System\Testing\HttpTestCase;

class HttpLayerTest extends HttpTestCase
{
    public function testSecurityHeadersOnEveryResponse(): void
    {
        foreach (['/login', '/does-not-exist', '/api/health'] as $path) {
            $response = $this->get($path);
            $this->assertSame('nosniff', $response->header('X-Content-Type-Options'), "nosniff on $path");
            $this->assertSame('SAMEORIGIN', $response->header('X-Frame-Options'), "frame options on $path");
            $this->assertSame('strict-origin-when-cross-origin', $response->header('Referrer-Policy'));
            $this->assertNull($response->header('X-Powered-By'), 'PHP version must not be advertised');
        }
    }

    public function testApiRouteReturnsJsonWithCors(): void
    {
        $response = $this->get('/api/health', ['Origin' => 'https://spa.example.com', 'Accept' => 'application/json']);
        $response->assertStatus(200);
        $this->assertSame('ok', json_decode($response->body, true)['status']);
        $this->assertContains('application/json', (string) $response->header('Content-Type'));
        $this->assertSame('*', $response->header('Access-Control-Allow-Origin'));
    }

    public function testCorsPreflight(): void
    {
        $response = $this->request('OPTIONS', '/api/health', [], [
            'Origin' => 'https://spa.example.com',
            'Access-Control-Request-Method' => 'GET',
        ]);
        $response->assertStatus(204);
        $this->assertSame('*', $response->header('Access-Control-Allow-Origin'));
        $this->assertContains('DELETE', (string) $response->header('Access-Control-Allow-Methods'));
        $this->assertContains('Authorization', (string) $response->header('Access-Control-Allow-Headers'));
    }

    public function testWebRoutesSendNoCorsHeaders(): void
    {
        $this->assertNull($this->get('/login', ['Origin' => 'https://evil.example.com'])->header('Access-Control-Allow-Origin'));
    }

    public function testApiErrorsAreJson(): void
    {
        $response = $this->get('/api/nothing-here');
        $response->assertStatus(404);
        $this->assertSame(404, json_decode($response->body, true)['error']['status']);

        $html = $this->get('/nothing-here', ['Accept' => 'application/json']);
        $this->assertSame('Page Not Found', json_decode($html->body, true)['error']['message'], 'Accept: application/json gets JSON errors on web routes too');
    }

    public function testMethodSpoofingAndRealVerbs(): void
    {
        $this->loginAsAdmin();
        $token = $this->get('/profile')->csrfToken();

        // A real PUT without a token is refused by CSRF
        $this->request('PUT', '/profile')->assertStatus(419);

        // An HTML form: POST + _method=PUT + token
        $this->submit('/profile', '/profile', ['_method' => 'PUT', 'name' => 'Admin Spoof', 'email' => 'admin@example.com'])->assertRedirect('/profile');
        $this->assertSame('Admin Spoof', $this->db('users')->where('id', 1)->first()->name);
        $this->db('users')->where('id', 1)->update(['name' => 'Admin']);

        // A plain POST to a PUT-only path is 405, with an Allow header
        $response = $this->post('/profile', ['name' => 'Bad Post']);
        $response->assertStatus(405);
        $this->assertContains('PUT', (string) $response->header('Allow'));
    }

    public function testCsrfTokenAcceptedFromHeader(): void
    {
        $this->loginAsAdmin();
        $token = $this->get('/system')->csrfToken();

        $this->post('/system/log-test', [], ['X-CSRF-TOKEN' => $token])->assertRedirect('/system');
        $this->post('/system/log-test', [], ['X-CSRF-TOKEN' => 'wrong'])->assertStatus(419);
    }

    public function testNamedRoutesInRenderedPages(): void
    {
        $this->loginAsAdmin();
        $this->get('/profile')
            ->assertSee('action="' . \System\Testing\Runner::$baseUrl . '/profile"')
            ->assertSee('name="_method" value="PUT"');
    }

    public function testApiIsRateLimited(): void
    {
        $first = $this->get('/api/health');
        $this->assertSame('60', $first->header('X-RateLimit-Limit'));
        $remaining = (int) $first->header('X-RateLimit-Remaining');

        // Use up the rest of this minute's allowance
        for ($i = 0; $i < $remaining; $i++) {
            $this->get('/api/health');
        }

        $blocked = $this->get('/api/health');
        $blocked->assertStatus(429);
        $this->assertSame('0', $blocked->header('X-RateLimit-Remaining'));
        $this->assertTrue((int) $blocked->header('Retry-After') > 0);
        $this->assertSame('Too Many Requests', json_decode($blocked->body, true)['error']['message']);

        // Other routes have their own counter
        $this->jsonRequest('POST', '/api/auth/login', ['email' => 'nobody@example.com', 'password' => 'x'])->assertStatus(401);
    }

    public function testLanguageSwitchIsRememberedAndTranslatesPagesAndErrors(): void
    {
        $this->get('/login')->assertSee('Sign in to the admin dashboard')->assertSee('lang="en"');

        // ?lang=bn switches and is remembered in the session
        $this->get('/login?lang=bn')->assertSee('অ্যাডমিন ড্যাশবোর্ডে সাইন ইন করুন')->assertSee('<html lang="bn">');
        $this->get('/login')->assertSee('সাইন ইন');

        // Validation and auth messages in Bangla
        $this->submit('/login', '/login', ['email' => '', 'password' => '']);
        $this->get('/login')->assertSee('ইমেইল দেওয়া আবশ্যক।');
        $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'wrong']);
        $this->get('/login')->assertSee('এই ইমেইল বা পাসওয়ার্ড আমাদের রেকর্ডের সাথে মেলে না।');

        $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'password']);
        $this->get('/dashboard')->assertSee('আবার স্বাগতম, Admin!')->assertSee('ড্যাশবোর্ড');

        $this->get('/dashboard?lang=en')->assertSee('Dashboard');
    }

    public function testBrowserLanguageIsUsedWhenNothingWasChosen(): void
    {
        $this->get('/login', ['Accept-Language' => 'bn-BD,bn;q=0.9,en;q=0.8'])->assertSee('<html lang="bn">');
        $this->cookies = [];
        $this->get('/login', ['Accept-Language' => 'fr-FR,fr;q=0.9'])->assertSee('<html lang="en">');
        $this->cookies = [];
        $this->get('/login?lang=xx')->assertSee('<html lang="en">', 'unsupported codes are ignored');
    }

    public function testSessionCookieIsHardened(): void
    {
        $response = $this->get('/login');
        $cookie = (string) $response->header('Set-Cookie');
        $this->assertContains('bongo_session=', $cookie, 'custom cookie name, not PHPSESSID');
        $this->assertContains('HttpOnly', $cookie);
        $this->assertContains('SameSite=Lax', $cookie);
        $this->assertNotContains('PHPSESSID', implode(' ', $response->headers['set-cookie'] ?? []));
    }

    public function testMadeUpSessionIdsAreRejected(): void
    {
        // Session fixation: an attacker plants an id; strict mode refuses ids the server never created
        $this->cookies = ['bongo_session' => 'attackerchosenid123456'];
        $this->get('/login');
        $this->assertNotSame('attackerchosenid123456', $this->cookies['bongo_session'] ?? null);
    }

    public function testCspAndCrossOriginHeaders(): void
    {
        $response = $this->get('/login');
        $this->assertSame("default-src 'self'; frame-ancestors 'self'", $response->header('Content-Security-Policy'));
        $this->assertSame('same-origin', $response->header('Cross-Origin-Opener-Policy'));
    }

    public function testSystemPageShowsDeploymentChecklist(): void
    {
        $this->loginAsAdmin();
        $this->get('/system')->assertSee('Deployment checklist')->assertSee('APP_KEY')->assertSee('Demo admin login');
    }

    public function testStaticFilesAreServedFromPublic(): void
    {
        // The built-in server (php bongo serve, the test server) passes existing public/ files through
        $response = $this->get('/asset/js/bongo-socket.js');
        $this->assertSame(200, $response->status, 'assets load in development');
        $this->assertContains('BongoSocket', $response->body);
    }
}
