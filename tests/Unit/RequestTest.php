<?php
namespace Tests\Unit;

use System\Container\Container;
use System\Debug\Dumper;
use System\Http\Request;
use System\Testing\TestCase;

class RequestTest extends TestCase
{
    protected array $server;
    protected array $post;
    protected array $get;

    public function setUp(): void
    {
        [$this->server, $this->post, $this->get] = [$_SERVER, $_POST, $_GET];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/categories?tab=all';
        $_SERVER['QUERY_STRING'] = 'tab=all';
        $_SERVER['HTTP_HOST'] = 'localhost:8000';
        $_SERVER['HTTP_COOKIE'] = 'bongo_session=secret-session';
        $_GET = ['tab' => 'all'];
        $_POST = ['name' => '  Books  ', 'active' => 'on', 'qty' => '12', 'note' => '', 'password' => 'hunter2', '_token' => 'abc'];
    }

    public function tearDown(): void
    {
        [$_SERVER, $_POST, $_GET] = [$this->server, $this->post, $this->get];
        Request::setRouteParameters([]);
    }

    public function testActionsCanAskForTheRequest(): void
    {
        $name = Container::getInstance()->call(fn(Request $request) => $request->input('name'));
        $this->assertSame('Books', $name, 'instance calls work like Request::input()');
    }

    public function testInputHelpers(): void
    {
        $request = new Request();
        $this->assertTrue($request->has('note'), 'sent, even though empty');
        $this->assertTrue($request->has(['name', 'qty']));
        $this->assertFalse($request->has(['name', 'nope']));
        $this->assertFalse($request->filled('note'));
        $this->assertTrue($request->filled('name'));
        $this->assertTrue($request->missing('nope'));

        $this->assertTrue($request->boolean('active'), '"on" from a checkbox');
        $this->assertFalse($request->boolean('missing'));
        $this->assertTrue($request->boolean('missing', true), 'default');
        $this->assertSame(12, $request->integer('qty'));
        $this->assertSame(5, $request->integer('name', 5), 'not a number → default');

        $this->assertSame(['tab', 'name', 'active', 'qty', 'note'], array_keys($request->except(['password', '_token'])));
        $this->assertTrue($request->isMethod('post'));

        $_POST['_method'] = 'PUT';
        $this->assertTrue($request->isMethod('PUT'), 'a form\'s _method counts');
        $this->assertSame('http://localhost:8000/categories', $request->url());
        $this->assertSame('http://localhost:8000/categories?tab=all', $request->fullUrl());
    }

    public function testValidateReturnsOnlyTheRules(): void
    {
        $data = (new Request())->validate(['name' => 'required|string|max:20', 'qty' => 'integer']);
        $this->assertSame(['name' => 'Books', 'qty' => '12'], $data);
    }

    public function testDumpingTheRequestShowsWhatMatters(): void
    {
        Request::setRouteParameters(['id' => '4']);
        $text = (new Dumper())->text(new Request());

        $this->assertContains('POST', $text);
        $this->assertContains('http://localhost:8000/categories?tab=all', $text);
        $this->assertContains('Books', $text);
        $this->assertContains('"id" => "4"', $text, 'route parameters');
        $this->assertNotContains('hunter2', $text, 'passwords are masked');
        $this->assertNotContains('secret-session', $text, 'cookies are left out');
        $this->assertContains('***', $text);
    }
}
