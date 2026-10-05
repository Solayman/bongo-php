<?php

namespace System\View;

/**
 * Compiles Bongo templates to plain PHP.
 *
 *   {{ $x }}  escaped echo        {!! $x !!}  raw echo        {{-- note --}}  comment
 *   @{{ x }}  prints "{{ x }}" literally
 *   @if / @elseif / @else / @endif     @unless / @endunless     @isset / @endisset     @empty(x) / @endempty
 *   @foreach / @endforeach   @forelse ... @empty ... @endforelse   @for / @endfor   @while / @endwhile
 *   (inside @foreach/@forelse: $loop->index, iteration, first, last, odd, even, count, remaining, depth, parent)
 *   @break / @continue (optionally with a condition)
 *   @php ... @endphp   @php($x = 1)
 *   @csrf   @method('PUT')   @error('field') {{ $message }} @enderror   @auth / @auth('customer') / @endauth   @guest / @endguest
 *   @json($data)
 *   @extends('layout')   @section('name') ... @endsection   @section('title', $text) (escaped)   @yield('name', 'default')
 *   @include('partial', ['extra' => 1])   @push('scripts') ... @endpush   @stack('scripts')
 *
 * Add your own with ViewCompiler::directive('name', fn(?string $args) => '<?php ... ?>').
 */
class ViewCompiler
{
    /** Bump when the compiled output changes, so cached views are rebuilt */
    public const VERSION = '2.4';

    protected const DIRECTIVE = '/@(\w+)(?:[ \t]*(\((?:[^()\'"]++|\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|(?2))*\)))?/';

    /** Directives that may follow text directly ("Home@endsection"); others need a non-word char before @ */
    protected const CLOSING = ['else', 'elseif', 'elsecan', 'stop', 'show', 'break', 'continue', 'empty', 'case', 'default'];

    /** Directives without arguments that always compile to the same PHP */
    protected const FIXED = [
        'else' => '<?php else: ?>', 'csrf' => '<?php echo csrf_field(); ?>',
        'debugbar' => '<?php if (config("app.debug")) { echo \System\Debug\DebugBar::render(); } ?>',
        'endfor' => '<?php endfor; ?>', 'endwhile' => '<?php endwhile; ?>',
        'endsection' => '<?php $this->sections->end(); ?>', 'stop' => '<?php $this->sections->end(); ?>',
        'endpush' => '<?php $this->sections->endPush(); ?>',
        'endif' => '<?php endif; ?>', 'endunless' => '<?php endif; ?>', 'endisset' => '<?php endif; ?>',
        'endempty' => '<?php endif; ?>', 'endauth' => '<?php endif; ?>', 'endguest' => '<?php endif; ?>',
        'endcan' => '<?php endif; ?>', 'endcannot' => '<?php endif; ?>', 'enderror' => '<?php endif; ?>',
        'endonce' => '<?php endif; ?>', 'endenv' => '<?php endif; ?>', 'endproduction' => '<?php endif; ?>',
    ];

    /** @checked($x), @selected, @disabled, @readonly, @required: print the attribute when true */
    protected const ATTRIBUTES = ['checked', 'selected', 'disabled', 'readonly', 'required'];

    protected static array $custom = [];
    protected array $verbatim = [];
    protected int $forelse = 0;
    protected array $forelseStack = [];
    /** Open @switch blocks: true until their first @case/@default */
    protected array $switchStack = [];
    protected string $file = '';
    protected int $once = 0;

    /** Register a custom directive: @name(args) → the PHP your callback returns */
    public static function directive(string $name, callable $compiler): void
    {
        static::$custom[$name] = $compiler;
    }

    public function compile(string $content): string
    {
        $this->verbatim = [];
        $this->forelseStack = [];
        $this->switchStack = [];
        $this->once = 0;
        $this->file = substr(sha1($content), 0, 12);   // stable ids for @once

        // {{-- comments --}} disappear
        $content = preg_replace('/\{\{--.*?--\}\}/s', '', $content);

        // @{{ ... }} and @{!! ... !!} print literally (for JS frameworks)
        $content = preg_replace_callback('/@(\{\{.*?\}\}|\{!!.*?!!\})/s', fn($m) => $this->protect($m[1]), $content);

        // @@if prints "@if" literally
        $content = preg_replace_callback('/@@(\w+)/', fn($m) => $this->protect('@' . $m[1]), $content);

        // @php ... @endphp blocks are kept as they are
        $content = preg_replace_callback('/\B@php(?![ \t]*\()(.*?)@endphp/s', fn($m) => $this->protect('<?php' . $m[1] . '?>'), $content);

        $content = $this->compileComponents($content);

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

        // {{ escaped }}: null prints as an empty string; a component's $slot is already HTML
        $content = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', fn($m) => "<?php echo \\System\\View\\ViewEngine::escape(({$m[1]}) ?? null); ?>", $content);

        return $this->restore($content);
    }

