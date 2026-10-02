<?php
namespace System\Routing;

use RuntimeException;
use System\Http\HttpException;

class Router
{
    protected array $routes = [];

    /**
     * @param array $routes     "METHOD /path/{param}" => [Controller, method, [middleware...]]
     * @param array $middleware alias => middleware class (from config/app.php)
     */
    public function __construct(array $routes, protected array $middleware = [])
    {
        foreach ($routes as $key => $handler) {
            if (preg_match('/^(GET|POST|ANY)\s+(.*)$/i', trim($key), $m)) {
                $method = strtoupper($m[1]);
                $path = $m[2];
            } else {
                $method = 'ANY';
                $path = trim($key);
            }

            $path = '/' . trim($path, '/');

            $this->routes[] = [
                'method'     => $method,
                'path'       => $path,
                'regex'      => $this->compile($path),
                'controller' => $handler[0],
                'action'     => $handler[1],
                'middleware' => $handler[2] ?? [],
            ];
        }
    }

    /**
     * "/products/{id}/edit" -> "#^/products/([^/]+)/edit$#"
     */
    protected function compile(string $path): string
    {
        $parts = preg_split('/(\{\w+\})/', $path, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';
        foreach ($parts as $part) {
            $regex .= preg_match('/^\{\w+\}$/', $part) ? '([^/]+)' : preg_quote($part, '#');
        }
        return '#^' . $regex . '$#';
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $pathMatched = false;

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $params)) {
                continue;
            }

            if ($route['method'] !== 'ANY' && $route['method'] !== $method) {
                $pathMatched = true;
                continue;
            }

            array_shift($params);

            // Remember the last page shown, so back() works without a Referer header
            if ($method === 'GET' && PHP_SAPI !== 'cli') {
                $query = $_SERVER['QUERY_STRING'] ?? '';
                \System\Http\Session::put('_previous_url', $path . ($query !== '' ? "?$query" : ''));
            }

            foreach ($route['middleware'] as $alias) {
                $this->runMiddleware($alias);
            }

            $this->callAction($route['controller'], $route['action'], array_map('urldecode', $params));
            return;
        }

        throw new HttpException($pathMatched ? 405 : 404);
    }

    protected function runMiddleware(string $alias): void
    {
        $class = $this->middleware[$alias] ?? null;
        if (!$class || !class_exists($class)) {
            throw new RuntimeException("Middleware [$alias] is not registered in config/app.php");
        }
        (new $class())->handle();
    }

    protected function callAction(string $controller, string $action, array $params): void
    {
        $class = "App\\Http\\Controllers\\$controller";

        if (!class_exists($class)) {
            throw new RuntimeException("Controller class $class not found");
        }

        $instance = new $class();

        if (!method_exists($instance, $action)) {
            throw new RuntimeException("Method $action not found in controller $class");
        }

        $instance->$action(...$params);
    }
}
