<?php

namespace App\Http\Controllers {
    // A tiny controller used only by RouterTest
    class RouterProbeController
    {
        public static array $calls = [];

        public function show($id, $slug = null)
        {
            self::$calls[] = ['show', $id, $slug];
        }

        public function index()
        {
            self::$calls[] = ['index'];
        }
    }
}

namespace Tests\Unit {

    use App\Http\Controllers\RouterProbeController;
    use System\Http\HttpException;
    use System\Routing\Router;
    use System\Testing\TestCase;

    class RouterTest extends TestCase
    {
        protected Router $router;

        public function setUp(): void
        {
            RouterProbeController::$calls = [];
            $this->router = new Router([
                'GET /'                       => ['RouterProbeController', 'index'],
                'GET /items/{id}'             => ['RouterProbeController', 'show'],
                'GET /items/{id}/{slug}'      => ['RouterProbeController', 'show'],
                'POST /items'                 => ['RouterProbeController', 'index'],
                'ANY /any'                    => ['RouterProbeController', 'index'],
            ]);
        }

        public function testMatchesStaticRoute(): void
        {
            $this->router->dispatch('GET', '/');
            $this->assertSame([['index']], RouterProbeController::$calls);
        }

        public function testPassesRouteParameters(): void
        {
            $this->router->dispatch('GET', '/items/42/blue%20shirt');
            $this->assertSame([['show', '42', 'blue shirt']], RouterProbeController::$calls);
        }

        public function testParameterDoesNotMatchAcrossSlashes(): void
        {
            $this->assertThrows(HttpException::class, fn() => $this->router->dispatch('GET', '/items/1/2/3'));
        }

        public function testUnknownPathIs404(): void
        {
            try {
                $this->router->dispatch('GET', '/nope');
                $this->assertTrue(false, 'Expected HttpException');
            } catch (HttpException $e) {
                $this->assertSame(404, $e->status());
            }
        }

        public function testWrongMethodIs405(): void
        {
            try {
                $this->router->dispatch('DELETE', '/items');
                $this->assertTrue(false, 'Expected HttpException');
            } catch (HttpException $e) {
                $this->assertSame(405, $e->status());
            }
        }

        public function testAnyMatchesEveryMethod(): void
        {
            $this->router->dispatch('POST', '/any');
            $this->router->dispatch('GET', '/any');
            $this->assertCount(2, RouterProbeController::$calls);
        }

        public function testUnregisteredMiddlewareFails(): void
        {
            $router = new Router(['GET /x' => ['RouterProbeController', 'index', ['missing']]]);
            $this->assertThrows(\RuntimeException::class, fn() => $router->dispatch('GET', '/x'));
        }
    }
}
