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
    /** Called like a controller action: dump() inside reports this method and its values */
    public function update($id, string $password = '', ?object $product = null): array
    {
        return Dumper::context();
    }

    public function testContextNamesTheCallingMethodAndItsValues(): void
    {
        $product = (object) ['id' => 7];
        $context = $this->update('4', 'hunter2', $product);

        $this->assertSame('DebugTest::update($id = "4", $password = "***", $product = stdClass #7)', $context['in'], 'secrets are masked');
        $this->assertSame(self::class, $context['class']);
        $this->assertContains('tests/Unit/DebugTest.php:', $context['at']);
        $this->assertSame(null, $context['request'], 'no HTTP request on the command line');
        $this->assertSame($context['in'] . ' · ' . $context['at'], Dumper::headline($context));

        // Long strings and arrays stay short in the header
        $long = (fn(string $text, array $rows) => Dumper::context())(str_repeat('x', 100), [1, 2, 3]);
        $this->assertContains('array(3)', $long['in']);
        $this->assertContains('…', $long['in']);
    }

    public function testLargeListsStayLightAndOpenOnlyTheFirstRecords(): void
    {
        $rows = [];
        for ($i = 1; $i <= 5000; $i++) {
            $rows[] = \App\Models\Category::hydrate(['id' => $i, 'name' => "Category $i", 'slug' => "category-$i"]);
        }
        $html = (new Dumper())->html($rows);

        // The first 10 records are open, the 11th is one folded line with a preview
        $this->assertSame(1 + 10, substr_count($html, '<details open>'), 'the list itself + its first 10 records');
        $this->assertContains('{id: 11, name: &quot;Category 11&quot;, slug: &quot;category-11&quot;}', $html);
        $this->assertContains('… 4,800 more (showing the first 200)', $html);
        $this->assertTrue(strlen($html) < 400000, 'the page stays small: ' . strlen($html) . ' bytes');

        // Copy as JSON / API dumps use the same limit
        $plain = (new Dumper())->plain($rows);
        $this->assertCount(201, $plain);
        $this->assertSame('4,800 more items', $plain['…']);
    }

    public function testModelsDumpTheirColumnsNotTheirSettings(): void
    {
        $category = \App\Models\Category::hydrate(['id' => 1, 'name' => 'Books', 'slug' => 'books']);
        $text = (new Dumper())->text([$category]);

        $this->assertContains('name: "Books"', $text);
        $this->assertContains('slug: "books"', $text);
        foreach (['fillable', 'guarded', 'casts', 'original', 'attributes'] as $internal) {
            $this->assertNotContains($internal, $text, "no $internal in the dump");
        }

        // Hidden columns are masked, loaded relations shown, unsaved edits listed
        $user = \App\Models\User::hydrate(['id' => 2, 'name' => 'Rahim', 'password' => '$argon2id$secret']);
        $user->setRelation('orders', [\App\Models\Category::hydrate(['id' => 9, 'name' => 'Order-like'])]);
        $user->name = 'Rahim Uddin';
        $text = (new Dumper())->text($user);
        $this->assertContains('password: "***"', $text);
        $this->assertNotContains('argon2id', $text);
        $this->assertContains('orders:', $text);
        $this->assertContains("'Rahim' → 'Rahim Uddin'", $text);

        $this->assertContains('(not saved yet): true', (new Dumper())->text(new \App\Models\Category(['name' => 'New'])));
    }

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
