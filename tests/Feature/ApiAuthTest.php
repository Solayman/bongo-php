<?php

namespace Tests\Feature;

use System\Testing\HttpTestCase;
use System\Testing\TestResponse;

class ApiAuthTest extends HttpTestCase
{
    protected function api(string $method, string $path, array $data = [], ?string $token = null): TestResponse
    {
        $headers = ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        if ($token !== null) {
            $headers['Authorization'] = "Bearer $token";
        }
        return $this->jsonRequest($method, $path, $data, $headers);
    }

    protected function json(TestResponse $response): array
    {
        return json_decode($response->body, true) ?? [];
    }

    protected function login(string $email = 'admin@example.com', string $password = 'password'): string
    {
        $response = $this->api('POST', '/api/auth/login', ['email' => $email, 'password' => $password]);
        $response->assertStatus(200);
        return $this->json($response)['access_token'];
    }

    public function testLoginReturnsBearerTokenWithoutPasswordHash(): void
    {
        $response = $this->api('POST', '/api/auth/login', ['email' => 'admin@example.com', 'password' => 'password']);
        $response->assertStatus(200);
        $body = $this->json($response);

        $this->assertSame('Bearer', $body['token_type']);
        $this->assertCount(3, explode('.', $body['access_token']));
        $this->assertTrue($body['expires_in'] > 3500 && $body['expires_in'] <= 3600);
        $this->assertSame('admin@example.com', $body['user']['email']);
        $this->assertTrue(!isset($body['user']['password']), 'the password hash is never sent');
        $this->assertNull($response->header('Set-Cookie'), 'the API is stateless: no session cookie');
    }

    public function testMeWithTokenAndProtectedResource(): void
    {
        $token = $this->login();

        $this->assertSame('admin@example.com', $this->json($this->api('GET', '/api/auth/me', [], $token)->assertStatus(200))['user']['email']);

        $products = $this->json($this->api('GET', '/api/products?per_page=5&q=BG-10', [], $token)->assertStatus(200));
        $this->assertCount(5, $products['data']);
        $this->assertSame(1, $products['meta']['current_page']);
        $this->assertTrue(is_float($products['data'][0]['price']));

        $this->api('GET', '/api/products/999999', [], $token)->assertStatus(404);
    }

    public function testMissingOrBadTokensAre401(): void
    {
        $response = $this->api('GET', '/api/auth/me');
        $response->assertStatus(401);
        $this->assertSame('Bearer', $response->header('WWW-Authenticate'));
        $this->assertContains('No token provided', $this->json($response)['error']['message']);

        $this->assertContains('Invalid token signature', $this->json($this->api('GET', '/api/auth/me', [], $this->login() . 'x')->assertStatus(401))['error']['message']);
        $this->api('GET', '/api/products', [], 'not-a-jwt')->assertStatus(401);
    }

    public function testBrowserSessionDoesNotAuthenticateTheApi(): void
    {
        $this->loginAsAdmin();
        $this->get('/api/auth/me', ['Accept' => 'application/json'])->assertStatus(401);
    }

    public function testWrongCredentialsAndValidation(): void
    {
        $this->api('POST', '/api/auth/login', ['email' => 'admin@example.com', 'password' => 'nope'])->assertStatus(401);

        $response = $this->api('POST', '/api/auth/login', ['email' => 'not-an-email']);
        $response->assertStatus(422);
        $errors = $this->json($response)['errors'];
        $this->assertSame('The email must be a valid email address.', $errors['email']);
        $this->assertSame('The password field is required.', $errors['password']);
    }

    public function testRefreshIssuesNewTokenAndRevokesTheOld(): void
    {
        $old = $this->login();
        sleep(1); // a different iat, so the new token differs

        $new = $this->json($this->api('POST', '/api/auth/refresh', [], $old)->assertStatus(200))['access_token'];
        $this->assertNotSame($old, $new);

        $this->api('GET', '/api/auth/me', [], $new)->assertStatus(200);
        $this->assertContains('revoked', $this->json($this->api('GET', '/api/auth/me', [], $old)->assertStatus(401))['error']['message']);
        $this->api('POST', '/api/auth/refresh', [], $old)->assertStatus(401);
    }

    public function testLogoutRevokesToken(): void
    {
        $token = $this->login();
        $this->assertSame('Token revoked.', $this->json($this->api('POST', '/api/auth/logout', [], $token)->assertStatus(200))['message']);
        $this->api('GET', '/api/auth/me', [], $token)->assertStatus(401);
    }

    public function testCustomersCanUseTheApi(): void
    {
        $token = $this->login('rahim1@example.com', 'password');
        $this->assertSame('customer', $this->json($this->api('GET', '/api/auth/me', [], $token))['user']['role']);
    }

    public function testApiLoginIsThrottled(): void
    {
        $this->db('users')->insert(['name' => 'Api Lock', 'email' => 'apilock@example.com', 'role' => 'customer', 'password' => \System\Security\Hash::make('password')]);
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', '/api/auth/login', ['email' => 'apilock@example.com', 'password' => "x$i"])->assertStatus(401);
        }
        $response = $this->api('POST', '/api/auth/login', ['email' => 'apilock@example.com', 'password' => 'password']);
        $response->assertStatus(429);
        $this->assertTrue((int) $response->header('Retry-After') > 0);
    }

    public function testWebLoginUsesItsOwnGuardSessionKey(): void
    {
        // Signing in on the web does not hand out API tokens, and vice versa
        $this->loginAsAdmin();
        $this->get('/')->assertStatus(200);
        $this->api('GET', '/api/products')->assertStatus(401);
    }
}
