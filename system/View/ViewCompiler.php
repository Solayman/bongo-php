<?php
namespace System\View;

/**
 * Compiles BongoMVC templates to plain PHP.
 *
 * Output
 *   {{ $x }}  escaped          {{! $x }}  raw HTML          {-- note --}  comment (removed)
 *   \{{ x }}  prints "{{ x }}"  \{: x :}  prints "{: x :}"   {: verbatim :} ... {: end :}  left untouched (Vue, Alpine)
 *
 * Tags: {: name arguments :}. Blocks are closed with {: end :}.
 *   {: if $a :} ... {: elseif $b :} ... {: else :} ... {: end :}
 *   {: unless $a :}   {: isset $a :}   {: empty $a :}   {: auth :} / {: auth 'customer' :}   {: guest :}
 *   {: each $items as $key => $item :} ... {: else :} no items ... {: end :}      ($loop->first, ->last, ->index ...)
 *   {: for $i = 0; $i < 3; $i++ :}   {: while $x :}   {: break :}  {: continue $item->hidden :}
 *   {: layout 'layouts.admin' :}   {: block 'content' :} ... {: end :}   {: block 'title', 'Products' :}
 *   {: show 'content' :}  {: show 'title', 'Default' :}   {: insert 'partials.note', ['x' => 1] :}
 *   {: push 'scripts' :} ... {: end :}   {: stack 'scripts' :}
 *   {: csrf :}   {: method 'PUT' :}   {: error 'email' :} {{ $message }} {: end :}   {: json $data :}
 *   {: php $total = 0 :}   {: php :} several; statements; {: end :}
 *
 * Add your own with ViewCompiler::tag('money', fn(?string $args) => "<?php echo money($args); ?>").
 */
class ViewCompiler
{
    /** Bump when the compiled output changes, so cached views are rebuilt */
    public const VERSION = '3.0';

    /** {: name args :}; quoted strings may contain ":}", and "::" / "?:" are fine inside the arguments */
    protected const TAG = '/\{:\s*(\w+)((?:[^\'":]++|\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|:(?!\}))*):\}/s';

    /** Tags that open a block closed by {: end :} */
    protected const BLOCKS = ['if', 'unless', 'isset', 'empty', 'auth', 'guest', 'error', 'each', 'for', 'while', 'block', 'push'];

    protected static array $custom = [];
    protected array $verbatim = [];
    /** Open blocks: [tag, line, number, hasElse] */
    protected array $open = [];
    protected int $counter = 0;
    protected string $source = '';

    /** Register a custom tag: {: name args :} → the PHP your callback returns */
    public static function tag(string $name, callable $compiler): void
    {
        static::$custom[$name] = $compiler;
    }

    public function compile(string $content): string
    {
        $this->verbatim = [];
        $this->open = [];
        $this->counter = 0;

        // Comments disappear, but keep their line breaks so error line numbers stay right
        $content = preg_replace_callback('/\{--.*?--\}/s', fn($m) => str_repeat("\n", substr_count($m[0], "\n")), $content);

        // Raw regions first, so nothing inside them is treated as a tag
        $content = preg_replace_callback('/\{:\s*verbatim\s*:\}(.*?)\{:\s*end\s*:\}/s', fn($m) => $this->protect($m[1]), $content);
        $content = preg_replace_callback('/\{:\s*php\s*:\}(.*?)\{:\s*end\s*:\}/s', fn($m) => $this->protect('<?php' . $m[1] . '?>'), $content);

        // \{{ and \{: print the characters themselves
        $content = preg_replace_callback('/\\\\(\{\{|\{:)/', fn($m) => $this->protect($m[1]), $content);

        $this->source = $content;
        $content = preg_replace_callback(self::TAG, function ($m) {
            $line = substr_count($this->source, "\n", 0, $m[0][1]) + 1;
            $args = trim($m[2][0]);
            return $this->compileTag($m[1][0], $args === '' ? null : $args, $line);
        }, $content, -1, $count, PREG_OFFSET_CAPTURE);

        if ($this->open) {
            [$tag, $line] = end($this->open);
            throw new ViewException("Unclosed {: $tag :} opened on line $line: add {: end :}");
        }

        // {{! raw }} then {{ escaped }} (null prints as an empty string)
        $content = preg_replace_callback('/\{\{!\s*(.+?)\s*\}\}/s', fn($m) => "<?php echo {$m[1]}; ?>", $content);
        $content = preg_replace_callback('/\{\{\s*(.+?)\s*\}\}/s', fn($m) => "<?php echo htmlspecialchars((string) (({$m[1]}) ?? ''), ENT_QUOTES, 'UTF-8'); ?>", $content);

        return $this->restore($content);
    }

