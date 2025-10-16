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
        $childPath = $this->getViewPath($view);
        $childContent = file_get_contents($childPath);

        // --- Compile child file ---
        $compiledChild = $this->compiler->compile($childContent);

        // Extract variables for child view
        extract($data, EXTR_OVERWRITE);
        ob_start();
        eval('?>' . $compiledChild);
        ob_end_clean(); // sections are captured inside SectionManager

        // --- Check for @extends ---
        if (preg_match('/@extends\([\'"](.+?)[\'"]\)/', $childContent, $match)) {
            $layout = $match[1];
            $layoutPath = $this->getViewPath($layout);
            $layoutContent = file_get_contents($layoutPath);

            // Replace @yield('section') in layout with captured sections
            $compiledLayout = preg_replace_callback('/@yield\([\'"](.+?)[\'"]\)/', function ($m) {
                return $this->sections->get($m[1]);
            }, $layoutContent);

            // Compile and render layout
            $compiledLayout = $this->compiler->compile($compiledLayout);
            ob_start();
            eval('?>' . $compiledLayout);
            return ob_get_clean();
        }

        // No layout, just render child
        $compiledChild = $this->compiler->compile($childContent);
        ob_start();
        eval('?>' . $compiledChild);
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
