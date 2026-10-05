<?php

namespace Tests\Feature;

use System\Testing\HttpTestCase;

class RememberMeTest extends HttpTestCase
{
    /** Close the browser: the session cookie goes, the remember cookie stays */
    protected function closeBrowser(): void
    {
        $this->cookies = array_intersect_key($this->cookies, ['remember_web' => 1, 'remember_customer' => 1]);
    }

    public function testWithoutRememberTheUserIsSignedOutWhenTheBrowserCloses(): void
    {
        $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'password'])->assertRedirect('/');
        $this->assertSame(false, isset($this->cookies['remember_web']), 'no cookie without the checkbox');
        $this->closeBrowser();
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function testRememberMeKeepsTheUserSignedIn(): void
    {
        $this->get('/login')->assertSee('name="remember"');
        $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'password', 'remember' => '1'])->assertRedirect('/');

        $cookie = $this->cookies['remember_web'] ?? '';
        $this->assertTrue((bool) preg_match('/^\d+(\||%7C)[a-f0-9]{64}$/', $cookie), "cookie: $cookie");
        $token = explode('|', urldecode($cookie))[1];
        $stored = $this->db('users')->where('email', 'admin@example.com')->first()->remember_token;
        $this->assertSame(hash('sha256', $token), $stored, 'only the hash is stored');

        // Browser closed and opened again: still signed in
        $this->closeBrowser();
        $this->get('/dashboard')->assertStatus(200);

        // Sign out: the cookie and the stored token are gone, so the cookie no longer works
        $page = $this->get('/dashboard');
        $this->post('/logout', ['_token' => $page->csrfToken()])->assertRedirect('/login');
        $this->assertSame(null, $this->db('users')->where('email', 'admin@example.com')->first()->remember_token);
        $this->cookies = ['remember_web' => $cookie];
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function testForgedCookiesAreRejected(): void
    {
        $id = $this->db('users')->where('email', 'admin@example.com')->first()->id;
        foreach (["$id|" . str_repeat('a', 64), "$id|nothex", 'garbage'] as $forged) {
            $this->cookies = ['remember_web' => $forged];
            $this->get('/dashboard')->assertRedirect('/login');
        }
    }
}