    protected function compileTag(string $name, ?string $args, int $line): string
    {
        if (isset(static::$custom[$name])) {
            return (static::$custom[$name])($args);
        }

        $method = 'compile' . ucfirst($name);
        if (!method_exists($this, $method) || in_array($name, ['tag', 'string'], true)) {
            throw new ViewException("Unknown template tag {: $name :} on line $line");
        }

        if (in_array($name, self::BLOCKS, true) && !($name === 'block' && count($this->splitArguments((string) $args)) > 1)) {
            $this->open[] = [$name, $line, ++$this->counter, false];
        }
        return $this->$method($args, $line);
    }

    protected function requireArgs(?string $args, string $tag, int $line): string
    {
        if ($args === null) {
            throw new ViewException("{: $tag :} on line $line needs an argument");
        }
        return $args;
    }

    // --- Conditions -----------------------------------------------------

    protected function compileIf($a, $l)     { return '<?php if (' . $this->requireArgs($a, 'if', $l) . '): ?>'; }
    protected function compileUnless($a, $l) { return '<?php if (!(' . $this->requireArgs($a, 'unless', $l) . ')): ?>'; }
    protected function compileIsset($a, $l)  { return '<?php if (isset(' . $this->requireArgs($a, 'isset', $l) . ')): ?>'; }
    protected function compileEmpty($a, $l)  { return '<?php if (empty(' . $this->requireArgs($a, 'empty', $l) . ')): ?>'; }
    protected function compileAuth($a)       { return '<?php if (auth(' . ($a ?? '') . ')): ?>'; }
    protected function compileGuest($a)      { return '<?php if (!auth(' . ($a ?? '') . ')): ?>'; }
    protected function compileError($a, $l)  { return '<?php if ($message = error(' . $this->requireArgs($a, 'error', $l) . ')): ?>'; }

    protected function compileElseif($a, $l)
    {
        $block = $this->current('elseif', $l);
        if (!in_array($block[0], ['if', 'unless'], true) || $block[3]) {
            throw new ViewException("{: elseif :} on line $l must be inside {: if :}, before {: else :}");
        }
        return '<?php elseif (' . $this->requireArgs($a, 'elseif', $l) . '): ?>';
    }

    /** {: else :} in a condition, or the "no items" part of {: each :} */
    protected function compileElse($a, $l)
    {
        $block = $this->current('else', $l);
        if ($block[3]) {
            throw new ViewException("Second {: else :} on line $l for the {: {$block[0]} :} opened on line {$block[1]}");
        }
        if (in_array($block[0], ['for', 'while', 'block', 'push'], true)) {
            throw new ViewException("{: else :} on line $l cannot be used inside {: {$block[0]} :}");
        }
        $this->open[array_key_last($this->open)][3] = true;

        if ($block[0] === 'each') {
            return "<?php endforeach; \$loop = array_pop(\$__loops); if (\$__empty_{$block[2]}): ?>";
        }
        return '<?php else: ?>';
    }

    // --- Loops ----------------------------------------------------------

    /** {: each $items as $item :} with a $loop object (index, iteration, first, last, odd, even, count, depth, parent) */
    protected function compileEach($a, $l)
    {
        $a = $this->requireArgs($a, 'each', $l);
        if (!preg_match('/^(.+?)\s+as\s+(.+)$/s', $a, $m)) {
            throw new ViewException("{: each :} on line $l must look like {: each \$items as \$item :}");
        }
        $n = $this->counter;
        return "<?php \$__loops[] = \$loop ?? null; \$__items_$n = {$m[1]}; \$loop = new \\System\\View\\Loop(\$__items_$n, end(\$__loops) ?: null); "
            . "\$__empty_$n = true; foreach (\$__items_$n as {$m[2]}): \$__empty_$n = false; \$loop->next(); ?>";
    }

