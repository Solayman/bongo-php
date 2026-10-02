<?php
namespace System\View;

/**
 * Compiles BongoMVC templates to plain PHP.
 *
 *   {{ $x }}  escaped echo        {!! $x !!}  raw echo        {{-- note --}}  comment
 *   @{{ x }}  prints "{{ x }}" literally
 *   @if / @elseif / @else / @endif     @unless / @endunless     @isset / @endisset     @empty(x) / @endempty
 *   @foreach / @endforeach   @forelse ... @empty ... @endforelse   @for / @endfor   @while / @endwhile
 *   @break / @continue (optionally with a condition)
 *   @php ... @endphp   @php($x = 1)
 *   @csrf   @method('PUT')   @error('field') {{ $message }} @enderror   @auth / @endauth   @guest / @endguest
 *   @json($data)
 *   @extends('layout')   @section('name') ... @endsection   @section('title', 'Text')   @yield('name', 'default')
 *   @include('partial', ['extra' => 1])   @push('scripts') ... @endpush   @stack('scripts')
 *
 * Add your own with ViewCompiler::directive('name', fn(?string $args) => '<?php ... ?>').
 */
class ViewCompiler
{
    /** Bump when the compiled output changes, so cached views are rebuilt */
    public const VERSION = '2.0';

    protected const DIRECTIVE = '/@(\w+)(?:[ \t]*(\((?:[^()\'"]++|\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|(?2))*\)))?/';

    /** Directives that may follow text directly ("Home@endsection"); others need a non-word char before @ */
    protected const CLOSING = ['else', 'stop', 'show', 'break', 'continue', 'empty'];

    protected static array $custom = [];
    protected array $verbatim = [];
    protected int $forelse = 0;
    protected array $forelseStack = [];

    /** Register a custom directive: @name(args) → the PHP your callback returns */
    public static function directive(string $name, callable $compiler): void
    {
        static::$custom[$name] = $compiler;
    }

    public function compile(string $content): string
    {
        $this->verbatim = [];
        $this->forelseStack = [];

        // {{-- comments --}} disappear
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);

        // @{{ ... }} and @{!! ... !!} print literally (for JS frameworks)
        $content = preg_replace_callback('/@(\{\{.*?\}\}|\{!!.*?!!\})/s', fn($m) => $this->protect($m[1]), $content);

        // @@if prints "@if" literally
        $content = preg_replace_callback('/@@(\w+)/', fn($m) => $this->protect('@' . $m[1]), $content);

        // @php ... @endphp blocks are kept as they are
        $content = preg_replace_callback('/\B@php(?![ \t]*\()(.*?)@endphp/s', fn($m) => $this->protect('<?php' . $m[1] . '?>'), $content);

        $content = preg_replace_callback(self::DIRECTIVE, function ($m) use (&$content) {
            [$whole, $offset] = $m[0];
            $name = $m[1][0];
            // After a letter or digit only closing directives count, so "user@example.com" stays text
            $before = $offset > 0 ? $content[$offset - 1] : ' ';
            if (preg_match('/\w/', $before) && !str_starts_with($name, 'end') && !in_array($name, self::CLOSING, true)) {
                return $whole;
            }
            return $this->compileDirective([$whole, $name, isset($m[2]) && $m[2][1] >= 0 ? $m[2][0] : null]);
        }, $content, -1, $count, PREG_OFFSET_CAPTURE);

        // {!! raw !!}
        $content = preg_replace_callback('/\{!!\s*(.+?)\s*!!\}/s', fn($m) => "<?php echo {$m[1]}; ?>", $content);

        // {{ escaped }}: null prints as an empty string
        $content = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', fn($m) => "<?php echo htmlspecialchars((string) (({$m[1]}) ?? ''), ENT_QUOTES, 'UTF-8'); ?>", $content);

