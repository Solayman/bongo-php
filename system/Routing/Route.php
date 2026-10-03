<?php
namespace System\Routing;

/**
 * Static route registration, used inside route files:
 *
 *   Route::get('/products', [ProductController::class, 'index'])->name('products.index');
 *   Route::post('/products/{id}', [ProductController::class, 'update'])->where('id', '\d+');
 *   Route::prefix('/admin')->middleware('auth')->group(function () { ... });
 *
 * @method static RouteDefinition get(string $path, array|\Closure $action)
 * @method static RouteDefinition post(string $path, array|\Closure $action)
 * @method static RouteDefinition put(string $path, array|\Closure $action)
 * @method static RouteDefinition patch(string $path, array|\Closure $action)
 * @method static RouteDefinition delete(string $path, array|\Closure $action)
 * @method static RouteDefinition options(string $path, array|\Closure $action)
 * @method static RouteDefinition any(string $path, array|\Closure $action)
 * @method static RouteDefinition match(array $methods, string $path, array|\Closure $action)
 * @method static void group(array $attributes, callable $routes)
 * @method static PendingGroup prefix(string $prefix)
 * @method static PendingGroup middleware(string|array ...$middleware)
 * @method static PendingGroup name(string $prefix)
 */
class Route
{
    public static function __callStatic(string $method, array $arguments)
    {
        $router = Router::current();
        if ($router === null) {
            throw new \RuntimeException('Routes can only be registered while the router loads a route file');
        }

        if (in_array($method, ['prefix', 'middleware', 'name'], true)) {
            return (new PendingGroup($router))->$method(...$arguments);
        }

        return $router->$method(...$arguments);
    }
}
