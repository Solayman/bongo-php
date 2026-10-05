<?php

namespace Tests\Feature;

use System\Testing\HttpTestCase;

class DashboardTest extends HttpTestCase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->loginAsAdmin();
    }

    public function tearDown(): void
    {
        $this->db('users')->where('id', 1)->update(['name' => 'Admin']);
        parent::tearDown();
    }

    public function testEveryAdminPageLoads(): void
    {
        foreach (['/', '/dashboard', '/profile', '/system'] as $path) {
            $response = $this->get($path);
            $this->assertSame(200, $response->status, "GET $path returned {$response->status}");
            $this->assertNotContains('Warning:', $response->body, "PHP warning on $path");
            $this->assertNotContains('Deprecated:', $response->body, "PHP deprecation on $path");
        }

        $log = (string) @file_get_contents(BASE_PATH . '/storage/logs/testing/bongo-' . date('Y-m-d') . '.log');
        $this->assertNotContains('NOTICE: PHP', $log, 'A page raised a PHP notice');
        $this->assertNotContains('WARNING: PHP', $log, 'A page raised a PHP warning');
    }

    public function testDashboardShowsSystemInfo(): void
    {
        $this->get('/')
            ->assertSee('Admin Dashboard')
            ->assertSee('Admin')
            ->assertSee('PHP');
    }

    public function testUnknownPageIs404(): void
    {
        $this->get('/does-not-exist')->assertStatus(404)->assertSee('Page Not Found');
    }

    public function testDebugFooterShowsQueries(): void
    {
        $response = $this->get('/');
        $response->assertSee('bongo-debugbar');
        $response->assertSee('Queries');
        $response->assertSee('SELECT COUNT(*) AS aggregate FROM users');
    }

    public function testProfileUpdate(): void
    {
        $this->submit('/profile', '/profile', [
            '_method' => 'PUT',
            'name'    => 'Super Admin',
            'email'   => 'admin@example.com',
        ])->assertRedirect('/profile');

        $this->assertSame('Super Admin', $this->db('users')->where('id', 1)->first()->name);
    }
}
