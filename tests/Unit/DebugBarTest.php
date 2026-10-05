<?php

namespace Tests\Unit;

use System\Cache\Cache;
use System\Database\Model;
use System\Debug\DebugBar;
use System\Log\Logger;
use System\Testing\TestCase;
use System\View\ViewCompiler;
use System\View\ViewEngine;

class TestDebugModel extends Model
{
    protected static $table = 'test_debug_models';
}

class DebugBarTest extends TestCase
{
    public function testDebugBarDisabledWhenAppDebugIsFalse(): void
    {
        $orig = $GLOBALS['config']['app']['debug'] ?? true;
        $GLOBALS['config']['app']['debug'] = false;

        $html = DebugBar::render();
        $this->assertSame('', $html);

        $GLOBALS['config']['app']['debug'] = $orig;
    }

    public function testDebugBarRendersAllPanelsAndMetrics(): void
    {
        $orig = $GLOBALS['config']['app']['debug'] ?? true;
        $GLOBALS['config']['app']['debug'] = true;

        // Record some dummy metrics
        Logger::instance()->info('DebugBar test info log', ['foo' => 'bar']);
        Logger::instance()->warning('DebugBar test warning log');
        Cache::put('debug_test_key', 'val', 60);
        TestDebugModel::hydrate(['id' => 1, 'name' => 'Demo']);

        $html = DebugBar::render();

        // Must contain root container, brand, and debug tag
        $this->assertContains('id="bongo-debugbar"', $html);
        $this->assertContains('Bongo', $html);
        $this->assertContains('DEBUG', $html);

        // Must contain all tabs
        $this->assertContains('data-tab="request"', $html);
        $this->assertContains('data-tab="messages"', $html);
        $this->assertContains('data-tab="timeline"', $html);
        $this->assertContains('data-tab="views"', $html);
        $this->assertContains('data-tab="queries"', $html);
        $this->assertContains('data-tab="models"', $html);
        $this->assertContains('data-tab="cache"', $html);

        // Must contain recorded logger messages
        $this->assertContains('DebugBar test info log', $html);
        $this->assertContains('DebugBar test warning log', $html);

        // Must contain model count
        $this->assertContains('Tests\Unit\TestDebugModel', $html);

        // Must contain cache operations
        $this->assertContains('debug_test_key', $html);

        // Must contain JavaScript toggle logic
        $this->assertContains('window.BongoDebugBar', $html);

        $GLOBALS['config']['app']['debug'] = $orig;
    }

    public function testDebugBarDirectiveCompilesCorrectly(): void
    {
        $compiler = new ViewCompiler();
        $compiled = $compiler->compile('@debugbar');

        $this->assertContains('System\Debug\DebugBar::render()', $compiled);
    }
}
