<?php

// Framework helpers (loaded by config/bootstrap.php)

use System\Auth\Auth;
use System\Http\HttpException;
use System\Http\Request;
use System\Http\Session;
use System\Log\Logger;

if (!function_exists('app')) {
    /** The service container, or app(SomeClass::class) to resolve one */
    function app(?string $abstract = null, array $parameters = [])
    {
        $container = \System\Container\Container::getInstance();
        return $abstract === null ? $container : $container->make($abstract, $parameters);
    }
}

if (!function_exists('__')) {
    /** Translate: __('Sign in'), __('messages.welcome', ['name' => 'Rahim']) */
    function __(string $key, array $replace = [], ?string $locale = null): string
    {
        return \System\Translation\Lang::translator()->get($key, $replace, $locale);
    }
}

if (!function_exists('trans_choice')) {
    /** Plural: trans_choice('{0} No orders|{1} One order|[2,*] :count orders', $n) */
    function trans_choice(string $key, int|float $count, array $replace = []): string
    {
        return \System\Translation\Lang::translator()->choice($key, $count, $replace);
    }
}

if (!function_exists('locale')) {
    /** Current language code ("en", "bn") */
    function locale(): string
    {
        return \System\Translation\Lang::locale();
    }
}

if (!function_exists('config')) {
    function config($key, $default = null)
    {
        $value = $GLOBALS['config'] ?? [];
        foreach (explode('.', $key) as $k) {
            if (is_array($value) && array_key_exists($k, $value)) {
                $value = $value[$k];
            } else {
                return $default;
            }
        }
        return $value;
    }
}

