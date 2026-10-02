<?php
namespace Tests\Unit;

use System\Testing\TestCase;
use System\View\ViewCompiler;
use System\View\ViewEngine;

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
        $tpl = "@if(\$n > 10) big @elseif(\$n > 5) medium @else small @endif";
        $this->assertSame('big', $this->render($tpl, ['n' => 11]));
        $this->assertSame('medium', $this->render($tpl, ['n' => 6]));
        $this->assertSame('small', $this->render($tpl, ['n' => 1]));

        $this->assertSame('no', $this->render("@unless(\$ok) no @endunless", ['ok' => false]));
        $this->assertSame('set', $this->render("@isset(\$x) set @endisset @isset(\$y) nope @endisset", ['x' => 0]));
        $this->assertSame('empty', $this->render("@empty(\$list) empty @endempty", ['list' => []]));
    }

    public function testLoops(): void
    {
        $this->assertSame('a b c', $this->render("@foreach(\$items as \$i) {{ \$i }} @endforeach", ['items' => ['a', 'b', 'c']]));
        $this->assertSame('0 1 2', $this->render("@for(\$i = 0; \$i < 3; \$i++) {{ \$i }} @endfor"));
        $this->assertSame('3 2 1', $this->render("@php(\$n = 3) @while(\$n > 0) {{ \$n-- }} @endwhile"));
        $this->assertSame('1 2', $this->render("@foreach([1, 2, 3, 4] as \$i) @if(\$i === 3) @break @endif {{ \$i }} @endforeach"));
        $this->assertSame('1 3', $this->render("@foreach([1, 2, 3] as \$i) @continue(\$i === 2) {{ \$i }} @endforeach"));
    }

    public function testForelse(): void
    {
        $tpl = "@forelse(\$rows as \$r) [{{ \$r }}] @empty none @endforelse";
        $this->assertSame('[x] [y]', $this->render($tpl, ['rows' => ['x', 'y']]));
        $this->assertSame('none', $this->render($tpl, ['rows' => []]));

        // nested forelse keeps its own empty flag
        $nested = "@forelse(\$groups as \$g) ({{ \$g['n'] }}: @forelse(\$g['items'] as \$i){{ \$i }}@empty-@endforelse) @empty nothing @endforelse";
        $this->assertSame('(A: 12) (B: -)', $this->render($nested, ['groups' => [['n' => 'A', 'items' => [1, 2]], ['n' => 'B', 'items' => []]]]));
    }

    public function testEchoesCommentsVerbatimAndPhp(): void
    {
        $this->assertSame('&lt;b&gt;x&lt;/b&gt; <b>x</b>', $this->render("{{ \$h }} {!! \$h !!}", ['h' => '<b>x</b>']));
        $this->assertSame('a b', $this->render("a {{-- hidden {{ \$boom }} --}} b"));
        $this->assertSame('{{ vue }} @if', $this->render("@{{ vue }} @@if"), '@{{ }} prints braces for JS frameworks');
        $this->assertSame('6', $this->render("@php \$a = 2; \$b = 3; @endphp {{ \$a * \$b }}"));
        $this->assertSame('mail me: user@example.com', $this->render("mail me: user@example.com"), 'email addresses are not directives');
        $this->assertSame('<style>@media (max-width: 1px) {}</style>', $this->render("<style>@media (max-width: 1px) {}</style>"));
    }

    public function testJsonIsSafeInsideScripts(): void
    {
        $out = $this->render("<script>let d = @json(\$d);</script>", ['d' => ['x' => '</script><b>', 'q' => "it's"]]);
        $this->assertNotContains('</script><b>', $out);
        $escaped = trim(json_encode('</script>', JSON_HEX_TAG), '"'); // the escaped form of </script>
        $this->assertContains($escaped, $out, '< and > are escaped so the data cannot end the script tag');
    }

    public function testLayoutsSectionsYieldDefaultsAndStacks(): void
    {
        $this->view('layout', "<title>@yield('title', 'Default')</title><main>@yield('content')</main><foot>@stack('js')</foot>");
        $this->view('page', "@extends('layout') @section('title', 'Home') @section('content')<p>{{ \$x }}</p>@include('partials.box', ['label' => 'B'])@endsection @push('js')<s1>@endpush @push('js')<s2>@endpush");
        $this->view('partials.box', "<box>{{ \$label }}/{{ \$x }}</box>@push('js')<s3>@endpush");

        $this->assertSame('<title>Home</title><main><p>1</p><box>B/1</box></main><foot><s3><s1><s2></foot>', trim($this->engine->render('page', ['x' => 1])));

        // A second page in the same request does not inherit sections or stacks
        $this->view('bare', "@extends('layout')");
        $this->assertSame('<title>Default</title><main></main><foot></foot>', trim($this->engine->render('bare')));
    }

    public function testNestedLayouts(): void
    {
        $this->view('base', "[base @yield('body')]");
        $this->view('admin', "@extends('base') @section('body')(admin @yield('page'))@endsection");
        $this->view('dash', "@extends('admin') @section('page')dash@endsection");
        $this->assertSame('[base (admin dash)]', trim($this->engine->render('dash')));
    }

    public function testCsrfMethodErrorAndAuthHelpers(): void
    {
        $out = $this->render("@csrf @method('DELETE')");
        $this->assertContains('name="_token"', $out);
        $this->assertContains('name="_method" value="DELETE"', $out);
        $this->assertSame('guest', $this->render("@auth user @endauth @guest guest @endguest"));
        $this->assertSame('', $this->render("@error('email') {{ \$message }} @enderror"), 'no error flashed, nothing shown');
    }

    public function testCustomDirective(): void
    {
        ViewCompiler::directive('upper', fn($args) => "<?php echo strtoupper($args); ?>");
        $this->assertSame('HELLO', $this->render("@upper('hello')"));
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
        $this->view('n', '@if(true) yes @endif');
        $this->assertSame('yes', trim($engine->render('n')));
        $this->assertFalse(is_dir($this->dir . '/nocache'));
    }

    public function testUnbalancedTagsFailClearly(): void
    {
        $this->assertThrows(\System\View\ViewException::class, fn() => $this->render('@endsection'));
        $this->assertThrows(\System\View\ViewException::class, fn() => $this->render('@empty'));
    }
}
