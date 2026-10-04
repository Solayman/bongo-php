<?php

namespace System\Routing;

/**
 * Fluent group builder: Route::prefix('/admin')->middleware('auth')->name('admin.')->group(fn() => ...)
 */
class PendingGroup
{
    protected array $attributes = [];

    public function __construct(protected Router $router)
    {
    }

    public function prefix(string $prefix): static
    {
        $this->attributes['prefix'] = $prefix;
        return $this;
    }

    public function middleware(string|array ...$middleware): static
    {
        $this->attributes['middleware'] = array_merge($this->attributes['middleware'] ?? [], ...array_map(fn($m) => (array) $m, $middleware));
        return $this;
    }

    public function name(string $prefix): static
    {
        $this->attributes['name'] = $prefix;
        return $this;
    }

    public function group(callable $routes): void
    {
        $this->router->group($this->attributes, $routes);
    }
}
