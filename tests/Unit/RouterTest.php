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

        public function testObjectsThatSendThemselvesKeepTheirStatusAndHeaders(): void
        {
            $response = new class {
                public bool $sent = false;

                public function send(): void
                {
                    $this->sent = true;   // a package response sets its own status and headers here
                }
            };
            $router = new Router();
            $router->get('/custom', fn() => $response);

            ob_start();
            $router->dispatch('GET', '/custom');
            ob_end_clean();
            $this->assertTrue($response->sent, 'send() is called instead of only json_encode()');
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

        public function testShouldTrackPreviousUrlExcludesAssetsAndApiAndAjax(): void
        {
            $this->assertTrue($this->router->shouldTrackPreviousUrl('/posts/create'));
            $this->assertTrue($this->router->shouldTrackPreviousUrl('/products'));
            $this->assertFalse($this->router->shouldTrackPreviousUrl('/filemanager/thumb'));
            $this->assertFalse($this->router->shouldTrackPreviousUrl('/filemanager/download'));
            $this->assertFalse($this->router->shouldTrackPreviousUrl('/api/health'));
            $this->assertFalse($this->router->shouldTrackPreviousUrl('/storage/photos/avatar.png'));
            $this->assertFalse($this->router->shouldTrackPreviousUrl('/assets/app.css'));
            $this->assertFalse($this->router->shouldTrackPreviousUrl('/captcha'));
        }
    }
}
