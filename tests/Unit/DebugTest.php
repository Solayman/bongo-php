<?php
namespace Tests\Unit;

use System\Debug\Dumper;
use System\Debug\ExceptionPage;
use System\Testing\TestCase;

class DebugProbe
{
    public $name = 'probe';
    protected $level = 2;
    private $secret = ['a' => true];
    public $self;
}

class DebugTest extends TestCase
{
    public function testTextDumpShowsTypesVisibilityAndRecursion(): void
    {
        $probe = new DebugProbe();
        $probe->self = $probe;
        $text = (new Dumper())->text(['n' => 1.5, 'list' => [null, false], 'obj' => $probe]);

        $this->assertContains('"n" => 1.5', $text);
        $this->assertContains('0 => null', $text);
        $this->assertContains('1 => false', $text);
        $this->assertContains('name: "probe" (5)', $text);
        $this->assertContains('#level: 2', $text, 'protected properties are marked with #');
        $this->assertContains('-secret: array(1)', $text, 'private properties are marked with -');
        $this->assertContains('{*recursion*}', $text);
    }

    public function testHtmlDumpEscapesValues(): void
    {
        $html = (new Dumper())->html(['x' => '<script>alert(1)</script>']);
        $this->assertNotContains('<script>', $html);
        $this->assertContains('&lt;script&gt;', $html);
        $this->assertContains('<details', $html);
    }

    public function testLimitsDepthAndLength(): void
    {
        $dumper = new Dumper(2, 3, 5);
        $text = $dumper->text(['a' => ['b' => ['c' => 1]], 'long' => 'abcdefghij', 'many' => [1, 2, 3, 4, 5]]);
        $this->assertContains('[...]', $text);
        $this->assertContains('"abcde…" (10)', $text);
        $this->assertContains('... 2 more', $text);
    }

    public function testPlainIsJsonSafe(): void
    {
        $probe = new DebugProbe();
        $probe->self = $probe;
        $plain = (new Dumper())->plain(['p' => $probe]);
        $this->assertSame(DebugProbe::class, $plain['p']['@class']);
        $this->assertSame(['a' => true], $plain['p']['secret']);
        $this->assertTrue(json_encode($plain) !== false);
    }

    public function testDumpReturnsItsFirstValue(): void
    {
        ob_start();
        $value = dump('kept', 'other');
        $out = ob_get_clean();
        $this->assertSame('kept', $value);
        $this->assertContains('tests/Unit/DebugTest.php:', $out, 'dump() shows where it was called');
    }

    public function testExceptionPageShowsSourceTraceAndMasksSecrets(): void
    {
        // Built from pieces so the literal secret never appears in this file's source snippet
        $_POST = ['email' => 'a@b.co', 'password' => 'hunt' . 'er2'];
        try {
            $line = __LINE__ + 1;
            throw new \LogicException('Boom <b>here</b>', 0, new \RuntimeException('root cause'));
        } catch (\LogicException $e) {
            $html = (new ExceptionPage($e))->render();
        } finally {
            $_POST = [];
        }

        $this->assertContains('LogicException', $html);
        $this->assertContains('Boom &lt;b&gt;here&lt;/b&gt;', $html);
        $this->assertContains('tests/Unit/DebugTest.php:' . $line, $html);
        $this->assertContains('<div class="ln hit"><span class="no">' . $line . '</span>', $html, 'the failing line is highlighted');
        $this->assertContains('Caused by <strong>RuntimeException</strong>: root cause', $html);
        $this->assertContains('a@b.co', $html);
        $this->assertNotContains('hunter2', $html);
        $this->assertContains('Stack trace', $html);
    }
}
