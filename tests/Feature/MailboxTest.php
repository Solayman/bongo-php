<?php

namespace Tests\Feature;

use System\Testing\HttpTestCase;

class MailboxTest extends HttpTestCase
{
    public function testSentEmailsCanBeReadOnTheSystemPage(): void
    {
        // Send a real email: a password reset for the admin
        $this->submit('/forgot-password', '/forgot-password', ['email' => 'admin@example.com'])->assertRedirect('/forgot-password');

        $this->loginAsAdmin();
        $page = $this->get('/system')->assertSee('id="mailbox"')->assertSee('Reset your password');
        $this->assertTrue((bool) preg_match('#/system/mail/(\d{8}_\d{6}_[a-f0-9]{8})"#', $page->body, $m), 'a link to the email');

        $this->get('/system/mail/' . $m[1])->assertStatus(200)
            ->assertSee('admin@example.com')
            ->assertSee('<iframe sandbox=""')
            ->assertSee('reset-password/')
            ->assertSee('Plain-text version');

        // Only mailbox file names: no paths, no other files
        $this->get('/system/mail/..%2F..%2F.env')->assertStatus(404);
        $this->get('/system/mail/20260101_000000_zzzzzzzz')->assertStatus(404);
    }

    public function testTheMailboxNeedsTheAdmin(): void
    {
        $this->get('/system/mail/20260101_000000_abcdef12')->assertRedirect('/login');
    }
}
