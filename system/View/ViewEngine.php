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
    protected static ?ViewEngine $shared = null;

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

    public function render(string $view, array $data = []): string
    {
        if ($this->depth === 0) {
            $this->sections->reset();
        }
        $this->depth++;

        $parentLayout = $this->extends;
        $this->extends = null;

        try {
            // Child sections are captured while it runs; its own output is
            // discarded when it extends a layout
            $output = $this->evaluate($this->getViewPath($view), $data);

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

    /** Used by compiled @extends */
    public function extend(string $layout): void
    {
        $this->extends = $layout;
    }

    /** Used by compiled @include: the caller's variables plus $data */
    public function includeView(array $scope, string $view, array $data = []): string
    {
        unset($scope['__file'], $scope['__data'], $scope['__compiled'], $scope['__e']);
        return $this->render($view, array_merge($scope, $data));
    }

    /** Compile a template (for tests and tools) */
    public function compileString(string $template): string
    {
        return $this->compiler->compile($template);
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

    protected function evaluate(string $__file, array $__data): string
    {
        $__compiled = $this->compiledFile($__file);

        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            if ($__compiled !== null) {
                include $__compiled;
            } else {
                eval('?>' . $this->compiler->compile(file_get_contents($__file)));
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

        $code = "<?php /* compiled from " . str_replace('*/', '', basename($source)) . " */ ?>\n" . $this->compiler->compile(file_get_contents($source));

        // Write to a temporary file then rename, so a half-written file is never included
        $tmp = $target . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $code) === false || !@rename($tmp, $target)) {
            @unlink($tmp);
            return null;
        }

        // Remove older compiled versions of the same template
        foreach (glob($this->cachePath . '/' . $prefix . '_*.php') ?: [] as $old) {
            if ($old !== $target) @unlink($old);
        }

        return $target;
    }

    protected function pathFor(string $view): string
    {
        return "{$this->viewPath}/" . str_replace('.', '/', $view) . '.php';
    }

    protected function getViewPath(string $view): string
    {
        $path = $this->pathFor($view);
        if (!is_file($path)) {
            throw new ViewException("View not found: $path");
        }
        return $path;
    }
}
