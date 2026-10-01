<?php
namespace System\View;

use Exception;

class ViewEngine
{
    protected string $viewPath;
    protected string $cachePath;
    protected ViewCompiler $compiler;
    protected SectionManager $sections;

    public function __construct(string $viewPath, string $cachePath)
    {
        $this->viewPath = rtrim($viewPath, '/');
        $this->cachePath = rtrim($cachePath, '/');
        $this->sections = new SectionManager();
        $this->compiler = new ViewCompiler($this->sections);
    }

    public function render(string $view, array $data = []): string
    {
        $content = file_get_contents($this->getViewPath($view));

        // --- Detect and strip @extends ---
        $layout = null;
        if (preg_match('/@extends\([\'"](.+?)[\'"]\)/', $content, $match)) {
            $layout = $match[1];
            $content = str_replace($match[0], '', $content);
        }

        // Child sections are captured inside SectionManager while it runs
        $output = $this->evaluate($this->compiler->compile($content), $data);

        if ($layout === null) {
            return $output;
        }

        // Layout is compiled from its own source only; @yield reads the
        // captured sections at runtime, so rendered HTML is never re-compiled
        return $this->render($layout, $data);
    }

    /**
     * Run compiled view code in an isolated scope and return its output
     */
    protected function evaluate(string $__compiled, array $__data): string
    {
        extract($__data, EXTR_SKIP);
        ob_start();
        try {
            eval('?>' . $__compiled);
        } catch (\Throwable $__e) {
            ob_end_clean();
            throw $__e;
        }
        return ob_get_clean();
    }

    protected function getViewPath(string $view): string
    {
        $path = "{$this->viewPath}/" . str_replace('.', '/', $view) . ".php";
        if (!file_exists($path)) {
            throw new Exception("View not found: $path");
        }
        return $path;
    }
}