if (!function_exists('base_path')) {
    function base_path($path = '')
    {
        return BASE_PATH . ($path ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('url')) {
    /** Absolute URL for a path inside the app: url('/products') */
    function url($path = '/')
    {
        return rtrim(config('app.url', ''), '/') . '/' . ltrim($path, '/');
    }
}

if (!function_exists('route')) {
    /**
     * URL of a named route: route('products.edit', ['id' => 5]).
     * A path still works too: route('/products').
     */
    function route(string $nameOrPath, array $params = [])
    {
        $router = \System\Routing\Router::current();
        if (!str_starts_with($nameOrPath, '/') && $router && $router->has($nameOrPath)) {
            return url($router->url($nameOrPath, $params));
        }
        return url($nameOrPath);
    }
}

if (!function_exists('signed_route')) {
    /** A route URL that can't be changed and stops working after $minutes (check it with ->middleware('signed')) */
    function signed_route(string $name, array $params = [], int $minutes = 60): string
    {
        return \System\Security\SignedUrl::make(route($name, $params), $minutes);
    }
}

if (!function_exists('relative_path')) {
    /** "D:\project/database\x.php" → "database/x.php" (paths inside the project, for messages) */
    function relative_path(string $file): string
    {
        $file = str_replace('\\', '/', $file);
        $base = str_replace('\\', '/', BASE_PATH) . '/';
        return str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}

if (!function_exists('asset')) {
    function asset($path)
    {
        return url($path);
    }
}

if (!function_exists('broadcast')) {
    /** Push a live update to open pages: broadcast('orders', 'order.created', ['id' => 5]) */
    function broadcast(string $channel, string $event, array $data = []): int
    {
        return \System\Broadcasting\Broadcast::to($channel)->send($event, $data);
    }
}

if (!function_exists('websocket_url')) {
    /** Where the browser connects: BongoSocket.connect('{{ websocket_url() }}') */
    function websocket_url(): string
    {
        return \System\Broadcasting\Broadcast::publicUrl();
    }
}

if (!function_exists('channel_token')) {
    /** Lets this page subscribe to a private- channel: socket.subscribe('private-user.5', '{{ channel_token('private-user.5') }}') */
    function channel_token(string $channel, int $seconds = 3600): string
    {
        return \System\Broadcasting\Broadcast::token($channel, $seconds);
    }
}

if (!function_exists('e')) {
    /** HTML-escape a value */
    function e($value)
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('request')) {
    /** request() -> all input, request('key', $default) -> one value */
    function request($key = null, $default = null)
    {
        return $key === null ? Request::all() : Request::input($key, $default);
    }
}

if (!function_exists('session')) {
    function session($key = null, $default = null)
    {
        return $key === null ? ($_SESSION ?? []) : Session::get($key, $default);
    }
}

if (!function_exists('flash')) {
    /** flash('success', 'Saved') sets a message; flash('success') reads one */
    function flash($key, $value = null)
    {
        if (func_num_args() === 1) {
            return Session::getFlash($key);
        }
        Session::flash($key, $value);
        return null;
    }
}

if (!function_exists('old')) {
    /** Previous form input after a failed validation */
    function old($key, $default = '')
    {
        $old = Session::getFlash('_old', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('errors')) {
    function errors()
    {
        return Session::getFlash('_errors', []);
    }
}

if (!function_exists('error')) {
    /** First validation error for a field, or null */
    function error($field)
    {
        return errors()[$field] ?? null;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token()
    {
        return Session::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field()
    {
        return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    /** Hidden _method field so an HTML form can send PUT, PATCH or DELETE */
    function method_field(string $method)
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('auth')) {
    /** The signed-in user of the default guard, or of a named one: auth('customer') */
    function auth(?string $guard = null)
    {
        return $guard === null ? Auth::user() : Auth::guard($guard)->user();
    }
}

if (!function_exists('can')) {
    /** May the signed-in user do this? can('delete', $product) · can('manage-system') */
    function can(string $ability, mixed ...$arguments): bool
    {
        return \System\Auth\Gate::allows($ability, ...$arguments);
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = '')
    {
        throw new HttpException($status, $message);
    }
}

if (!function_exists('redirect')) {
    function redirect($url)
    {
        $cleanUrl = str_replace(["\r", "\n", "\0"], '', (string) $url);
        header('Location: ' . $cleanUrl, true, 302);
        exit;
    }
}

if (!function_exists('back')) {
    /** Redirect to the previous page: last GET page in the session, else a same-host Referer, else "/" */
    function back()
    {
        $previous = Session::get('_previous_url');
        if ($previous) {
            redirect(url($previous));
        }
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $host = parse_url(config('app.url', ''), PHP_URL_HOST);
        redirect(($referer && parse_url($referer, PHP_URL_HOST) === $host) ? $referer : url('/'));
    }
}

if (!function_exists('response')) {
    function response($data, $type = 'json', int $status = 200)
    {
        http_response_code($status);
        switch ($type) {
            case 'json':
                header('Content-Type: application/json');
                echo json_encode($data);
                break;
            case 'html':
                header('Content-Type: text/html');
                echo $data;
                break;
        }
        exit;
    }
}

if (!function_exists('cache_for')) {
    /**
     * Let the browser keep this response, and skip resending it when nothing changed.
     *
     *   cache_for(300);                                   // reuse for 5 minutes
     *   cache_for(300, etag: $product->updated_at);       // after that, 304 Not Modified while unchanged
     *   cache_for(3600, public: true);                    // shared caches (CDN) too: only for pages that are the same for everyone
     *
     * Call it in the controller before returning. A matching If-None-Match ends the request with 304.
     */
    function cache_for(int $seconds, mixed $etag = null, bool $public = false): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('Pragma');    // sent by session_start(); conflicts with caching
        header_remove('Expires');
        header('Cache-Control: ' . ($public ? 'public' : 'private') . ', max-age=' . max(0, $seconds));
        if ($etag === null) {
            return;
        }
        $tag = '"' . sha1(is_scalar($etag) || $etag instanceof \Stringable ? (string) $etag : serialize($etag)) . '"';
        header('ETag: ' . $tag);
        header('Vary: Cookie', false);   // a signed-in and a guest page never share one cache entry
        $sent = array_map('trim', explode(',', (string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')));
        if (in_array($tag, $sent, true) || in_array('W/' . $tag, $sent, true)) {
            http_response_code(304);
            exit;
        }
    }
}

if (!function_exists('logger')) {
    /** logger() -> the Logger; logger('message', $context) -> write a debug line */
    function logger(?string $message = null, array $context = [])
    {
        if ($message === null) {
            return Logger::instance();
        }
        Logger::instance()->debug($message, $context);
        return null;
    }
}

if (!function_exists('dump')) {
    /**
     * Show values and keep going. HTML in the browser, text in the terminal,
     * JSON for API requests. Returns the first value, so it can wrap expressions.
     */
    function dump(...$values)
    {
        $dumper = new \System\Debug\Dumper();
        $context = \System\Debug\Dumper::context();
        switch (\System\Debug\Dumper::format()) {
            case 'text':
                echo "\u{25B6} " . \System\Debug\Dumper::headline($context) . "\n";
                foreach ($values as $value) {
                    echo $dumper->text($value);
                }
                break;
            case 'json':
                echo json_encode(['dump' => array_map([$dumper, 'plain'], $values)] + array_filter($context), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR), "\n";
                break;
            default:
                echo \System\Debug\Dumper::styles();
                echo '<div class="bongo-dump-src">' . htmlspecialchars(\System\Debug\Dumper::headline(['request' => null] + $context)) . '</div>';
                foreach ($values as $value) {
                    echo $dumper->html($value);
                }
        }
        return $values[0] ?? null;
    }
}

if (!function_exists('dd')) {
    /** Dump the values and stop the request ("dump and die") */
    function dd(...$values)
    {
        $dumper = new \System\Debug\Dumper();
        $context = \System\Debug\Dumper::context();
        $at = $context['at'];
        $format = \System\Debug\Dumper::format();

        if ($format === 'text') {
            echo "\u{25B6} " . \System\Debug\Dumper::headline($context) . "\n";
            foreach ($values as $value) {
                echo $dumper->text($value);
            }
            exit(1);
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (!headers_sent()) {
            http_response_code(500);
        }

        if ($format === 'json') {
            if (!headers_sent()) {
                header('Content-Type: application/json');
            }
            echo json_encode(['dd' => array_map([$dumper, 'plain'], $values)] + array_filter($context), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
            exit;
        }

        // Where it was called (method, file, line, request), the source around it, each value in a card
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo (new \System\Debug\DdPage($values, $context))->render();
        exit;
    }
}

if (!function_exists('debug_stats')) {
    /**
     * Numbers for the debug footer: queries, query time, page time, memory, and
     * "repeated": the same SQL run 3+ times with different values, usually a query
     * inside a loop (N+1). Fix it by loading the relation up front: Post::with('author')->get().
     */
    function debug_stats(): array
    {
        $log = \System\Database\DB::getQueryLog();
        $repeated = array_filter(array_count_values(array_column($log, 'sql')), fn($n) => $n >= 3);
        arsort($repeated);
        return [
            'queries'  => count($log),
            'query_ms' => round(array_sum(array_column($log, 'ms')), 1),
            'page_ms'  => round((microtime(true) - (defined('BONGO_START') ? BONGO_START : microtime(true))) * 1000, 1),
            'memory'   => round(memory_get_peak_usage() / 1048576, 1),
            'log'      => $log,
            'repeated' => $repeated,
        ];
    }
}

if (!function_exists('dispatch')) {
    /** Queue a job: dispatch(new SendInvoice($id)) or dispatch($job, 60) to delay it a minute */
    function dispatch(\System\Queue\Job $job, int $delaySeconds = 0): ?int
    {
        return \System\Queue\Queue::push($job, $delaySeconds);
    }
}

if (!function_exists('money')) {
    function money($amount)
    {
        return '$' . number_format((float) $amount, 2);
    }
}

if (!function_exists('is_active')) {
    /** 'active' when the current path starts with $prefix (for nav links) */
    function is_active(string $prefix)
    {
        $path = Request::path();
        return ($prefix === '/' ? $path === '/' : str_starts_with($path, $prefix)) ? 'active' : '';
    }
}

if (!function_exists('clean_html')) {
    /**
     * Clean untrusted HTML of XSS attack vectors while preserving safe rich-text markup.
     */
    function clean_html(string $html, ?array $allowedTags = null): string
    {
        return \System\Security\HtmlSanitizer::clean($html, $allowedTags);
    }
}

