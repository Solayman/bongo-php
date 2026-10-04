<?php

namespace Tests\Feature;

use System\Http\Maintenance;
use System\Testing\HttpTestCase;

class MaintenanceTest extends HttpTestCase
{
    public function tearDown(): void
    {
        Maintenance::up();
    }

    public function testDownShowsTheBackSoonPageAndUpEndsIt(): void
    {
        Maintenance::down('Upgrading the shop', 120);
        try {
            $page = $this->get('/shop')->assertStatus(503)->assertSee('back soon')->assertSee('Upgrading the shop');
            $this->assertSame('120', $page->header('Retry-After'));

            $api = $this->get('/api/health', ['Accept' => 'application/json'])->assertStatus(503);
            $this->assertSame('Upgrading the shop', json_decode($api->body, true)['message']);
        } finally {
            Maintenance::up();
        }
        $this->get('/shop')->assertStatus(200);
    }

    public function testTheSecretUrlLetsOneBrowserIn(): void
    {
        Maintenance::down('', 60, 'let-me-in');
        try {
            $this->get('/shop')->assertStatus(503);
            $this->get('/let-me-in')->assertRedirect('/');
            $this->assertTrue(isset($this->cookies[Maintenance::COOKIE]), 'bypass cookie set');
            $this->get('/shop')->assertStatus(200);

            $this->cookies = [Maintenance::COOKIE => str_repeat('a', 64)];   // a forged cookie
            $this->get('/shop')->assertStatus(503);
        } finally {
            Maintenance::up();
        }
    }
}
