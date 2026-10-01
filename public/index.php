<?php
require_once __DIR__ . '/../config/bootstrap.php';

use System\Http\ErrorPage;
use System\Http\HttpException;
use System\Http\Request;
use System\Routing\Router;

try {
    $router = new Router(require base_path('routes/web.php'), config('app.middleware', []));
    $router->dispatch(Request::method(), Request::path());
} catch (HttpException $e) {
    ErrorPage::render($e->status(), $e->getMessage());
} catch (Throwable $e) {
    error_log((string) $e);
    ErrorPage::render(500, 'Server Error', config('app.debug') ? $e : null);
}
