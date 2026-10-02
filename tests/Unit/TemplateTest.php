<?php
namespace Tests\Unit;

use System\Testing\TestCase;
use System\View\ViewCompiler;
use System\View\ViewEngine;
use System\View\ViewException;

class TemplateTest extends TestCase
{
    protected string $dir;
    protected ViewEngine $engine;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bongo_tpl_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/views/partials', 0777, true);
        $this->engine = new ViewEngine($this->dir . '/views', $this->dir . '/cache');
    }

    public function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) $file->isDir() ? @rmdir($file) : @unlink($file);
        @rmdir($this->dir);
    }

    protected int $n = 0;

    protected function view(string $name, string $template): void
    {
        file_put_contents($this->dir . '/views/' . str_replace('.', '/', $name) . '.php', $template);
    }

    /** Render with whitespace collapsed, so assertions read cleanly */
    protected function render(string $template, array $data = []): string
    {
        $name = 't' . ++$this->n;
        $this->view($name, $template);
        return trim(preg_replace('/\s+/', ' ', $this->engine->render($name, $data)));
    }

    public function testConditions(): void
    {
        $tpl = "{: if \$n > 10 :} big {: elseif \$n > 5 :} medium {: else :} small {: end :}";
        $this->assertSame('big', $this->render($tpl, ['n' => 11]));
        $this->assertSame('medium', $this->render($tpl, ['n' => 6]));
        $this->assertSame('small', $this->render($tpl, ['n' => 1]));

        $this->assertSame('no', $this->render("{: unless \$ok :} no {: end :}", ['ok' => false]));
        $this->assertSame('set', $this->render("{: isset \$x :} set {: end :} {: isset \$y :} nope {: end :}", ['x' => 0]));
        $this->assertSame('empty', $this->render("{: empty \$list :} empty {: end :}", ['list' => []]));
        $this->assertSame('yes', $this->render("{: if in_array(\$x, ['a', 'b']) && \$y ?: true :}yes{: end :}", ['x' => 'a', 'y' => 0]), 'calls, arrays and ?: work in conditions');
        $this->assertSame('a:}b', $this->render("{: if true :}{{ 'a:}b' }}{: end :}"), 'a quoted ":}" does not end the tag');
    }

    public function testLoops(): void
    {
        $this->assertSame('a b c', $this->render("{: each \$items as \$i :} {{ \$i }} {: end :}", ['items' => ['a', 'b', 'c']]));
        $this->assertSame('x=1 y=2', $this->render("{: each \$map as \$k => \$v :} {{ \$k }}={{ \$v }} {: end :}", ['map' => ['x' => 1, 'y' => 2]]));
        $this->assertSame('0 1 2', $this->render("{: for \$i = 0; \$i < 3; \$i++ :} {{ \$i }} {: end :}"));
        $this->assertSame('3 2 1', $this->render("{: php \$n = 3 :} {: while \$n > 0 :} {{ \$n-- }} {: end :}"));
        $this->assertSame('1 2', $this->render("{: each [1, 2, 3, 4] as \$i :} {: if \$i === 3 :} {: break :} {: end :} {{ \$i }} {: end :}"));
        $this->assertSame('1 3', $this->render("{: each [1, 2, 3] as \$i :} {: continue \$i === 2 :} {{ \$i }} {: end :}"));
    }

    public function testEachElseShowsTheEmptyCase(): void
    {
        $tpl = "{: each \$rows as \$r :} [{{ \$r }}] {: else :} none {: end :}";
        $this->assertSame('[x] [y]', $this->render($tpl, ['rows' => ['x', 'y']]));
        $this->assertSame('none', $this->render($tpl, ['rows' => []]));

        // A nested loop keeps its own "empty" flag
        $nested = "{: each \$groups as \$g :} ({{ \$g['n'] }}: {: each \$g['items'] as \$i :}{{ \$i }}{: else :}-{: end :}) {: else :} nothing {: end :}";
        $this->assertSame('(A: 12) (B: -)', $this->render($nested, ['groups' => [['n' => 'A', 'items' => [1, 2]], ['n' => 'B', 'items' => []]]]));
    }

    public function testLoopVariable(): void
    {
        $tpl = "{: each \$items as \$i :}{{ \$loop->iteration }}/{{ \$loop->count }}{{ \$loop->first ? 'F' : '' }}{{ \$loop->last ? 'L' : '' }}{{ \$loop->odd ? 'o' : 'e' }}{: if !\$loop->last :}, {: end :}{: end :}";
        $this->assertSame('1/3Fo, 2/3e, 3/3Lo', $this->render($tpl, ['items' => ['a', 'b', 'c']]));

        // Nested loops: $loop->parent and depth; the outer $loop comes back after the inner loop
        $nested = "{: each \$rows as \$row :}{: each \$row as \$cell :}{{ \$loop->parent->index }}.{{ \$loop->index }}@{{ \$loop->depth }} {: end :}{{ \$loop->index }}| {: end :}";
        $this->assertSame('0.0@2 0.1@2 0| 1.0@2 1|', $this->render($nested, ['rows' => [[1, 2], [3]]]));

        // Generators cannot be counted: last is unknown, the rest works
        $gen = (function () { yield 'a'; yield 'b'; })();
        $this->assertSame('1? 2?', $this->render("{: each \$g as \$x :}{{ \$loop->iteration }}{{ \$loop->last === null ? '?' : '!' }} {: end :}", ['g' => $gen]));
    }

    public function testEchoesCommentsVerbatimAndPhp(): void
    {
        $this->assertSame('&lt;b&gt;x&lt;/b&gt; <b>x</b>', $this->render("{{ \$h }} {{! \$h }}", ['h' => '<b>x</b>']));
        $this->assertSame('a b', $this->render("a {-- hidden {{ \$boom }} {: if :} --} b"));
        $this->assertSame('{{ vue }} {: if x :}', $this->render("\\{{ vue }} \\{: if x :}"), 'a backslash prints the braces');
        $this->assertSame('<div>{{ count }} {: x :}</div>', $this->render("{: verbatim :}<div>{{ count }} {: x :}</div>{: end :}"));
        $this->assertSame('6', $this->render("{: php :} \$a = 2; \$b = 3; {: end :} {{ \$a * \$b }}"));
        $this->assertSame('mail me: user@example.com @media', $this->render("mail me: user@example.com @media"), '@ is plain text now');
        $this->assertSame('', $this->render('{{ $missing ?? null }}'), 'null prints as nothing');
    }

    public function testJsonIsSafeInsideScripts(): void
    {
        $out = $this->render("<script>let d = {: json \$d :};</script>", ['d' => ['x' => '</script><b>', 'q' => "it's"]]);
        $this->assertNotContains('</script><b>', $out);
        $escaped = trim(json_encode('</script>', JSON_HEX_TAG), '"'); // the escaped form of </script>
        $this->assertContains($escaped, $out, '< and > are escaped so the data cannot end the script tag');
    }

    public function testLayoutsBlocksShowDefaultsAndStacks(): void
    {
        $this->view('layout', "<title>{: show 'title', 'Default' :}</title><main>{: show 'content' :}</main><foot>{: stack 'js' :}</foot>");
        $this->view('page', "{: layout 'layout' :} {: block 'title', 'Home' :} {: block 'content' :}<p>{{ \$x }}</p>{: insert 'partials.box', ['label' => 'B'] :}{: end :} {: push 'js' :}<s1>{: end :} {: push 'js' :}<s2>{: end :}");
        $this->view('partials.box', "<box>{{ \$label }}/{{ \$x }}</box>{: push 'js' :}<s3>{: end :}");

        $this->assertSame('<title>Home</title><main><p>1</p><box>B/1</box></main><foot><s3><s1><s2></foot>', trim($this->engine->render('page', ['x' => 1])));

        // A second page in the same request does not inherit blocks or stacks
        $this->view('bare', "{: layout 'layout' :}");
        $this->assertSame('<title>Default</title><main></main><foot></foot>', trim($this->engine->render('bare')));
    }

    public function testInlineBlockValuesAreEscaped(): void
    {
        $this->view('shell', "<title>{: show 'title' :}</title>");
        $this->view('item', "{: layout 'shell' :}{: block 'title', \$name :}");
        $this->assertSame('<title>&lt;script&gt;x&lt;/script&gt; &amp; co</title>', trim($this->engine->render('item', ['name' => '<script>x</script> & co'])));
    }

    public function testNestedLayouts(): void
    {
        $this->view('base', "[base {: show 'body' :}]");
        $this->view('admin', "{: layout 'base' :} {: block 'body' :}(admin {: show 'page' :}){: end :}");
        $this->view('dash', "{: layout 'admin' :} {: block 'page' :}dash{: end :}");
        $this->assertSame('[base (admin dash)]', trim($this->engine->render('dash')));
    }

    public function testCsrfMethodErrorAndAuthHelpers(): void
    {
        $out = $this->render("{: csrf :} {: method 'DELETE' :}");
        $this->assertContains('name="_token"', $out);
        $this->assertContains('name="_method" value="DELETE"', $out);
        $this->assertSame('guest', $this->render("{: auth :} user {: end :} {: guest :} guest {: end :}"));
        $this->assertSame('shop guest', $this->render("{: auth 'customer' :} customer {: else :} shop guest {: end :}"), 'auth takes a guard name');
        $this->assertSame('', $this->render("{: error 'email' :} {{ \$message }} {: end :}"), 'no error flashed, nothing shown');
    }

    public function testCustomTag(): void
    {
        ViewCompiler::tag('upper', fn($args) => "<?php echo strtoupper($args); ?>");
        $this->assertSame('HELLO', $this->render("{: upper 'hello' :}"));
    }

    public function testMistakesFailWithTheLineNumber(): void
    {
        $fails = function (string $template, string $message) {
            try {
                $this->render($template);
            } catch (ViewException $e) {
                $this->assertContains($message, $e->getMessage());
                return;
            }
            throw new \System\Testing\AssertionFailed("Expected a ViewException for: $template");
        };
        $fails("a\nb\n{: if \$x :}\nc", 'Unclosed {: if :} opened on line 3');
        $fails("{-- two\nlines --}\n{: end :}", '{: end :} on line 3 has no open block');
        $fails("x\n{: foreach \$a as \$b :}", 'Unknown template tag {: foreach :} on line 2');
        $fails("{: each \$a as \$b :}{: else :}{: else :}{: end :}", 'Second {: else :}');
        $fails("{: each \$items :}{: end :}", 'must look like {: each $items as $item :}');
        $fails("{: if :}{: end :}", '{: if :} on line 1 needs an argument');

        // The engine adds the file name
        $this->view('broken', "{: if true :}");
        try {
            $this->engine->render('broken');
            throw new \System\Testing\AssertionFailed('Expected a ViewException');
        } catch (ViewException $e) {
            $this->assertContains('broken.php: Unclosed', $e->getMessage());
        }
    }

    public function testCompiledCacheIsReusedAndRefreshed(): void
    {
        $this->view('c', 'one');
        $this->assertSame('one', $this->engine->render('c'));
        $files = glob($this->dir . '/cache/*.php');
        $this->assertCount(1, $files, 'compiled once into the cache');

        $this->assertSame('one', $this->engine->render('c'));
        $this->assertCount(1, glob($this->dir . '/cache/*.php'), 'second render reuses the compiled file');

        file_put_contents($this->dir . '/views/c.php', 'two!'); // same second, different size
        $this->assertSame('two!', $this->engine->render('c'), 'a changed template is recompiled');
        $this->assertCount(1, glob($this->dir . '/cache/*.php'), 'the old compiled version is removed');

        $this->assertSame(1, $this->engine->clearCache());
    }

    public function testWorksWithoutCache(): void
    {
        $engine = new ViewEngine($this->dir . '/views', $this->dir . '/nocache', false);
        $this->view('n', '{: if true :} yes {: end :}');
        $this->assertSame('yes', trim($engine->render('n')));
        $this->assertFalse(is_dir($this->dir . '/nocache'));
    }
}
