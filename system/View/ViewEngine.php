<?php

namespace System\View;

/**
 * Renders templates from resources/views.
 *
 * Templates are compiled once to PHP files in the cache directory and reused
 * until the template changes (see config/view.php). Without a writable cache
 * directory they are compiled in memory on every render.
 */
class ViewEngine
{
    protected string $viewPath;
    protected string $cachePath;
    protected ViewCompiler $compiler;
    protected SectionManager $sections;
    protected int $depth = 0;
    protected ?string $extends = null;
    /** view name => compiled file, for the current top-level render (an @include in a loop is resolved once) */
    protected array $resolved = [];
    /** Views rendered during the current request for DebugBar */
    protected array $renderedViews = [];
    protected static ?ViewEngine $shared = null;
    /** Variables every view gets (see share()) */
    protected static array $sharedData = [];
    /** Package view namespaces: name => folder path */
    protected static array $namespaces = [];

    public static function addNamespace(string $namespace, string $path): void
    {
        self::$namespaces[$namespace] = rtrim($path, '/\\');
    }

    public function __construct(string $viewPath, string $cachePath, protected bool $cache = true)
    {
        $this->viewPath = rtrim($viewPath, '/\\');
        $this->cachePath = rtrim($cachePath, '/\\');
        $this->sections = new SectionManager();
        $this->compiler = new ViewCompiler();
    }

    /** One engine per request, configured from config/view.php */
    public static function shared(): self
    {
        return self::$shared ??= new self(
            (string) config('view.path', base_path('resources/views')),
            (string) config('view.compiled', base_path('storage/cache/views')),
            (bool) config('view.cache', true)
        );
    }

    /**
     * Give every view a variable (in app/bootstrap.php):
     *   ViewEngine::share('appName', 'Bongo Shop');
     *   ViewEngine::share('cartCount', fn() => app(Cart::class)->count());   // a closure runs once per page, only when rendering
     * Data passed to render() wins over shared values.
     */
    public static function share(string|array $key, mixed $value = null): void
    {
        foreach (is_array($key) ? $key : [$key => $value] as $name => $item) {
            self::$sharedData[$name] = $item;
        }
    }

    public function getRenderedViews(): array
    {
        return $this->renderedViews;
    }

    public function clearRenderedViews(): void
    {
        $this->renderedViews = [];
    }

    public function render(string $view, array $data = []): string
    {
        if ($this->depth === 0) {
            $this->sections->reset();
            $this->resolved = [];   // fresh per page, so edited templates are picked up by long-running workers
            foreach (self::$sharedData as $name => $value) {
                if (!array_key_exists($name, $data)) {
                    $data[$name] = $value instanceof \Closure ? $value() : $value;
                }
            }
        }
        $this->depth++;

        $parentLayout = $this->extends;
        $this->extends = null;

        $start = microtime(true);
        try {
            // Child sections are captured while it runs; its own output is
            // discarded when it extends a layout
            $output = $this->evaluate($view, $data);

            $this->renderedViews[] = [
                'name'      => $view,
                'path'      => $this->pathFor($view),
                'data_keys' => array_keys($data),
                'ms'        => round((microtime(true) - $start) * 1000, 2),
            ];

            $layout = $this->extends;
            $this->extends = $parentLayout;

            return $layout === null ? $output : $this->render($layout, $data);
        } finally {
            $this->extends = $parentLayout;
            $this->depth--;
        }
    }

    public function exists(string $view): bool
    {
        return is_file($this->pathFor($view));
    }

    /** Used by compiled @includeFirst */
    public function firstExisting(array $views): string
    {
        foreach ($views as $view) {
            if ($this->exists($view)) {
                return $view;
            }
        }
        throw new ViewException('None of these views exist: ' . implode(', ', $views));
    }

    /** Used by compiled @each('partials.card', $items, 'item', 'partials.empty') */
    public function renderEach(string $view, iterable $items, string $as, ?string $empty = null): string
    {
        $html = '';
        foreach ($items as $key => $item) {
            $html .= $this->render($view, ['key' => $key, $as => $item]);
        }
        if ($html === '' && $empty !== null) {
            $html = str_starts_with($empty, 'raw|') ? substr($empty, 4) : $this->render($empty);
        }
        return $html;
    }

