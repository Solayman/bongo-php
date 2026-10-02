<?php
require_once __DIR__ . '/../config/bootstrap.php';

use System\Http\ErrorPage;
use System\Http\HttpException;
use System\Http\Request;
use System\Log\Log;
use System\Routing\Router;

try {
    Router::fromConfig()->dispatch(Request::method(), Request::path());
} catch (HttpException $e) {
    $context = ['status' => $e->status(), 'method' => Request::method(), 'path' => Request::path(), 'ip' => \System\Http\Request::ip()];
    // 403 and 419 (bad CSRF token) can mean an attack; 404/405 are routine
    in_array($e->status(), [403, 419], true)
        ? Log::warning('HTTP {status} on {method} {path}', $context)
        : Log::debug('HTTP {status} on {method} {path}', $context);
    ErrorPage::render($e->status(), $e->getMessage());
} catch (Throwable $e) {
    $hint = System\Database\MigrationHint::for($e);
    Log::error('Uncaught ' . get_class($e) . ': ' . $e->getMessage() . ($hint ? " | HINT: $hint" : ''), [
        'method' => Request::method(), 'path' => Request::path(), 'exception' => $e,
    ]);
    ErrorPage::render(500, 'Server Error', config('app.debug') ? $e : null);
}
