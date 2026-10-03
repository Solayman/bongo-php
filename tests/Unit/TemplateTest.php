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

        // Compile errors name the template file
        $this->view('broken', "@foreach(\$items)@endforeach");
        try {
            $this->engine->render('broken');
            throw new \System\Testing\AssertionFailed('Expected a ViewException');
        } catch (\System\View\ViewException $e) {
            $this->assertContains('broken.php: @foreach($items) must look like', $e->getMessage());
        }
    }

    public function testLoopVariable(): void
    {
        $tpl = "@foreach(\$items as \$i){{ \$loop->iteration }}/{{ \$loop->count }}{{ \$loop->first ? 'F' : '' }}{{ \$loop->last ? 'L' : '' }}{{ \$loop->odd ? 'o' : 'e' }}@if(!\$loop->last), @endif@endforeach";
        $this->assertSame('1/3Fo, 2/3e, 3/3Lo', $this->render($tpl, ['items' => ['a', 'b', 'c']]));

        // Nested loops: $loop->parent and depth; the outer $loop comes back after the inner loop
        $nested = "@foreach(\$rows as \$row)@foreach(\$row as \$cell){{ \$loop->parent->index }}.{{ \$loop->index }}:{{ \$loop->depth }} @endforeach{{ \$loop->index }}| @endforeach";
        $this->assertSame('0.0:2 0.1:2 0| 1.0:2 1|', $this->render($nested, ['rows' => [[1, 2], [3]]]));

        // @forelse has $loop too, with or without its @empty part
        $this->assertSame('1 2', $this->render("@forelse(\$xs as \$x) {{ \$loop->iteration }} @empty none @endforelse", ['xs' => ['a', 'b']]));
        $this->assertSame('a', $this->render("@forelse(\$xs as \$x){{ \$x }}@endforelse", ['xs' => ['a']]));

        // Generators cannot be counted: last is unknown, the rest works
        $gen = (function () { yield 'a'; yield 'b'; })();
        $this->assertSame('1? 2?', $this->render("@foreach(\$g as \$x){{ \$loop->iteration }}{{ \$loop->last === null ? '?' : '!' }} @endforeach", ['g' => $gen]));
    }

    public function testAuthTakesAGuardName(): void
    {
        $this->assertSame('shop guest', $this->render("@auth('customer') customer @else shop guest @endauth"));
        $this->assertSame('signed out', $this->render("@guest('customer') signed out @endguest"));
    }

    public function testInlineSectionValuesAreEscaped(): void
    {
        $this->view('shell', "<title>@yield('title')</title><h1>@yield('heading')</h1>");
        $this->view('item', "@extends('shell') @section('title', \$name) @set('heading', \$name)");
        $this->assertSame(
            '<title>&lt;script&gt;x&lt;/script&gt; &amp; co</title><h1>&lt;script&gt;x&lt;/script&gt; &amp; co</h1>',
            trim($this->engine->render('item', ['name' => '<script>x</script> & co'])),
            'a product name cannot inject HTML into the page title'
        );
    }

    public function testSwitch(): void
    {
        $tpl = "@switch(\$status)\n    @case('paid')\n        Paid\n        @break\n    @case('shipped')\n    @case('delivered')\n        On its way\n        @break\n    @default\n        Pending\n@endswitch";
        $this->assertSame('Paid', $this->render($tpl, ['status' => 'paid']));
        $this->assertSame('On its way', $this->render($tpl, ['status' => 'delivered']), 'cases can share a body');
        $this->assertSame('Pending', $this->render($tpl, ['status' => 'new']));

        // Nested in a loop, and nested switches
        $nested = "@foreach(\$xs as \$x)@switch(\$x)@case(1)one@break@default @switch(\$x % 2)@case(0)even@break@default odd@endswitch@endswitch,@endforeach";
        $this->assertSame('one, even, odd,', $this->render($nested, ['xs' => [1, 2, 3]]));
    }

    public function testFormAttributeHelpers(): void
    {
        $out = $this->render("<input type=\"checkbox\" @checked(\$on)><option @selected(\$id === 2)>x</option><button @disabled(!\$ok) @readonly(false) @required(true)>", ['on' => true, 'id' => 2, 'ok' => false]);
        $this->assertSame('<input type="checkbox" checked><option selected>x</option><button disabled required>', $out);
        $this->assertSame('<input type="checkbox" >', $this->render("<input type=\"checkbox\" @checked(\$on)>", ['on' => false]));

        $this->assertSame('<p class="btn btn-primary">', $this->render("<p @class(['btn', 'btn-primary' => \$active, 'd-none' => !\$show, null])>", ['active' => true, 'show' => true]));
        $this->assertSame('<p class="a &quot;b">', $this->render("<p @class([\$cls])>", ['cls' => 'a "b']), 'class names are escaped');
    }

    public function testConditionalIncludesAndEach(): void
    {
        $this->view('partials.badge', "[{{ \$label ?? 'badge' }}]");
        $this->view('partials.card', "<{{ \$item }}>");
        $this->view('partials.none', "(none)");

        $this->assertSame('[x]', $this->render("@includeIf('partials.badge', ['label' => 'x'])@includeIf('partials.missing')"));
        $this->assertSame('[yes]', $this->render("@includeWhen(\$admin, 'partials.badge', ['label' => 'yes'])@includeUnless(\$admin, 'partials.badge')", ['admin' => true]));
        $this->assertSame('[badge]', $this->render("@includeFirst(['partials.custom', 'partials.badge'])"));
        $this->assertThrows(\System\View\ViewException::class, fn() => $this->render("@includeFirst(['partials.a', 'partials.b'])"));

        $this->assertSame('<a><b>', $this->render("@each('partials.card', \$items, 'item', 'partials.none')", ['items' => ['a', 'b']]));
        $this->assertSame('(none)', $this->render("@each('partials.card', [], 'item', 'partials.none')"));
        $this->assertSame('Nothing yet', $this->render("@each('partials.card', [], 'item', 'raw|Nothing yet')"));
    }

    public function testHasSectionOnceEnvAndSharedData(): void
    {
        $this->view('shell', "@hasSection('aside')<aside>@yield('aside')</aside>@endif @sectionMissing('aside')<p>no aside</p>@endif");
        $this->view('with', "@extends('shell')@section('aside')A@endsection");
        $this->view('without', "@extends('shell')");
        $this->assertSame('<aside>A</aside>', trim($this->engine->render('with')));
        $this->assertSame('<p>no aside</p>', trim($this->engine->render('without')));

        // @once inside a partial rendered three times: printed once per page
        $this->view('partials.picker', "@once<script src=\"picker.js\"></script>@endonce<input>");
        $page = "@include('partials.picker')@include('partials.picker')@include('partials.picker')";
        $this->assertSame('<script src="picker.js"></script><input><input><input>', $this->render($page));
        $this->assertSame('<script src="picker.js"></script><input><input><input>', $this->render($page), 'and again on the next page');

        $env = config('app.env');
        $this->assertSame('T', $this->render("@env('testing')T@endenv @env(['local', 'production'])X@endenv @production P@endproduction"), "app.env is $env in tests");

        \System\View\ViewEngine::share(['siteName' => 'Bongo Shop', 'visits' => fn() => 41 + 1]);
        $this->assertSame('Bongo Shop 42', $this->render("{{ \$siteName }} {{ \$visits }}"));
        $this->assertSame('Mine 42', $this->render("{{ \$siteName }} {{ \$visits }}", ['siteName' => 'Mine']), 'passed data wins');
        \System\View\ViewEngine::share(['siteName' => null, 'visits' => null]);
    }

    public function testComponents(): void
    {
        mkdir($this->dir . '/views/components/forms', 0777, true);
        $this->view('components.alert', '<div class="alert alert-{{ $type ?? \'info\' }}">{{ $slot }}</div>');
        $this->view('components.badge', '<span>{{ $text }}{{ $count ?? \'\' }}</span>');
        $this->view('components.forms.input', '<input name="{{ $name }}" maxlength="{{ $maxLength }}" @disabled($disabled ?? false)>');

        // Text attributes, a slot with HTML and {{ }} inside, PHP values with ":"
        $this->assertSame('<div class="alert alert-danger">Saved <b>Mug</b>!</div>',
            $this->render('<x-alert type="danger">Saved <b>{{ $name }}</b>!</x-alert>', ['name' => 'Mug']));
        $this->assertSame('<span>New3</span>', $this->render('<x-badge text="New" :count="1 + 2" />'));

        // Folders, dashed names become camelCase, bare attributes are true
        $this->assertSame('<input name="email" maxlength="20" disabled>', $this->render('<x-forms.input name="email" max-length="20" disabled />'));

        // Nested components, and the slot's own data is still escaped
        $this->assertSame('<div class="alert alert-info">x <span>&lt;b&gt;</span></div>',
            $this->render('<x-alert>x <x-badge :text="$evil" /></x-alert>', ['evil' => '<b>']));

        // Directives work inside a slot
        $this->assertSame('<div class="alert alert-info">1 2 </div>', $this->render('<x-alert>@foreach([1, 2] as $i){{ $i }} @endforeach</x-alert>'));

        // Mistakes say what to do
        $this->assertThrows(\System\View\ViewException::class, fn() => $this->render('<x-missing />'));
        $this->assertThrows(\System\View\ViewException::class, fn() => $this->render('<x-alert>no end'));
    }
}