    /** @class(['btn', 'active' => $on]) → "btn active": number keys always, string keys when their value is true */
    public static function classList(array $classes): string
    {
        $list = [];
        foreach ($classes as $key => $value) {
            if (is_int($key)) {
                if ($value !== null && $value !== '') {
                    $list[] = (string) $value;
                }
            } elseif ($value) {
                $list[] = $key;
            }
        }
        return implode(' ', array_unique($list));
    }

    /** Used by compiled @extends */
    public function extend(string $layout): void
    {
        $this->extends = $layout;
    }

    /** Open components: [name, attributes] */
    protected array $components = [];

    /** Used by compiled <x-name ...>: remember the attributes and capture the slot */
    public function startComponent(string $name, array $attributes): void
    {
        $this->components[] = [$name, $attributes];
        ob_start();
    }

    /**
     * Used by compiled </x-name>: render components/name.php with each attribute as a variable
     * (max-length → $maxLength), $attributes (all of them) and $slot (the inner HTML).
     */
    public function endComponent(): string
    {
        $slot = new HtmlString(ob_get_clean());
        [$name, $attributes] = array_pop($this->components);

        $data = [];
        foreach ($attributes as $key => $value) {
            $data[lcfirst(str_replace(' ', '', ucwords(str_replace(['-', '.'], ' ', $key))))] = $value;
        }
        $view = 'components.' . $name;
        if (!$this->exists($view)) {
            throw new ViewException("Component <x-$name> needs the file resources/views/components/" . str_replace('.', '/', $name) . '.php');
        }
        return $this->render($view, $data + ['attributes' => $attributes, 'slot' => $slot]);
    }

    /** {{ $value }}: escaped, except HTML the template itself produced (a component's $slot) */
    public static function escape(mixed $value): string
    {
        return $value instanceof HtmlString ? (string) $value : htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }

    /** Used by compiled @include: the caller's variables (minus engine internals) plus $data */
    public function includeView(array $scope, string $view, array $data = []): string
    {
        $scope = array_filter($scope, fn($key) => !str_starts_with((string) $key, '__'), ARRAY_FILTER_USE_KEY);
        return $this->render($view, array_merge($scope, $data));
    }

    /** Compile one template file; errors name the file, e.g. "products/index.php: @empty without arguments ..." */
    protected function compileFile(string $source): string
    {
        try {
            return $this->compiler->compile((string) file_get_contents($source));
        } catch (ViewException $e) {
            $name = ltrim(str_replace('\\', '/', substr($source, strlen($this->viewPath))), '/');
            throw new ViewException("$name: " . $e->getMessage(), 0, $e);
        }
    }

    /** Compile a template (for tests and tools) */
    public function compileString(string $template): string
    {
        return $this->compiler->compile($template);
    }

