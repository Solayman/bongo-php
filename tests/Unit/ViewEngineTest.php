<?php
namespace Tests\Unit;

use System\Testing\TestCase;
use System\View\ViewEngine;

class ViewEngineTest extends TestCase
{
    protected string $dir;
    protected ViewEngine $engine;

    public function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bongo_views_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/partials', 0777, true);

        file_put_contents("$this->dir/layout.php", "<title>@yield('title')</title><main>@yield('content')</main>");
        file_put_contents("$this->dir/page.php", "@extends('layout')\n@set('title', 'Home')\n@section('content')<p>{{ \$name }}</p>@include('partials.note')@endsection");
        file_put_contents("$this->dir/partials/note.php", "<small>note for {{ \$name }}</small>");
        file_put_contents("$this->dir/plain.php", "<?php \$GLOBALS['view_runs'] = (\$GLOBALS['view_runs'] ?? 0) + 1; ?>Raw: {!! \$html !!}");

        $this->engine = new ViewEngine($this->dir, $this->dir . '/cache');
    }

    public function tearDown(): void
    {
        foreach (['layout', 'page', 'plain', 'partials/note'] as $f) @unlink("$this->dir/$f.php");
        array_map('unlink', glob("$this->dir/cache/*") ?: []);
        @rmdir("$this->dir/cache");
        @rmdir("$this->dir/partials");
        @rmdir($this->dir);
    }

    public function testLayoutSectionsSetAndInclude(): void
    {
        $html = $this->engine->render('page', ['name' => 'Rahim']);
        $this->assertSame('<title>Home</title><main><p>Rahim</p><small>note for Rahim</small></main>', $html);
    }

    public function testEscapesOutput(): void
    {
        $html = $this->engine->render('page', ['name' => '<script>alert(1)</script>']);
        $this->assertNotContains('<script>', $html);
        $this->assertContains('&lt;script&gt;', $html);
    }

    public function testUserDataIsNeverExecutedAsTemplateCode(): void
    {
        $html = $this->engine->render('page', ['name' => '{{ phpversion() }}']);
        $this->assertContains('{{ phpversion() }}', $html);
        $this->assertNotContains(PHP_VERSION, $html);
    }

    public function testViewWithoutLayoutRunsOnceAndRawEcho(): void
    {
        $GLOBALS['view_runs'] = 0;
        $html = $this->engine->render('plain', ['html' => '<b>bold</b>']);
        $this->assertSame('Raw: <b>bold</b>', $html);
        $this->assertSame(1, $GLOBALS['view_runs']);
    }

    public function testNullPrintsAsEmptyWithoutDeprecation(): void
    {
        file_put_contents("$this->dir/nullable.php", "[{{ \$missing ?? null }}][{{ null }}]");
        $notices = [];
        set_error_handler(function ($no, $msg) use (&$notices) { $notices[] = $msg; return true; });
        try {
            $html = $this->engine->render('nullable');
        } finally {
            restore_error_handler();
            @unlink("$this->dir/nullable.php");
        }
        $this->assertSame('[][]', $html);
        $this->assertSame([], $notices, 'Rendering null must not raise notices');
    }

    public function testMissingViewThrows(): void
    {
        $this->assertThrows(\Exception::class, fn() => $this->engine->render('nope'));
    }

    public function testIncludeInALoopAndEditedTemplatesBetweenRenders(): void
    {
        file_put_contents("$this->dir/loop.php", "@foreach(\$items as \$name)@include('partials.note')@endforeach");
        $html = $this->engine->render('loop', ['items' => ['a', 'b', 'c']]);
        $this->assertSame('<small>note for a</small><small>note for b</small><small>note for c</small>', $html);

        // A long-running process (queue worker) sees template edits on its next render
        file_put_contents("$this->dir/partials/note.php", "<i>{{ \$name }} (edited)</i>");
        $this->assertSame('<i>a (edited)</i>', $this->engine->render('loop', ['items' => ['a']]));
        @unlink("$this->dir/loop.php");
    }

    public function testGuessesTheViewForAnAction(): void
    {
        $this->assertSame('products.index', ViewEngine::guessName('App\\Http\\Controllers\\ProductController', 'index'));
        $this->assertSame('categories.edit', ViewEngine::guessName('App\\Http\\Controllers\\CategoryController', 'edit'));
        $this->assertSame('shop.catalogs.show', ViewEngine::guessName('App\\Http\\Controllers\\Shop\\CatalogController', 'show'));
        $this->assertSame('order_items.bulk-edit', ViewEngine::guessName('App\\Http\\Controllers\\OrderItemController', 'bulkEdit'));
    }

    public function testCompileAllPrecompilesAndReportsBrokenTemplates(): void
    {
        $result = $this->engine->compileAll(lint: true);
        $this->assertSame(4, $result['compiled']);
        $this->assertSame([], $result['errors']);
        $this->assertCount(4, glob("$this->dir/cache/*.php"));

        file_put_contents("$this->dir/broken.php", "<?php if ( ?>oops");
        $result = $this->engine->compileAll(lint: true);
        $this->assertSame(['broken'], array_keys($result['errors']));
        $this->assertContains('syntax error', $result['errors']['broken']);
        @unlink("$this->dir/broken.php");
    }
}
