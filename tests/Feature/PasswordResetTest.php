<?php

namespace Tests\Feature;

use System\Testing\HttpTestCase;

class PasswordResetTest extends HttpTestCase
{
    protected string $email = 'resetme@example.com';

    public function setUp(): void
    {
        parent::setUp();
        if (!$this->db('users')->where('email', $this->email)->exists()) {
            $this->db('users')->insert([
                'name' => 'Reset Me', 'email' => $this->email, 'role' => 'admin',
                'password' => \System\Security\Hash::make('old-password'),
            ]);
        }
    }

    /** The reset link from the newest email */
    protected function linkFromMail(): string
    {
        $mail = $this->lastMail();
        $this->assertTrue($mail !== null, 'an email was sent');
        preg_match('#href="([^"]+/reset-password/[a-f0-9]{64}[^"]*)"#', (string) $mail['html'], $m);
        $this->assertTrue(isset($m[1]), 'the email contains a reset link');
        return html_entity_decode($m[1]);
    }

    public function testFullResetFlow(): void
    {
        $this->get('/login')->assertSee('Forgot your password?');
        $this->get('/forgot-password')->assertStatus(200)->assertSee('Send reset link');

        $before = count($this->sentMails());
        $this->submit('/forgot-password', '/forgot-password', ['email' => $this->email])->assertRedirect('/forgot-password');
        $this->get('/forgot-password')->assertSee('If that email has an account, we have sent a link');
        $this->assertCount($before + 1, $this->sentMails());
        $this->assertMailSent($this->email, 'Reset your password');

        $mail = $this->lastMail();
        $this->assertSame([[$this->email, 'Reset Me']], $mail['to']);
        $this->assertSame('Reset your password', $mail['subject']);
        $this->assertContains('only once', $mail['text']);

        // The token is stored hashed, never as sent
        $link = $this->linkFromMail();
        preg_match('#/reset-password/([a-f0-9]{64})#', $link, $m);
        $stored = $this->db('password_resets')->where('email', $this->email)->first();
        $this->assertSame(hash('sha256', $m[1]), $stored->token_hash);

        $path = parse_url($link, PHP_URL_PATH) . '?' . parse_url($link, PHP_URL_QUERY);
        $this->get($path)->assertStatus(200)->assertSee('Choose a new password.');

        // Too short / not confirmed
        $this->submit($path, '/reset-password', ['token' => $m[1], 'email' => $this->email, 'password' => 'short', 'password_confirmation' => 'short']);
        $this->get($path)->assertSee('The password must be at least 8 characters.');

        $this->submit($path, '/reset-password', ['token' => $m[1], 'email' => $this->email, 'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass'])
            ->assertRedirect('/login');
        $this->get('/login')->assertSee('Your password has been reset.');

        // New password works, old one does not
        $this->submit('/login', '/login', ['email' => $this->email, 'password' => 'old-password'])->assertRedirect('/login');
        $this->submit('/login', '/login', ['email' => $this->email, 'password' => 'brand-new-pass'])->assertRedirect('/dashboard');

        // Single use: the link is dead now
        $this->cookies = [];
        $this->get($path)->assertSee('This reset link is invalid or has expired.');
    }

    public function testUnknownEmailGetsTheSameAnswerAndNoMail(): void
    {
        $before = count($this->sentMails());
        $this->submit('/forgot-password', '/forgot-password', ['email' => 'nobody-here@example.com'])->assertRedirect('/forgot-password');
        $this->get('/forgot-password')->assertSee('If that email has an account, we have sent a link');
        $this->assertCount($before, $this->sentMails(), 'no email for an unknown address');
    }

    public function testWrongAndExpiredTokensAreRejected(): void
    {
        $this->submit('/forgot-password', '/forgot-password', ['email' => $this->email]);
        $link = $this->linkFromMail();
        preg_match('#/reset-password/([a-f0-9]{64})#', $link, $m);

        $wrong = str_repeat('a', 64);
        $this->get("/reset-password/$wrong?email=" . urlencode($this->email))->assertSee('This reset link is invalid or has expired.');
        $this->submit('/forgot-password', '/reset-password', ['token' => $wrong, 'email' => $this->email, 'password' => 'whatever123', 'password_confirmation' => 'whatever123'])
            ->assertRedirect('/forgot-password');

        // Make the real token 2 hours old
        $this->db('password_resets')->where('email', $this->email)->update(['created_at' => date('Y-m-d H:i:s', time() - 7200)]);
        $this->get(parse_url($link, PHP_URL_PATH) . '?email=' . urlencode($this->email))->assertSee('This reset link is invalid or has expired.');
        $this->submit('/forgot-password', '/reset-password', ['token' => $m[1], 'email' => $this->email, 'password' => 'whatever123', 'password_confirmation' => 'whatever123']);
        $this->get('/forgot-password')->assertSee('This reset link is invalid or has expired.');
    }

    public function testResetRequestsAreThrottled(): void
    {
        $email = 'throttle-reset@example.com';
        for ($i = 0; $i < 3; $i++) {
            $this->submit('/forgot-password', '/forgot-password', ['email' => $email]);
        }
        $fourth = $this->submit('/forgot-password', '/forgot-password', ['email' => $email]);

        // Either limit may answer first: the per-email limit (3 per 15 min, a message on the form)
        // or the route's throttle:6,1 (429), which counts every reset request from this IP
        if ($fourth->status === 429) {
            $this->assertTrue((int) $fourth->header('Retry-After') > 0);
        } else {
            $this->get('/forgot-password')->assertSee('Too many reset requests');
        }
    }
}