        return $this->restore($content);
    }

    protected function compileDirective(array $m): string
    {
        $name = $m[1];
        $args = $m[2] !== null ? substr($m[2], 1, -1) : null;

        if (isset(static::$custom[$name])) {
            return (static::$custom[$name])($args);
        }

        $method = 'compile' . ucfirst($name);
        if (!method_exists($this, $method)) {
            return $m[0]; // not a directive (e.g. an email address or CSS @media)
        }

        return $this->$method($args);
    }

    // --- Conditions ---------------------------------------------------

    protected function compileIf($a)       { return "<?php if ($a): ?>"; }
    protected function compileElseif($a)   { return "<?php elseif ($a): ?>"; }
    protected function compileElse()       { return '<?php else: ?>'; }
    protected function compileEndif()      { return '<?php endif; ?>'; }
    protected function compileUnless($a)   { return "<?php if (!($a)): ?>"; }
    protected function compileEndunless()  { return '<?php endif; ?>'; }
    protected function compileIsset($a)    { return "<?php if (isset($a)): ?>"; }
    protected function compileEndisset()   { return '<?php endif; ?>'; }
    protected function compileEndempty()   { return '<?php endif; ?>'; }
    protected function compileAuth()       { return '<?php if (auth()): ?>'; }
    protected function compileEndauth()    { return '<?php endif; ?>'; }
    protected function compileGuest()      { return '<?php if (!auth()): ?>'; }
    protected function compileEndguest()   { return '<?php endif; ?>'; }

    /** @empty($x) is a condition; a bare @empty inside @forelse starts the "no items" part */
    protected function compileEmpty($a)
    {
        if ($a !== null) {
            return "<?php if (empty($a)): ?>";
        }
        if (!$this->forelseStack) {
            throw new ViewException('@empty without arguments must be inside @forelse');
        }
        $n = end($this->forelseStack);
        return "<?php endforeach; if (\$__empty_$n): ?>";
    }

    // --- Loops --------------------------------------------------------

    protected function compileForeach($a)    { return "<?php foreach ($a): ?>"; }
    protected function compileEndforeach()   { return '<?php endforeach; ?>'; }
    protected function compileFor($a)        { return "<?php for ($a): ?>"; }
    protected function compileEndfor()       { return '<?php endfor; ?>'; }
    protected function compileWhile($a)      { return "<?php while ($a): ?>"; }
    protected function compileEndwhile()     { return '<?php endwhile; ?>'; }
    protected function compileBreak($a)      { return $a === null ? '<?php break; ?>' : "<?php if ($a) break; ?>"; }
    protected function compileContinue($a)   { return $a === null ? '<?php continue; ?>' : "<?php if ($a) continue; ?>"; }

    protected function compileForelse($a)
    {
        $n = ++$this->forelse;
        $this->forelseStack[] = $n;
        return "<?php \$__empty_$n = true; foreach ($a): \$__empty_$n = false; ?>";
    }

    protected function compileEndforelse()
    {
        array_pop($this->forelseStack);
        return '<?php endif; ?>';
    }

    // --- Helpers ------------------------------------------------------

    protected function compilePhp($a)        { return "<?php ($a); ?>"; }
    protected function compileCsrf()         { return '<?php echo csrf_field(); ?>'; }
    protected function compileMethod($a)     { return "<?php echo method_field($a); ?>"; }
    protected function compileError($a)      { return "<?php if (\$message = error($a)): ?>"; }
    protected function compileEnderror()     { return '<?php endif; ?>'; }

    protected function compileJson($a)
    {
        return "<?php echo json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>";
    }

    // --- Layouts, sections, includes, stacks ---------------------------

    protected function compileExtends($a)    { return "<?php \$this->extend($a); ?>"; }
    protected function compileSection($a)    { return "<?php \$this->sections->start($a); ?>"; }
    protected function compileEndsection()   { return '<?php $this->sections->end(); ?>'; }
    protected function compileStop()         { return '<?php $this->sections->end(); ?>'; }
    protected function compileSet($a)        { return "<?php \$this->sections->set($a); ?>"; }
    protected function compileYield($a)      { return "<?php echo \$this->sections->get($a); ?>"; }
    protected function compileInclude($a)    { return "<?php echo \$this->includeView(get_defined_vars(), $a); ?>"; }
    protected function compilePush($a)       { return "<?php \$this->sections->startPush($a); ?>"; }
    protected function compileEndpush()      { return '<?php $this->sections->endPush(); ?>'; }
    protected function compileStack($a)      { return "<?php echo \$this->sections->stack($a); ?>"; }

    // ------------------------------------------------------------------

    protected function protect(string $text): string
    {
        $this->verbatim[] = $text;
        return "\x00BONGO_VERBATIM_" . (count($this->verbatim) - 1) . "\x00";
    }

    protected function restore(string $content): string
    {
        return preg_replace_callback("/\x00BONGO_VERBATIM_(\d+)\x00/", fn($m) => $this->verbatim[(int) $m[1]], $content);
    }
}
