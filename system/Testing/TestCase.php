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