    /**
     * Compile every template ahead of time (deploys: no compile on the first request).
     * Returns ['compiled' => n, 'errors' => [view => message]]; with $lint each compiled
     * file is also checked with `php -l`, so a broken template fails the deploy, not a visitor.
     */
    public function compileAll(bool $lint = false): array
    {
        $compiled = 0;
        $errors = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->viewPath, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());
            // Skip non-templates, and compiled files when the cache lives inside the view folder
            if ($file->getExtension() !== 'php' || str_starts_with($path, str_replace('\\', '/', $this->cachePath) . '/')) {
                continue;
            }
            $relative = substr($path, strlen(str_replace('\\', '/', $this->viewPath)) + 1);
            $view = str_replace('/', '.', substr($relative, 0, -4));
            try {
                $target = $this->compiledFile($file->getPathname());
                if ($target === null) {
                    throw new ViewException('the cache directory is not writable (or VIEW_CACHE is off)');
                }
                if ($lint) {
                    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($target) . ' 2>&1', $out, $code);
                    if ($code !== 0) {
                        $message = trim(implode(' ', array_filter($out, fn($l) => str_contains($l, 'error'))));
                        $out = [];
                        throw new ViewException($message !== '' ? $message : 'syntax error');
                    }
                    $out = [];
                }
                $compiled++;
            } catch (\Throwable $e) {
                $errors[$view] = $e->getMessage();
            }
        }
        ksort($errors);
        return ['compiled' => $compiled, 'errors' => $errors];
    }

    /**
     * The conventional view for a controller action:
     * ProductController::index → products.index, Shop\CatalogController::show → shop.catalogs.show
     */
    public static function guessName(string $controller, string $method): string
    {
        $relative = preg_replace('/^App\\\\Http\\\\Controllers\\\\/', '', $controller);
        $parts = explode('\\', $relative);
        $class = (string) array_pop($parts);
        $base = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', preg_replace('/Controller$/', '', $class)));
        $base = str_ends_with($base, 'y') && !preg_match('/[aeiou]y$/', $base) ? substr($base, 0, -1) . 'ies'
            : (preg_match('/(s|x|z|ch|sh)$/', $base) ? $base . 'es' : $base . 's');
        $action = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $method));
        return implode('.', array_merge(array_map('strtolower', $parts), [$base, $action]));
    }

    /** Delete every compiled view; returns how many files were removed */
    public function clearCache(): int
    {
        $count = 0;
        foreach (glob($this->cachePath . '/*.php') ?: [] as $file) {
            $count += (int) @unlink($file);
        }
        return $count;
    }

    protected function evaluate(string $__view, array $__data): string
    {
        if (!array_key_exists($__view, $this->resolved)) {
            $source = $this->getViewPath($__view);
            $this->resolved[$__view] = [$source, $this->compiledFile($source)];
        }
        [$__file, $__compiled] = $this->resolved[$__view];
        unset($source);

        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            if ($__compiled !== null) {
                include $__compiled;
            } else {
                eval('?>' . $this->compileFile($__file));
            }
        } catch (\Throwable $__e) {
            ob_end_clean();
            throw $__e;
        }
        return ob_get_clean();
    }

    /**
     * Path of the up-to-date compiled file, or null when caching is off or impossible
     */
    protected function compiledFile(string $source): ?string
    {
        if (!$this->cache) {
            return null;
        }

        // The name changes whenever the template's modification time or size changes
        clearstatcache(true, $source);
        $prefix = sha1(ViewCompiler::VERSION . '|' . $source);
        $target = $this->cachePath . '/' . $prefix . '_' . filemtime($source) . '_' . filesize($source) . '.php';

        if (is_file($target)) {
            return $target;
        }

        if (!is_dir($this->cachePath) && !@mkdir($this->cachePath, 0775, true) && !is_dir($this->cachePath)) {
            return null;
        }

        $code = "<?php /* compiled from " . str_replace('*/', '', basename($source)) . " */ ?>\n" . $this->compileFile($source);

        // Write to a temporary file then rename, so a half-written file is never included
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $code) === false || !@rename($tmp, $target)) {
            @unlink($tmp);
            return null;
        }

        // Remove older compiled versions of the same template
        foreach (glob($this->cachePath . '/' . $prefix . '_*.php') ?: [] as $old) {
            if ($old !== $target) {
                @unlink($old);
            }
        }

        return $target;
    }

    protected function pathFor(string $view): string
    {
        if (str_contains($view, "\0") || str_contains($view, '..')) {
            throw new ViewException("Invalid view name [$view]: path traversal is not permitted.");
        }
        if (str_contains($view, '::')) {
            [$namespace, $relative] = explode('::', $view, 2);
            if (str_contains($relative, "\0") || str_contains($relative, '..')) {
                throw new ViewException("Invalid view name [$view]: path traversal is not permitted.");
            }
            $subPath = str_replace('.', '/', $relative) . '.php';
            // App override takes precedence: resources/views/vendor/<namespace>/<view>.php
            $override = "{$this->viewPath}/vendor/{$namespace}/{$subPath}";
            if (is_file($override)) {
                return $override;
            }
            if (isset(self::$namespaces[$namespace])) {
                return self::$namespaces[$namespace] . "/{$subPath}";
            }
        }
        return "{$this->viewPath}/" . str_replace('.', '/', $view) . '.php';
    }

    protected function getViewPath(string $view): string
    {
        $path = $this->pathFor($view);
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            throw new ViewException("View not found: $path");
        }

        // Verify the resolved canonical path is strictly inside the views root or a registered namespace
        $allowed = false;
        $realViewPath = realpath($this->viewPath);
        if ($realViewPath && str_starts_with($real, $realViewPath . DIRECTORY_SEPARATOR)) {
            $allowed = true;
        } else {
            foreach (self::$namespaces as $nsDir) {
                $realNs = realpath($nsDir);
                if ($realNs && str_starts_with($real, $realNs . DIRECTORY_SEPARATOR)) {
                    $allowed = true;
                    break;
                }
            }
        }

        if (!$allowed) {
            throw new ViewException("View [$view] resolves outside permitted view paths.");
        }

        return $real;
    }
}
