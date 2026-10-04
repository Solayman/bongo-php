<?php

namespace Tests\Unit;

use System\Testing\TestCase;

class ConfigCacheTest extends TestCase
{
    protected string $cache;
    protected ?string $before = null;

    public function setUp(): void
    {
        $this->cache = CONFIG_CACHE;
        // Keep a developer's existing cache untouched
        $this->before = is_file($this->cache) ? file_get_contents($this->cache) : null;
    }

    public function tearDown(): void
    {
        if ($this->before === null) {
            @unlink($this->cache);
        } else {
            file_put_contents($this->cache, $this->before);
        }
    }

    protected function bongo(string $command): string
    {
        return (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(BASE_PATH . '/bongo') . " $command 2>&1");
    }

    public function testCacheHoldsTheSameConfigAsTheFiles(): void
    {
        $this->assertContains('Config cached', $this->bongo('config:cache'));
        $this->assertSame(load_config_files(), require $this->cache);

        $this->assertContains('removed', $this->bongo('config:clear'));
        clearstatcache();
        $this->assertFalse(is_file($this->cache));
        $this->assertContains('No config cache', $this->bongo('config:clear'));
    }

    public function testTestsNeverReadTheCache(): void
    {
        // This run uses the test database even though config:cache would hold the real one
        $this->assertSame('0', getenv('BONGO_CONFIG_CACHE'));
        $db = (string) config('database.connections.' . config('database.default') . '.database');
        $this->assertTrue(str_contains($db, '_test') || str_contains($db, 'testing'), "tests must use the test database, got $db");
    }
}
