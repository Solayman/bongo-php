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

    public function testMissingViewThrows(): void
    {
        $this->assertThrows(\Exception::class, fn() => $this->engine->render('nope'));
    }
}