    protected function compileDirective(array $m): string
    {
        $name = $m[1];
        $args = $m[2] !== null ? substr($m[2], 1, -1) : null;

        if (isset(static::$custom[$name])) {
            return (static::$custom[$name])($args);
        }

        if (isset(self::FIXED[$name])) {
            return self::FIXED[$name];
        }
        if (in_array($name, self::ATTRIBUTES, true)) {
            return "<?php if ($args) echo '$name'; ?>";
        }

        $method = 'compile' . ucfirst($name);
        if (!method_exists($this, $method)) {
            return $m[0]; // not a directive (e.g. an email address or CSS @media)
        }

        return $this->$method($args);
    }

    // --- Conditions ---------------------------------------------------

    protected function compileIf($a)
    {
        return "<?php if ($a): ?>";
    }
    protected function compileElseif($a)
    {
        return "<?php elseif ($a): ?>";
    }
    protected function compileUnless($a)
    {
        return "<?php if (!($a)): ?>";
    }
    protected function compileIsset($a)
    {
        return "<?php if (isset($a)): ?>";
    }
    /** @auth / @auth('customer'): signed in with the default guard or a named one */
    protected function compileAuth($a)
    {
        return '<?php if (auth(' . ($a ?? '') . ')): ?>';
    }
    protected function compileGuest($a)
    {
        return '<?php if (!auth(' . ($a ?? '') . ')): ?>';
    }

    /** @can('delete', $product) ... @elsecan('update', $product) ... @else ... @endcan · @cannot(...) ... @endcannot */
    protected function compileCan($a)
    {
        return "<?php if (can($a)): ?>";
    }
    protected function compileElsecan($a)
    {
        return "<?php elseif (can($a)): ?>";
    }
    protected function compileCannot($a)
    {
        return "<?php if (!can($a)): ?>";
    }

    /** @empty($x) is a condition; a bare @empty inside @forelse starts the "no items" part */
    protected function compileEmpty($a)
    {
        if ($a !== null) {
            return "<?php if (empty($a)): ?>";
        }
        if (!$this->forelseStack) {
            throw new ViewException('@empty without arguments must be inside @forelse');
        }
        $key = array_key_last($this->forelseStack);
        $this->forelseStack[$key][1] = true;
        return "<?php endforeach; \$loop = array_pop(\$__loops); if (\$__empty_{$this->forelseStack[$key][0]}): ?>";
    }

    // --- Loops --------------------------------------------------------

    /**
     * @foreach and @forelse give the body a $loop object:
     * index, iteration, first, last, odd, even, count, remaining, depth, parent
     */
    protected function openLoop(string $a, int $n): string
    {
        if (!preg_match('/^(.+?)\s+as\s+(.+)$/s', $a, $m)) {
            throw new ViewException("@foreach($a) must look like @foreach(\$items as \$item)");
        }
        return "\$__loops[] = \$loop ?? null; \$__items_$n = {$m[1]}; \$loop = new \\System\\View\\Loop(\$__items_$n, end(\$__loops) ?: null); "
            . "\$__empty_$n = true; foreach (\$__items_$n as {$m[2]}): \$__empty_$n = false; \$loop->next();";
    }

    protected function compileForeach($a)
    {
        return '<?php ' . $this->openLoop((string) $a, ++$this->forelse) . ' ?>';
    }
    protected function compileEndforeach()
    {
        return '<?php endforeach; $loop = array_pop($__loops); ?>';
    }
    protected function compileFor($a)
    {
        return "<?php for ($a): ?>";
    }
    protected function compileWhile($a)
    {
        return "<?php while ($a): ?>";
    }
    protected function compileBreak($a)
    {
        return $a === null ? '<?php break; ?>' : "<?php if ($a) break; ?>";
    }
    protected function compileContinue($a)
    {
        return $a === null ? '<?php continue; ?>' : "<?php if ($a) continue; ?>";
    }

    protected function compileForelse($a)
    {
        $n = ++$this->forelse;
        $this->forelseStack[] = [$n, false];
        return '<?php ' . $this->openLoop((string) $a, $n) . ' ?>';
    }