    protected function compileFor($a, $l)   { return '<?php for (' . $this->requireArgs($a, 'for', $l) . '): ?>'; }
    protected function compileWhile($a, $l) { return '<?php while (' . $this->requireArgs($a, 'while', $l) . '): ?>'; }
    protected function compileBreak($a)     { return $a === null ? '<?php break; ?>' : "<?php if ($a) break; ?>"; }
    protected function compileContinue($a)  { return $a === null ? '<?php continue; ?>' : "<?php if ($a) continue; ?>"; }

    // --- Closing --------------------------------------------------------

    protected function compileEnd($a, $l)
    {
        [$tag, , , $hasElse] = $this->current('end', $l);
        array_pop($this->open);

        return match ($tag) {
            'each'  => $hasElse ? '<?php endif; ?>' : '<?php endforeach; $loop = array_pop($__loops); ?>',
            'for'   => '<?php endfor; ?>',
            'while' => '<?php endwhile; ?>',
            'block' => '<?php $this->sections->end(); ?>',
            'push'  => '<?php $this->sections->endPush(); ?>',
            default => '<?php endif; ?>',
        };
    }

    /** The innermost open block, or an error naming the stray tag */
    protected function current(string $tag, int $line): array
    {
        if (!$this->open) {
            throw new ViewException("{: $tag :} on line $line has no open block to belong to");
        }
        return end($this->open);
    }

    // --- Layouts, blocks, inserts, stacks --------------------------------

    protected function compileLayout($a, $l) { return '<?php $this->extend(' . $this->requireArgs($a, 'layout', $l) . '); ?>'; }
    protected function compileShow($a, $l)   { return '<?php echo $this->sections->get(' . $this->requireArgs($a, 'show', $l) . '); ?>'; }
    protected function compileInsert($a, $l) { return '<?php echo $this->includeView(get_defined_vars(), ' . $this->requireArgs($a, 'insert', $l) . '); ?>'; }
    protected function compilePush($a, $l)   { return '<?php $this->sections->startPush(' . $this->requireArgs($a, 'push', $l) . '); ?>'; }
    protected function compileStack($a, $l)  { return '<?php echo $this->sections->stack(' . $this->requireArgs($a, 'stack', $l) . '); ?>'; }

    /** {: block 'name' :} ... {: end :} captures; {: block 'title', $text :} sets plain text (escaped) */
    protected function compileBlock($a, $l)
    {
        $parts = $this->splitArguments($this->requireArgs($a, 'block', $l));
        if (count($parts) === 1) {
            return "<?php \$this->sections->start({$parts[0]}); ?>";
        }
        $value = implode(',', array_slice($parts, 1));
        return "<?php \$this->sections->set({$parts[0]}, htmlspecialchars((string) (($value) ?? ''), ENT_QUOTES, 'UTF-8')); ?>";
    }

    // --- Helpers --------------------------------------------------------

    protected function compileCsrf()          { return '<?php echo csrf_field(); ?>'; }
    protected function compileMethod($a, $l)  { return '<?php echo method_field(' . $this->requireArgs($a, 'method', $l) . '); ?>'; }
    protected function compilePhp($a, $l)     { return '<?php ' . $this->requireArgs($a, 'php', $l) . '; ?>'; }

    protected function compileJson($a, $l)
    {
        return '<?php echo json_encode(' . $this->requireArgs($a, 'json', $l) . ', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>';
    }

    // --------------------------------------------------------------------

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
                if ($c === '\\') { $current .= $c . ($args[++$i] ?? ''); continue; }
                if ($c === $quote) $quote = null;
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

    /** Hide text from later passes; the placeholder keeps the line breaks for line numbers */
    protected function protect(string $text): string
    {
        $this->verbatim[] = $text;
        return "\x00BV" . (count($this->verbatim) - 1) . str_repeat("\n", substr_count($text, "\n")) . "\x00";
    }

    protected function restore(string $content): string
    {
        return preg_replace_callback("/\x00BV(\d+)\n*\x00/", fn($m) => $this->verbatim[(int) $m[1]], $content);
    }
}
