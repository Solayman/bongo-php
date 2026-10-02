<?php
namespace System\Http\Middleware;

use System\Auth\Gate;
use System\Http\Request;

/**
 * "can:manage-system" → 403 unless the signed-in user has the ability.
 * Extra parameters name route parameters passed to the check:
 * "can:update,product" on /products/{product} → Gate::authorize('update', '20') for the raw id.
 * (Policies get models from controllers: $this->authorize('update', $product).)
 */
class Authorize
{
    public function handle(string $ability = '', string ...$routeParameters): void
    {
        $arguments = array_map(fn($name) => Request::route($name), $routeParameters);
        Gate::authorize($ability, ...$arguments);
    }
}