    protected function compileEndforelse()
    {
        if (!$this->forelseStack) {
            throw new ViewException('@endforelse without a matching @forelse');
        }
        [, $hadEmpty] = array_pop($this->forelseStack);
        return $hadEmpty ? '<?php endif; ?>' : '<?php endforeach; $loop = array_pop($__loops); ?>';
    }

    // --- Helpers ------------------------------------------------------

    protected function compilePhp($a)
    {
        return "<?php ($a); ?>";
    }
    protected function compileMethod($a)
    {
        return "<?php echo method_field($a); ?>";
    }
    protected function compileError($a)
    {
        return "<?php if (\$message = error($a)): ?>";
    }

    protected function compileJson($a)
    {
        return "<?php echo json_encode($a, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>";
    }

    // --- Components ---------------------------------------------------

    /**
     * <x-alert type="danger" :count="$n">Saved!</x-alert> → resources/views/components/alert.php
     * with $type, $count and $slot (the inner HTML). <x-badge text="New" /> has no slot.
     * <x-forms.input> → components/forms/input.php. Inner components are compiled first.
     */
    protected function compileComponents(string $content): string
    {
        $self = '/<x-([\w.-]+)((?:\s+:?[\w.-]+(?:="[^"]*")?)*)\s*\/>/';
        $content = preg_replace_callback($self, fn($m) => $this->componentOpen($m[1], $m[2]) . '<?php echo $this->endComponent(); ?>', $content);

        // Paired tags, innermost first (the body holds no further <x- tag of any name)
        $pair = '/<x-([\w.-]+)((?:\s+:?[\w.-]+(?:="[^"]*")?)*)\s*>((?:(?!<x-[\w.-]).)*?)<\/x-\1\s*>/s';
        do {
            $content = preg_replace_callback($pair, fn($m) => $this->componentOpen($m[1], $m[2]) . $m[3] . '<?php echo $this->endComponent(); ?>', $content, -1, $count);
        } while ($count > 0);

        if (preg_match('/<\/?x-([\w.-]+)/', $content, $m)) {
            throw new ViewException("<x-{$m[1]}> has no matching closing tag </x-{$m[1]}>");
        }
        return $content;
    }

