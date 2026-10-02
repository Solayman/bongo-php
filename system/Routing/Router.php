<?php
namespace System\Routing;

use RuntimeException;
use System\Http\HttpException;
use System\Http\Session;

/**
 * Matches the request to a route, runs its middleware, then the action.
 *
 * Routes come from route files (config/app.php → 'routes'), written either with
 * the Route facade or as an array: "METHOD /path/{param}" => [Controller, method, [middleware]].
 */
class Router
{
    /** @var RouteDefinition[] */
    protected array $routes = [];
    protected array $groupStack = [];
    protected static ?Router $current = null;

    public const VERBS = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

    /**
     * @param array $routes     Array-style routes (optional)
     * @param array $middleware alias => class
     * @param array $groups     group name => [aliases...]
     * @param array $global     middleware run on every request
     */
    public function __construct(
        array $routes = [],
        protected array $middleware = [],
        protected array $groups = [],
        protected array $global = []
    ) {
        self::$current = $this;
        $this->addArrayRoutes($routes);
    }

    /** The router being configured or dispatched (used by Route:: and route()) */
    public static function current(): ?Router
    {
        return self::$current;
    }

    /**
     * Build the router from config/app.php: middleware aliases, groups, global stack and route files
     */
    public static function fromConfig(): self
    {
        $router = new self(
            [],
            (array) config('app.middleware', []),
            (array) config('app.middleware_groups', []),
            (array) config('app.middleware_global', [])
        );

        foreach ((array) config('app.routes', ['web' => ['file' => 'routes/web.php']]) as $file) {
            $router->loadFile(base_path($file['file']), $file);
        }

        return $router;
    }

    /**
     * Load one route file inside a group (prefix / middleware / name from its config)
     */
    public function loadFile(string $file, array $attributes = []): void
    {
        if (!is_file($file)) {
            return;
        }
        self::$current = $this;
        $this->group(array_intersect_key($attributes, array_flip(['prefix', 'middleware', 'name'])), function () use ($file) {
            $result = require $file;
            if (is_array($result)) {
                $this->addArrayRoutes($result);
            }
        });
    }

    // ------------------------------------------------------------------
    // Registration
    // ------------------------------------------------------------------

    public function get(string $path, array|\Closure $action): RouteDefinition     { return $this->add(['GET'], $path, $action); }
    public function post(string $path, array|\Closure $action): RouteDefinition    { return $this->add(['POST'], $path, $action); }
    public function put(string $path, array|\Closure $action): RouteDefinition     { return $this->add(['PUT'], $path, $action); }
    public function patch(string $path, array|\Closure $action): RouteDefinition   { return $this->add(['PATCH'], $path, $action); }
    public function delete(string $path, array|\Closure $action): RouteDefinition  { return $this->add(['DELETE'], $path, $action); }
    public function options(string $path, array|\Closure $action): RouteDefinition { return $this->add(['OPTIONS'], $path, $action); }
    public function any(string $path, array|\Closure $action): RouteDefinition     { return $this->add(self::VERBS, $path, $action); }

    public function match(array $methods, string $path, array|\Closure $action): RouteDefinition
    {
        return $this->add(array_map('strtoupper', $methods), $path, $action);
    }

    /**
     * Shared attributes for the routes registered inside $routes:
     *   ['prefix' => '/admin', 'middleware' => ['auth'], 'name' => 'admin.']
     */
    public function group(array $attributes, callable $routes): void
    {
        $parent = end($this->groupStack) ?: ['prefix' => '', 'middleware' => [], 'name' => ''];
        $this->groupStack[] = [
            'prefix'     => rtrim($parent['prefix'] . '/' . trim($attributes['prefix'] ?? '', '/'), '/'),
            'middleware' => array_merge($parent['middleware'], (array) ($attributes['middleware'] ?? [])),
            'name'       => $parent['name'] . ($attributes['name'] ?? ''),
        ];
        try {
            $routes();
        } finally {
            array_pop($this->groupStack);
        }
    }

    protected function add(array $methods, string $path, array|\Closure $action): RouteDefinition
    {
        $group = end($this->groupStack) ?: ['prefix' => '', 'middleware' => [], 'name' => ''];
        if (in_array('GET', $methods, true) && !in_array('HEAD', $methods, true)) {
            $methods[] = 'HEAD';
        }

        $route = new RouteDefinition($methods, $group['prefix'] . '/' . ltrim($path, '/'), $action, $group['name']);
        $route->middleware($group['middleware']);

        return $this->routes[] = $route;
    }

    protected function addArrayRoutes(array $routes): void
    {
        foreach ($routes as $key => $handler) {
            if (preg_match('/^([A-Z|]+)\s+(.*)$/i', trim($key), $m)) {
                $methods = strtoupper($m[1]) === 'ANY' ? self::VERBS : explode('|', strtoupper($m[1]));
                $path = $m[2];
            } else {
                $methods = self::VERBS;
                $path = trim($key);
            }
            $this->add($methods, $path, [$handler[0], $handler[1]])->middleware($handler[2] ?? []);
        }
    }

