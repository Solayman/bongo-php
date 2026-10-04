<?php
namespace System\Routing;

/**
 * One registered route. Returned by Route::get() etc. so it can be refined:
 *   Route::get('/products/{id}', [ProductController::class, 'edit'])
 *       ->name('products.edit')->middleware('auth')->where('id', '\d+');
 */
class RouteDefinition
{
    public ?string $name = null;
    public array $middleware = [];
    public array $withoutMiddleware = [];
    public array $wheres = [];
    protected ?string $regex = null;
    protected array $paramNames = [];

    /**
     * @param string[]               $methods GET, POST, PUT, PATCH, DELETE, OPTIONS
     * @param array|\Closure         $action  [Controller, 'method'] or a closure
     */
    public function __construct(
        public array $methods,
        public string $path,
        public array|\Closure $action,
        protected ?string $namePrefix = null
    ) {
        $this->path = '/' . trim($path, '/');
    }

    public function name(string $name): static
    {
        $this->name = ($this->namePrefix ?? '') . $name;
        return $this;
    }

    /** Add middleware aliases, groups or classes ("auth", "auth:api", "throttle:60,1") */
    public function middleware(string|array ...$middleware): static
    {
        $this->middleware = array_merge($this->middleware, ...array_map(fn($m) => (array) $m, $middleware));
        return $this;
    }

    /** Skip middleware that a group would add, e.g. ->withoutMiddleware('csrf') */
    public function withoutMiddleware(string|array ...$middleware): static
    {
        $this->withoutMiddleware = array_merge($this->withoutMiddleware, ...array_map(fn($m) => (array) $m, $middleware));
        return $this;
    }

    /** Constrain a parameter: ->where('id', '\d+') or ->where(['id' => '\d+']) */
    public function where(string|array $param, ?string $pattern = null): static
    {
        $this->wheres = array_merge($this->wheres, is_array($param) ? $param : [$param => $pattern]);
        $this->regex = null;
        return $this;
    }

    /** Parameters as [name => value] when the path matches, otherwise null */
    public function match(string $path): ?array
    {
        // Fast path for static routes without parameters
        if (!str_contains($this->path, '{')) {
            return $this->path === $path ? [] : null;
        }

        if (!preg_match($this->regex(), $path, $m)) {
            return null;
        }
        array_shift($m);
        // Optional parameters at the end are left out of $m when absent
        $m = array_pad($m, count($this->paramNames), '');
        $values = array_map(fn($v) => $v === '' ? null : urldecode($v), $m);
        return $this->paramNames ? array_combine($this->paramNames, $values) : [];
    }

    protected function regex(): string
    {
        if ($this->regex !== null) {
            return $this->regex;
        }

        $this->paramNames = [];
        $parts = preg_split('/(\{\w+\??\})/', $this->path, -1, PREG_SPLIT_DELIM_CAPTURE);
        $regex = '';
        foreach ($parts as $part) {
            if (preg_match('/^\{(\w+)(\?)?\}$/', $part, $p)) {
                $this->paramNames[] = $p[1];
                $pattern = $this->wheres[$p[1]] ?? '[^/]+';
                // optional segment: "/posts/{page?}" also matches "/posts"
                if (!empty($p[2]) && str_ends_with($regex, '/')) {
                    $regex = substr($regex, 0, -1) . "(?:/($pattern))?";
                } else {
                    $regex .= "($pattern)" . (!empty($p[2]) ? '?' : '');
                }
            } else {
                $regex .= preg_quote($part, '#');
            }
        }

        return $this->regex = '#^' . $regex . '$#';
    }

    /** Build this route's path from parameters; unused parameters become the query string */
    public function url(array $params = []): string
    {
        $path = preg_replace_callback('/\{(\w+)(\?)?\}/', function ($m) use (&$params) {
            if (array_key_exists($m[1], $params)) {
                $value = rawurlencode((string) $params[$m[1]]);
                unset($params[$m[1]]);
                return $value;
            }
            if (!empty($m[2])) {
                return '';
            }
            throw new \InvalidArgumentException("Missing parameter [{$m[1]}] for route [{$this->name}]");
        }, $this->path);

        $path = '/' . trim(preg_replace('#/+#', '/', $path), '/');
        return $path . ($params ? '?' . http_build_query($params) : '');
    }
}
