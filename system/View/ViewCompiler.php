<?php
namespace System\View;

class ViewCompiler
{
    protected SectionManager $sections;

    public function __construct(SectionManager $sections)
    {
        $this->sections = $sections;
    }

    public function compile(string $content): string
    {
        // --- @set('key', 'value') ---
        $content = preg_replace_callback('/@set\([\'"](.+?)[\'"]\s*,\s*[\'"](.*?)[\'"]\)/', function ($m) {
            return "<?php \$this->sections->set('{$m[1]}', '{$m[2]}'); ?>";
        }, $content);

        // --- @section('name') ... @endsection ---
        $content = preg_replace_callback('/@section\([\'"](.+?)[\'"]\)(.*?)@endsection/s', function ($m) {
            return "<?php \$this->sections->start('{$m[1]}'); ?>{$m[2]}<?php \$this->sections->end(); ?>";
        }, $content);

        // --- @yield('name') ---
        $content = preg_replace_callback('/@yield\([\'"](.+?)[\'"]\)/', function ($m) {
            return "<?php echo \$this->sections->get('{$m[1]}'); ?>";
        }, $content);

        // --- @include('partial') ---
        $content = preg_replace_callback('/@include\([\'"](.+?)[\'"]\)/', function ($m) {
            return "<?php echo \$this->render('{$m[1]}', get_defined_vars()); ?>";
        }, $content);

        // --- {{ variable }} ---
        $content = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/', function ($m) {
            return "<?php echo htmlspecialchars({$m[1]}, ENT_QUOTES, 'UTF-8'); ?>";
        }, $content);

        // --- {!! raw !!} ---
        $content = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/', function ($m) {
            return "<?php echo {$m[1]}; ?>";
        }, $content);

        return $content;
    }
}
