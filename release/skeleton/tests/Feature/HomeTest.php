<?php
namespace Tests\Feature;

use System\Testing\HttpTestCase;

class HomeTest extends HttpTestCase
{
    public function testWelcomePage(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('BongoMVC');
        $this->get('/?name=Rahim')->assertSee('Rahim');
    }

    public function testApiHealth(): void
    {
        $this->get('/api/health')->assertStatus(200)->assertSee('"status":"ok"');
    }
}
