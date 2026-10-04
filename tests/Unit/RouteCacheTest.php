<?php
namespace Tests\Unit;

use System\Routing\Router;
use System\Testing\TestCase;

class RouteCacheTest extends TestCase
{
    protected string $cacheFile;

    public function setUp(): void
    {
        parent::setUp();
        $this->cacheFile = ROUTE_CACHE;
        if (is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
    }

    public function tearDown(): void
    {
        if (is_file($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
        parent::tearDown();
    }

    public function testRoutesCanBeExportedAndRestoredFromCache(): void
    {
        $router = Router::fromConfig();
        $cachedData = $router->toCache();

        $this->assertTrue(count($cachedData['routes']) > 0);
        $this->assertArrayHasKey('routes', $cachedData);
        $this->assertArrayHasKey('middleware', $cachedData);

        $restored = Router::fromCache($cachedData);
        $this->assertCount(count($router->routes()), $restored->routes());

        // Verify named route lookup works from restored cache
        $this->assertTrue($restored->has('login'));
        $this->assertTrue($restored->has('api.health'));
        $this->assertSame('/login', $restored->url('login'));
        $this->assertSame('/api/health', $restored->url('api.health'));
    }

    public function testClosureRouteThrowsOnCache(): void
    {
        $router = new Router();
        $router->get('/closure-route', function () {
            return 'hello';
        });

        $this->assertThrows(\RuntimeException::class, function () use ($router) {
            $router->toCache();
        });
    }
}
