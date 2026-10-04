<?php

namespace Tests\Unit;

use System\Auth\PasswordBroker;
use System\Database\DB;
use System\Security\Hash;
use System\Testing\TestCase;

class PasswordBrokerTest extends TestCase
{
    public function tearDown(): void
    {
        DB::table('password_resets')->where('email', 'admin@example.com')->delete();
    }

    public function testLinksAreThrottledPerEmailAndTokensWork(): void
    {
        $broker = new PasswordBroker();
        $sent = [];
        $capture = function ($user, $link, $token) use (&$sent) {
            $sent[] = $token;
        };

        $email = 'unit-throttle-' . bin2hex(random_bytes(3)) . '@example.com';
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(PasswordBroker::RESET_LINK_SENT, $broker->sendResetLink($email, $capture));
        }
        $this->assertSame(PasswordBroker::THROTTLED, $broker->sendResetLink($email, $capture), '4th request within 15 minutes');
        $this->assertCount(0, $sent, 'unknown email: nothing is mailed');

        $this->assertSame(PasswordBroker::RESET_LINK_SENT, $broker->sendResetLink('admin@example.com', $capture));
        $this->assertCount(1, $sent);
        $token = $sent[0];

        $this->assertTrue($broker->tokenIsValid('admin@example.com', $token));
        $this->assertTrue($broker->tokenIsValid('ADMIN@example.com ', $token), 'email is normalised');
        $this->assertFalse($broker->tokenIsValid('admin@example.com', strrev($token)));

        $original = DB::table('users')->where('email', 'admin@example.com')->value('password');
        try {
            $this->assertSame(PasswordBroker::PASSWORD_RESET, $broker->reset('admin@example.com', $token, 'unit-new-pass'));
            $this->assertTrue(Hash::check('unit-new-pass', DB::table('users')->where('email', 'admin@example.com')->value('password')));
            $this->assertSame(PasswordBroker::INVALID_TOKEN, $broker->reset('admin@example.com', $token, 'again-pass'), 'single use');
        } finally {
            DB::table('users')->where('email', 'admin@example.com')->update(['password' => $original]);
        }
    }
}
