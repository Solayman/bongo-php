<?php
require_once __DIR__ . '/../config/bootstrap.php';

// Get request path
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$base = parse_url(config('app.url'), PHP_URL_PATH) ?? '';
$uri = '/' . trim(str_replace($base, '', $uri), '/');

// Request method
$method = $_SERVER['REQUEST_METHOD'];

// Load routes
$routes = require __DIR__ . '/../routes/web.php';

$routeFound = false;


foreach ($routes as $route => $handler) {

    if (preg_match('/^(GET|POST|ANY)\s+(.*)$/i', $route, $matches)) {
        $routeMethod = strtoupper($matches[1]);
        $routePath = $matches[2];
    } else {
        $routeMethod = 'ANY';
        $routePath = $route;
    }

    if ($uri === $routePath && ($routeMethod === $method || $routeMethod === 'ANY')) {
        [$controllerName, $methodName] = $handler;
        $controllerFile = __DIR__ . "/../app/Http/Controllers/$controllerName.php";

        if (!file_exists($controllerFile)) {
            http_response_code(500);
            echo "Controller file not found: $controllerFile";
            exit;
        }

        require_once $controllerFile;

        $fullControllerName = "App\\Http\\Controllers\\$controllerName";

        if (!class_exists($fullControllerName)) {
            http_response_code(500);
            echo "Controller class $fullControllerName not found";
            exit;
        }

        $controller = new $fullControllerName();

        if (!method_exists($controller, $methodName)) {
            http_response_code(500);
            echo "Method $methodName not found in controller $fullControllerName";
            exit;
        }

        $controller->$methodName();
        $routeFound = true;
        break;
    }
}

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});


if (!$routeFound) {
    http_response_code(404);
    echo "404 Not Found";
}
