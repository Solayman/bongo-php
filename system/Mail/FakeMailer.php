<?php
namespace System\Mail;

use RuntimeException;

/**
 * Mail::fake(): emails are kept in memory instead of being sent, for tests:
 *   Mail::fake();
 *   ... code that sends mail ...
 *   $this->assertMailSent('rahim@example.com', 'Your order');
 */
class FakeMailer extends Mailer
{
    /** Each sent email as Message::toArray() */
    public array $sent = [];

    public function __construct()
    {
        parent::__construct(['driver' => 'fake', 'from' => (array) config('mail.from', [])]);
    }

    public function send(Message $message): bool
    {
        if (!$message->to) {
            throw new RuntimeException('An email needs at least one recipient');
        }
        $message->from ??= [(string) ($this->config['from']['address'] ?? 'no-reply@localhost'), $this->config['from']['name'] ?? null];
        $this->sent[] = $message->toArray();
        return true;
    }
}
