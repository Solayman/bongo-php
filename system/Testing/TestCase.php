<?php
namespace System\Testing;

/**
 * Base class for tests. Public methods named test* are run by `php bongo test`.
 */
abstract class TestCase
{
    public int $assertions = 0;

    public function setUp(): void {}

    public function tearDown(): void {}

    // ------------------------------------------------------------------
    // Assertions
    // ------------------------------------------------------------------

    protected function assert(bool $condition, string $message): void
    {
        $this->assertions++;
        if (!$condition) {
            throw new AssertionFailed($message);
        }
    }

    protected function assertTrue($value, string $message = 'Expected true'): void
    {
        $this->assert($value === true, $message);
    }

    protected function assertFalse($value, string $message = 'Expected false'): void
    {
        $this->assert($value === false, $message);
    }

    protected function assertNotNull($value, string $message = 'Expected a value, got null'): void
    {
        $this->assert($value !== null, $message);
    }

    // --- Mail -----------------------------------------------------------

    /** Emails sent in this test: Mail::fake() in unit tests (HTTP tests read the test server's emails) */
    protected function sentMails(): array
    {
        return \System\Mail\Mail::sent();
    }

    /** An email to $to was sent, optionally with $subject in its subject; returns that email */
    protected function assertMailSent(string $to, ?string $subject = null): array
    {
        foreach (array_reverse($this->sentMails()) as $mail) {
            $recipients = array_map('strtolower', array_column($mail['to'] ?? [], 0));
            if (in_array(strtolower($to), $recipients, true) && ($subject === null || str_contains((string) ($mail['subject'] ?? ''), $subject))) {
                $this->assert(true, '');
                return $mail;
            }
        }
        $sent = array_map(fn($m) => '"' . ($m['subject'] ?? '') . '" to ' . implode(', ', array_column($m['to'] ?? [], 0)), $this->sentMails());
        $this->assert(false, "No email to $to" . ($subject !== null ? " with \"$subject\" in the subject" : '') . '. Sent: ' . ($sent ? implode('; ', $sent) : 'nothing'));
        return [];
    }

    protected function assertNoMailSent(): void
    {
        $count = count($this->sentMails());
        $this->assert($count === 0, "Expected no email, $count were sent");
    }

    protected function assertSame($expected, $actual, string $message = ''): void
    {
        $this->assert($expected === $actual, $message ?: 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    protected function assertEquals($expected, $actual, string $message = ''): void
    {
        $this->assert($expected == $actual, $message ?: 'Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }

    protected function assertNotSame($expected, $actual, string $message = 'Values should differ'): void
    {
        $this->assert($expected !== $actual, $message);
    }

    protected function assertNull($value, string $message = 'Expected null'): void
    {
        $this->assert($value === null, $message);
    }

    protected function assertCount(int $expected, $countable, string $message = ''): void
    {
        $actual = count($countable);
        $this->assert($expected === $actual, $message ?: "Expected count $expected, got $actual");
    }

    protected function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assert(str_contains($haystack, $needle), $message ?: "Expected to find \"$needle\"");
    }

    protected function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assert(!str_contains($haystack, $needle), $message ?: "Did not expect to find \"$needle\"");
    }

    protected function assertArrayHasKey($key, array $array, string $message = ''): void
    {
        $this->assert(array_key_exists($key, $array), $message ?: "Expected array key \"$key\"");
    }

    protected function assertThrows(string $exceptionClass, callable $callback, string $message = ''): void
    {
        try {
            $callback();
        } catch (\Throwable $e) {
            $this->assert($e instanceof $exceptionClass, $message ?: "Expected $exceptionClass, got " . get_class($e));
            return;
        }
        $this->assert(false, $message ?: "Expected $exceptionClass to be thrown");
    }
}