    /** @return RouteDefinition[] */
    public function routes(): array
    {
        return $this->routes;
    }

    /** Path for a named route, e.g. url('products.edit', ['id' => 5]) → "/products/5/edit" */
    public function url(string $name, array $params = []): string
    {
        foreach ($this->routes as $route) {
            if ($route->name === $name) {
                return $route->url($params);
            }
        }
        throw new \InvalidArgumentException("Route [$name] is not defined");
    }

    public function has(string $name): bool
    {
        foreach ($this->routes as $route) {
            if ($route->name === $name) return true;
        }
        return false;
    }

    // ------------------------------------------------------------------
    // Dispatch
    // ------------------------------------------------------------------

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        foreach ($this->global as $middleware) {
            $this->runMiddleware($middleware);
        }

        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $route->match($path);
            if ($params === null) {
                continue;
            }

            if (!in_array($method, $route->methods, true)) {
                $allowed = array_merge($allowed, $route->methods);
                continue;
            }

            foreach ($this->resolveMiddleware($route) as [$class, $args]) {
                $this->call($class, $args);
            }

            // Remember the last page shown, so back() works without a Referer header
            // (after middleware, because the session middleware starts the session)
            if ($method === 'GET' && PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_ACTIVE) {
                $query = $_SERVER['QUERY_STRING'] ?? '';
                Session::put('_previous_url', $path . ($query !== '' ? "?$query" : ''));
            }

            $this->callAction($route->action, array_values($params));
            return;
        }

        if ($allowed) {
            $allowed = array_values(array_unique(array_diff($allowed, ['HEAD'])));

            // CORS preflight / OPTIONS: run the first matching route's middleware (e.g. cors), then 204
            if ($method === 'OPTIONS') {
                foreach ($this->routes as $route) {
                    if ($route->match($path) !== null) {
                        foreach ($this->resolveMiddleware($route) as [$class, $args]) {
                            if (method_exists($class, 'preflight')) {
                                (new $class())->preflight(...$args);
                            }
                        }
                        break;
                    }
                }
                if (!headers_sent()) {
                    http_response_code(204);
                    header('Allow: ' . implode(', ', array_merge($allowed, ['OPTIONS'])));
                }
                return;
            }

            if (!headers_sent()) {
                header('Allow: ' . implode(', ', $allowed));
            }
            throw new HttpException(405);
        }

        throw new HttpException(404);
    }

    /**
     * Expand groups, apply withoutMiddleware, split parameters, de-duplicate.
     * @return array<int, array{0: string, 1: array}> [class, args]
     */
    public function resolveMiddleware(RouteDefinition $route): array
    {
        $expanded = [];
        $expand = function (array $items) use (&$expand, &$expanded) {
            foreach ($items as $item) {
                $name = explode(':', $item, 2)[0];
                if (isset($this->groups[$name])) {
                    $expand((array) $this->groups[$name]);
                } else {
                    $expanded[] = $item;
                }
            }
        };
        $expand($route->middleware);

        $skip = array_map(fn($m) => explode(':', $m, 2)[0], $route->withoutMiddleware);
        $resolved = [];

        foreach (array_unique($expanded) as $item) {
            [$name, $argString] = array_pad(explode(':', $item, 2), 2, null);
            if (in_array($name, $skip, true)) {
                continue;
            }
            $class = $this->middleware[$name] ?? $name;
            if (!class_exists($class)) {
                throw new RuntimeException("Middleware [$name] is not registered in config/app.php");
            }
            $resolved[] = [$class, $argString === null ? [] : explode(',', $argString)];
        }

        return $resolved;
    }

    protected function runMiddleware(string $item): void
    {
        [$name, $argString] = array_pad(explode(':', $item, 2), 2, null);
        $class = $this->middleware[$name] ?? $name;
        if (!class_exists($class)) {
            throw new RuntimeException("Middleware [$name] is not registered in config/app.php");
        }
        $this->call($class, $argString === null ? [] : explode(',', $argString));
    }

    protected function call(string $class, array $args): void
    {
        (new $class())->handle(...$args);
    }

    protected function callAction(array|\Closure $action, array $params): void
    {
        if ($action instanceof \Closure) {
            $result = $action(...$params);
            $this->send($result);
            return;
        }

        [$controller, $method] = $action;
        $class = class_exists($controller) ? $controller : "App\\Http\\Controllers\\$controller";

        if (!class_exists($class)) {
            throw new RuntimeException("Controller class $class not found");
        }

        $instance = new $class();

        if (!method_exists($instance, $method)) {
            throw new RuntimeException("Method $method not found in controller $class");
        }

        $this->send($instance->$method(...$params));
    }

    /** Actions may echo, or return a string (HTML) or an array (JSON) */
    protected function send($result): void
    {
        if (is_array($result) || $result instanceof \JsonSerializable) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode($result);
        } elseif (is_string($result)) {
            echo $result;
        }
    }
}