    /** Attributes → PHP array: type="danger" is text, :count="$n" is PHP */
    protected function componentOpen(string $name, string $attributes): string
    {
        $pairs = [];
        preg_match_all('/(:?)([\w.-]+)(?:="([^"]*)")?/', $attributes, $matches, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
        foreach ($matches as $m) {
            [$isPhp, $key, $value] = [$m[1] === ':', $m[2], $m[3] ?? null];
            // <x-input disabled /> → true · type="danger" → 'danger' · :count="$n" → $n
            $code = $value === null ? 'true' : ($isPhp ? $value : var_export($value, true));
            $pairs[] = var_export($key, true) . ' => ' . $code;
        }
        return "<?php \$this->startComponent(" . var_export($name, true) . ', [' . implode(', ', $pairs) . ']); ?>';
    }

    // --- @switch ------------------------------------------------------

    /**
     * PHP allows nothing (not even a newline) between "switch (): ?>" and the first case,
     * so @switch leaves PHP open and the first @case/@default closes it.
     */
    protected function compileSwitch($a)
    {
        $this->switchStack[] = true;
        return "<?php switch ($a):";
    }

    protected function compileCase($a)
    {
        return $this->switchArm("case ($a):");
    }

    protected function compileDefault()
    {
        return $this->switchArm('default:');
    }

    protected function switchArm(string $arm): string
    {
        if (!$this->switchStack) {
            throw new ViewException('@case/@default must be inside @switch');
        }
        $first = end($this->switchStack);
        $this->switchStack[array_key_last($this->switchStack)] = false;
        return $first ? " $arm ?>" : "<?php $arm ?>";
    }

    protected function compileEndswitch()
    {
        if (!$this->switchStack) {
            throw new ViewException('@endswitch without a matching @switch');
        }
        $first = array_pop($this->switchStack);
        return $first ? ' endswitch; ?>' : '<?php endswitch; ?>';
    }

    // --- Form attributes ----------------------------------------------


    /** @class(['btn', 'btn-primary' => $active, 'd-none' => !$show]) → class="btn btn-primary" */
    protected function compileClass($a)
    {
        return "<?php echo 'class=\"' . htmlspecialchars(\\System\\View\\ViewEngine::classList($a), ENT_QUOTES, 'UTF-8') . '\"'; ?>";
    }

    // --- Conditional includes -----------------------------------------

    /** @includeIf('partials.promo', [...]): only when the view exists */
    protected function compileIncludeIf($a)
    {
        $parts = $this->splitArguments((string) $a);
        return "<?php if (\$this->exists({$parts[0]})) echo \$this->includeView(get_defined_vars(), $a); ?>";
    }

    /** @includeWhen($user->isAdmin(), 'partials.admin-bar', [...]) */
    protected function compileIncludeWhen($a, bool $negate = false)
    {
        $parts = $this->splitArguments((string) $a);
        $condition = array_shift($parts);
        $condition = $negate ? "!($condition)" : $condition;
        return "<?php if ($condition) echo \$this->includeView(get_defined_vars(), " . implode(', ', $parts) . '); ?>';
    }

    protected function compileIncludeUnless($a)
    {
        return $this->compileIncludeWhen($a, true);
    }

    /** @includeFirst(['shop.custom-banner', 'partials.banner'], [...]): the first view that exists */
    protected function compileIncludeFirst($a)
    {
        $parts = $this->splitArguments((string) $a);
        $views = array_shift($parts);
        $data = $parts ? ', ' . implode(', ', $parts) : '';
        return "<?php echo \$this->includeView(get_defined_vars(), \$this->firstExisting($views)$data); ?>";
    }

    /** @each('partials.product-card', $products, 'product', 'partials.no-products'): one view per item */
    protected function compileEach($a)
    {
        return "<?php echo \$this->renderEach($a); ?>";
    }

    // --- Sections, once, environment, debugging -----------------------

    protected function compileHasSection($a)
    {
        return "<?php if (\$this->sections->has($a)): ?>";
    }
    protected function compileSectionMissing($a)
    {
        return "<?php if (!\$this->sections->has($a)): ?>";
    }

    /** @once ... @endonce: printed the first time on a page only (e.g. a script a partial needs) */
    protected function compileOnce()
    {
        $id = $this->file . ':' . ++$this->once;
        return "<?php if (\$this->sections->once('$id')): ?>";
    }


    /** @env('local') / @env(['local', 'testing']) ... @endenv · @production ... @endproduction */
    protected function compileEnv($a)
    {
        return "<?php if (in_array(config('app.env'), (array) ($a), true)): ?>";
    }
    protected function compileProduction()
    {
        return "<?php if (config('app.env') === 'production'): ?>";
    }

    protected function compileDump($a)
    {
        return "<?php dump($a); ?>";
    }

    // --- Layouts, sections, includes, stacks ---------------------------

    protected function compileExtends($a)
    {
        return "<?php \$this->extend($a); ?>";
    }
    /** @section('content') ... @endsection captures HTML; @section('title', $text) sets plain text (escaped) */
    protected function compileSection($a)
    {
        $parts = $this->splitArguments((string) $a);
        return count($parts) > 1 ? $this->compileSet($a) : "<?php \$this->sections->start($a); ?>";
    }


    /** @set('title', $product->name): the value is text, so it is escaped (a product name cannot inject HTML) */
    protected function compileSet($a)
    {
        $parts = $this->splitArguments((string) $a);
        if (count($parts) < 2) {
            throw new ViewException("@set($a) needs a name and a value: @set('title', 'Products')");
        }
        $value = implode(',', array_slice($parts, 1));
        return "<?php \$this->sections->set({$parts[0]}, htmlspecialchars((string) (($value) ?? ''), ENT_QUOTES, 'UTF-8')); ?>";
    }

    /** Split "a, f(b, c), 'd,e'" on top-level commas → ["a", "f(b, c)", "'d,e'"] */
    protected function splitArguments(string $args): array
    {
        $parts = [];
        $depth = 0;
        $quote = null;
        $current = '';
        for ($i = 0, $len = strlen($args); $i < $len; $i++) {
            $c = $args[$i];
            if ($quote !== null) {
                if ($c === '\\') {
                    $current .= $c . ($args[++$i] ?? '');
                    continue;
                }
                if ($c === $quote) {
                    $quote = null;
                }
            } elseif ($c === '"' || $c === "'") {
                $quote = $c;
            } elseif (strpbrk($c, '([{') !== false) {
                $depth++;
            } elseif (strpbrk($c, ')]}') !== false) {
                $depth--;
            } elseif ($c === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $c;
        }
        $parts[] = trim($current);
        return $parts;
    }
    protected function compileYield($a)
    {
        return "<?php echo \$this->sections->get($a); ?>";
    }
    protected function compileInclude($a)
    {
        return "<?php echo \$this->includeView(get_defined_vars(), $a); ?>";
    }
    protected function compilePush($a)
    {
        return "<?php \$this->sections->startPush($a); ?>";
    }
    protected function compileStack($a)
    {
        return "<?php echo \$this->sections->stack($a); ?>";
    }

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
