<?php
namespace Tests\Feature;

use System\Testing\HttpTestCase;

class PagesTest extends HttpTestCase
{
    public function testWelcomePageIsPublic(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('lightweight PHP MVC framework')->assertSee('Sign in');
    }

    public function testSignedInPagesLoadWithoutPhpNotices(): void
    {
        $this->loginAsAdmin();
        foreach (['/', '/home', '/system'] as $path) {
            $response = $this->get($path);
            $this->assertSame(200, $response->status, "GET $path returned {$response->status}");
            $this->assertNotContains('Warning:', $response->body, "PHP warning on $path");
            $this->assertNotContains('Deprecated:', $response->body, "PHP deprecation on $path");
        }
        $this->get('/system')->assertSee('Connected')->assertSee('login_attempts');
    }

    public function testErrorPages(): void
    {
        $this->get('/does-not-exist')->assertStatus(404)->assertSee('Page Not Found');
        $this->get('/logout')->assertStatus(405);
    }

    public function testCsrfFailureIsLoggedAsWarning(): void
    {
        $this->get('/login');
        $this->post('/login', ['email' => 'x@example.com', 'password' => 'x'])->assertStatus(419);

        $log = file_get_contents(BASE_PATH . '/storage/logs/testing/bongo-' . date('Y-m-d') . '.log');
        $this->assertContains('WARNING: HTTP 419 on POST /login', $log);
    }
}
