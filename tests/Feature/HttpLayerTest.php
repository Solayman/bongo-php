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
        $id = $this->db('categories')->insertGetId(['name' => 'Spoof', 'slug' => 'spoof-test']);

        // A real DELETE without a token is refused by CSRF
        $this->request('DELETE', "/categories/$id")->assertStatus(419);
        $this->assertSame('Spoof', $this->db('categories')->where('id', $id)->first()->name);

        // An HTML form: POST + _method=DELETE + token
        $this->submit('/categories', "/categories/$id", ['_method' => 'DELETE'])->assertRedirect('/categories');
        $this->assertNull($this->db('categories')->where('id', $id)->first());

        // A plain POST to a DELETE-only path is 405, with an Allow header
        $response = $this->submit('/categories', '/categories/1');
        $response->assertStatus(405);
        $this->assertContains('DELETE', (string) $response->header('Allow'));
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
        $this->get('/products')
            ->assertSee('action="' . \System\Testing\Runner::$baseUrl . '/products/20"')
            ->assertSee('name="_method" value="DELETE"');
    }
}
