<?php

namespace App\Http\Middleware {
    // Records how it was called, used only by RoutingTest
    class ProbeMiddleware
    {
        public static array $calls = [];

        public function handle(...$args): void
        {
            self::$calls[] = $args;
        }
    }
}

namespace Tests\Unit\RoutingProbe {
    class ProbeController
    {
        public static array $calls = [];

        public function show($id, $slug = null)
        {
            self::$calls[] = [$id, $slug];
        }

        public function json()
        {
            return ['ok' => true];
        }
    }
}

namespace Tests\Unit {

    use App\Http\Middleware\ProbeMiddleware;
    use System\Http\HttpException;
    use System\Routing\Route;
    use System\Routing\Router;
    use System\Testing\TestCase;
    use Tests\Unit\RoutingProbe\ProbeController;

    class RoutingTest extends TestCase
    {
        protected Router $router;

        public function setUp(): void
        {
            ProbeController::$calls = [];
            ProbeMiddleware::$calls = [];

            $this->router = new Router([], ['probe' => ProbeMiddleware::class], ['shop' => ['probe:group']]);
            $this->router->group(['middleware' => ['shop']], function () {
                Route::get('/items/{id}/{slug?}', [ProbeController::class, 'show'])->name('items.show')->where('id', '\d+');
                Route::delete('/items/{id}', [ProbeController::class, 'show'])->name('items.destroy');
                Route::get('/json', [ProbeController::class, 'json'])->withoutMiddleware('probe');
                Route::prefix('/admin')->name('admin.')->middleware('probe:admin,strict')->group(function () {
                    Route::get('/reports/{year}', fn($year) => "report $year")->name('reports');
                    Route::match(['GET', 'POST'], '/search', fn() => 'search')->name('search');
                    Route::any('/anything', fn() => 'any');
                });
            });
        }

        protected function dispatch(string $method, string $path): string
        {
            ob_start();
            try {
                $this->router->dispatch($method, $path);
            } finally {
                $output = ob_get_clean();
            }
            return $output;
        }

        public function testFullyQualifiedControllerAndParameters(): void
        {
            $this->dispatch('GET', '/items/7/blue-shirt');
            $this->dispatch('GET', '/items/8');
            $this->assertSame([['7', 'blue-shirt'], ['8', null]], ProbeController::$calls);
        }

        public function testWhereConstraintRejectsNonMatchingValues(): void
        {
            $this->assertThrows(HttpException::class, fn() => $this->dispatch('GET', '/items/abc'));
        }

        public function testHttpVerbsAndWrongMethod(): void
        {
            $this->dispatch('DELETE', '/items/3');
            $this->assertSame([['3', null]], ProbeController::$calls);

            try {
                $this->dispatch('PATCH', '/items/3');
                $this->assertTrue(false, 'Expected 405');
            } catch (HttpException $e) {
                $this->assertSame(405, $e->status());
            }

            $this->dispatch('HEAD', '/items/3');  // GET routes answer HEAD
            $this->assertSame('search', $this->dispatch('POST', '/admin/search'));
            $this->assertSame('any', $this->dispatch('PUT', '/admin/anything'));
        }

        public function testGroupsPrefixNamesAndClosureResults(): void
        {
            $this->assertSame('report 2026', $this->dispatch('GET', '/admin/reports/2026'));
            $this->assertSame('{"ok":true}', $this->dispatch('GET', '/json'), 'Arrays are sent as JSON');
        }

        public function testMiddlewareGroupsParametersAndExclusions(): void
        {
            $this->dispatch('GET', '/admin/reports/1');
            $this->assertSame([['group'], ['admin', 'strict']], ProbeMiddleware::$calls);

            ProbeMiddleware::$calls = [];
            $this->dispatch('GET', '/json');
            $this->assertSame([], ProbeMiddleware::$calls, 'withoutMiddleware() skips the group middleware');
        }

        public function testNamedRouteUrls(): void
        {
            $this->assertSame('/items/5', $this->router->url('items.show', ['id' => 5]));
            $this->assertSame('/items/5/red%20hat', $this->router->url('items.show', ['id' => 5, 'slug' => 'red hat']));
            $this->assertSame('/admin/reports/2026?format=pdf', $this->router->url('admin.reports', ['year' => 2026, 'format' => 'pdf']));
            $this->assertTrue($this->router->has('admin.search'));
            $this->assertThrows(\InvalidArgumentException::class, fn() => $this->router->url('admin.reports'));
            $this->assertThrows(\InvalidArgumentException::class, fn() => $this->router->url('nope'));
        }

        public function testArrayRouteFilesStillWork(): void
        {
            $router = new Router(['GET|POST /legacy/{id}' => [ProbeController::class, 'show']]);
            ob_start();
            $router->dispatch('POST', '/legacy/9');
            ob_end_clean();
            $this->assertSame([['9', null]], ProbeController::$calls);
        }

        public function testAppRouteFilesLoadFromConfig(): void
        {
            $router = Router::fromConfig();
            $this->assertSame('/reset-password/abc', $router->url('password.reset', ['token' => 'abc']));
            $this->assertSame('/api/health', $router->url('api.health'));
            $this->assertSame(url('/system'), route('system'));
            $this->assertSame(url('/plain/path'), route('/plain/path'));
        }

        public function testExactRoutesTakePrecedenceOverWildcards(): void
        {
            $router = new Router();
            // Wildcard registered FIRST
            $router->get('/admin/{any?}', fn() => 'spa_shell')->where('any', '.*');
            // Exact API endpoint registered AFTER
            $router->get('/admin/api/overview', fn() => 'api_overview');
            $router->get('/admin/api/{endpoint}', fn($endpoint) => "api_$endpoint");

            ob_start();
            $router->dispatch('GET', '/admin/api/overview');
            $exact = ob_get_clean();
            $this->assertSame('api_overview', $exact, 'Exact match takes precedence over wildcard pattern registered earlier');

            ob_start();
            $router->dispatch('GET', '/admin/api/devices');
            $param = ob_get_clean();
            $this->assertSame('api_devices', $param, 'Specific param route takes precedence over catch-all .*');

            ob_start();
            $router->dispatch('GET', '/admin/dashboard');
            $spa = ob_get_clean();
            $this->assertSame('spa_shell', $spa, 'Catch-all handles non-API paths');
        }

        public function testFallbackRouteExecutesOnlyWhenNoOtherRouteMatches(): void
        {
            $router = new Router();
            $router->fallback(fn() => 'global_fallback');
            $router->get('/exact/route', fn() => 'exact_response');

            ob_start();
            $router->dispatch('GET', '/exact/route');
            $exact = ob_get_clean();
            $this->assertSame('exact_response', $exact);

            ob_start();
            $router->dispatch('GET', '/non-existent/deep/path');
            $fallback = ob_get_clean();
            $this->assertSame('global_fallback', $fallback);
        }
    }
}
