<?php
namespace System\Debug;

/**
 * Turns any PHP value into readable output for dump() / dd():
 * HTML in the browser, plain text in the terminal, JSON for API requests.
 */
class Dumper
{
    public function __construct(
        protected int $maxDepth = 8,
        protected int $maxItems = 200,
        protected int $maxString = 2000
    ) {}

    /** Which format suits the current request */
    public static function format(): string
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            return 'text';
        }
        return class_exists(\System\Http\Request::class) && \System\Http\Request::wantsJson() ? 'json' : 'html';
    }

    // ------------------------------------------------------------------
    // Plain text
    // ------------------------------------------------------------------

    public function text($value): string
    {
        return $this->textValue($value, 0, []) . "\n";
    }

    protected function textValue($value, int $depth, array $seen): string
    {
        $pad = str_repeat('  ', $depth);

        switch (true) {
            case $value === null:
                return 'null';
            case is_bool($value):
                return $value ? 'true' : 'false';
            case is_int($value) || is_float($value):
                return var_export($value, true);
            case is_string($value):
                return '"' . $this->cut($value) . '" (' . mb_strlen($value) . ')';
            case is_resource($value):
                return 'resource(' . get_resource_type($value) . ')';
            case is_array($value):
                if ($value === []) return '[]';
                if ($depth >= $this->maxDepth) return 'array(' . count($value) . ') [...]';
                $lines = [];
                $i = 0;
                foreach ($value as $k => $v) {
                    if (++$i > $this->maxItems) { $lines[] = "$pad  ... " . (count($value) - $this->maxItems) . ' more'; break; }
                    $key = is_int($k) ? $k : '"' . $k . '"';
                    $lines[] = "$pad  $key => " . $this->textValue($v, $depth + 1, $seen);
                }
                return 'array(' . count($value) . ") [\n" . implode("\n", $lines) . "\n$pad]";
            case is_object($value):
                $id = spl_object_id($value);
                $class = get_class($value);
                if (isset($seen[$id])) return "$class #$id {*recursion*}";
                if ($depth >= $this->maxDepth) return "$class #$id {...}";
                $seen[$id] = true;
                $lines = [];
                foreach ($this->properties($value) as [$name, $visibility, $v]) {
                    $prefix = $visibility === 'public' ? '' : ($visibility === 'protected' ? '#' : '-');
                    $lines[] = "$pad  $prefix$name: " . $this->textValue($v, $depth + 1, $seen);
                }
                return "$class #$id {" . ($lines ? "\n" . implode("\n", $lines) . "\n$pad" : '') . '}';
        }

        return (string) $value;
    }

    // ------------------------------------------------------------------
    // HTML
    // ------------------------------------------------------------------

    public function html($value): string
    {
        return '<pre class="bongo-dump">' . $this->htmlValue($value, 0, []) . '</pre>';
    }

    protected function htmlValue($value, int $depth, array $seen): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        switch (true) {
            case $value === null:
                return '<span class="bd-null">null</span>';
            case is_bool($value):
                return '<span class="bd-bool">' . ($value ? 'true' : 'false') . '</span>';
            case is_int($value) || is_float($value):
                return '<span class="bd-num">' . $e(var_export($value, true)) . '</span>';
            case is_string($value):
                return '<span class="bd-str">"' . $e($this->cut($value)) . '"</span><span class="bd-meta"> (' . mb_strlen($value) . ')</span>';
            case is_resource($value):
                return '<span class="bd-meta">resource(' . $e(get_resource_type($value)) . ')</span>';
            case is_array($value):
                if ($value === []) return '<span class="bd-meta">[]</span>';
                if ($depth >= $this->maxDepth) return '<span class="bd-meta">array(' . count($value) . ') [...]</span>';
                $rows = '';
                $i = 0;
                foreach ($value as $k => $v) {
                    if (++$i > $this->maxItems) { $rows .= '<div class="bd-meta">... ' . (count($value) - $this->maxItems) . ' more</div>'; break; }
                    $key = is_int($k) ? '<span class="bd-num">' . $k . '</span>' : '<span class="bd-key">"' . $e($k) . '"</span>';
                    $rows .= "<div>$key => " . $this->htmlValue($v, $depth + 1, $seen) . '</div>';
                }
                return '<details' . ($depth < 2 ? ' open' : '') . '><summary><span class="bd-type">array</span><span class="bd-meta">(' . count($value) . ')</span></summary><div class="bd-children">' . $rows . '</div></details>';
            case is_object($value):
                $id = spl_object_id($value);
                $class = $e(get_class($value));
                if (isset($seen[$id])) return "<span class=\"bd-type\">$class</span> <span class=\"bd-meta\">#$id *recursion*</span>";
                if ($depth >= $this->maxDepth) return "<span class=\"bd-type\">$class</span> <span class=\"bd-meta\">#$id {...}</span>";
                $seen[$id] = true;
                $rows = '';
                foreach ($this->properties($value) as [$name, $visibility, $v]) {
                    $badge = $visibility === 'public' ? '' : '<span class="bd-vis">' . $visibility . '</span> ';
                    $rows .= '<div>' . $badge . '<span class="bd-key">' . $e($name) . '</span>: ' . $this->htmlValue($v, $depth + 1, $seen) . '</div>';
                }
                return '<details' . ($depth < 2 ? ' open' : '') . "><summary><span class=\"bd-type\">$class</span> <span class=\"bd-meta\">#$id</span></summary><div class=\"bd-children\">$rows</div></details>";
        }

        return $e((string) $value);
    }

    /** Styles for the HTML dump, printed once per request */
    public static function styles(): string
    {
        static $printed = false;
        if ($printed) return '';
        $printed = true;
        return '<style>
.bongo-dump{font:13px/1.5 ui-monospace,Consolas,monospace;background:#1e1f2b;color:#e6e6e6;padding:12px 16px;border-radius:8px;margin:8px 0;overflow:auto;text-align:left;white-space:pre-wrap}
.bongo-dump details{display:inline}.bongo-dump summary{cursor:pointer;display:inline;list-style:none}
.bongo-dump summary::-webkit-details-marker{display:none}.bongo-dump summary::before{content:"\25B8 ";color:#888}
.bongo-dump details[open]>summary::before{content:"\25BE "}
.bongo-dump .bd-children{display:block;margin-left:18px;border-left:1px dashed #444;padding-left:10px}
.bd-str{color:#a6e3a1}.bd-num{color:#fab387}.bd-bool{color:#f38ba8}.bd-null{color:#9399b2;font-style:italic}
.bd-key{color:#89b4fa}.bd-type{color:#f9e2af;font-weight:600}.bd-meta{color:#7f849c}.bd-vis{color:#cba6f7;font-size:11px}
.bongo-dump-src{font:12px ui-monospace,Consolas,monospace;color:#7f849c;margin:12px 0 0}
</style>';
    }

    // ------------------------------------------------------------------
    // JSON
    // ------------------------------------------------------------------

    /** A JSON-safe copy of the value (objects become class + properties) */
    public function plain($value, int $depth = 0, array $seen = [])
    {
        if (is_array($value)) {
            if ($depth >= $this->maxDepth) return '[...]';
            $out = [];
            foreach ($value as $k => $v) $out[$k] = $this->plain($v, $depth + 1, $seen);
            return $out;
        }
        if (is_object($value)) {
            $id = spl_object_id($value);
            if (isset($seen[$id]) || $depth >= $this->maxDepth) return get_class($value) . ' {...}';
            $seen[$id] = true;
            $out = ['@class' => get_class($value)];
            foreach ($this->properties($value) as [$name, , $v]) $out[$name] = $this->plain($v, $depth + 1, $seen);
            return $out;
        }
        if (is_resource($value)) return 'resource(' . get_resource_type($value) . ')';
        if (is_string($value)) return $this->cut($value);
        return $value;
    }

    // ------------------------------------------------------------------

    /** [name, visibility, value] for every property, including private and protected */
    protected function properties(object $object): array
    {
        if ($object instanceof \Closure) {
            return [['closure', 'public', '(closure)']];
        }

        // An object can choose what a dump shows (e.g. Request: its method, URL and input)
        if (method_exists($object, '__debugInfo')) {
            $info = $object->__debugInfo();
            if (is_array($info)) {
                return array_map(fn($key, $value) => [(string) $key, 'public', $value], array_keys($info), $info);
            }
        }

        $props = [];
        foreach ((array) $object as $key => $value) {
            if (str_starts_with($key, "\0*\0")) {
                $props[] = [substr($key, 3), 'protected', $value];
            } elseif (str_starts_with($key, "\0")) {
                $props[] = [substr($key, strpos($key, "\0", 1) + 1), 'private', $value];
            } else {
                $props[] = [$key, 'public', $value];
            }
        }
        return $props;
    }

    protected function cut(string $value): string
    {
        return mb_strlen($value) > $this->maxString ? mb_substr($value, 0, $this->maxString) . '…' : $value;
    }

    /** "app/Http/Controllers/X.php:12" for the first frame outside the dump helpers */
    public static function caller(): string
    {
        return self::context()['at'];
    }

    /**
     * Where dump()/dd() was called, for the header:
     *   at      "app/Http/Controllers/CategoryController.php:37"
     *   in      'CategoryController::update($id = "4")' (null at the top level of a file)
     *   class   "App\Http\Controllers\CategoryController" (null outside a class)
     *   request "PUT /categories/4" (null on the command line)
     */
    public static function context(): array
    {
        $trace = debug_backtrace(0, 40);
        $context = ['at' => 'unknown', 'in' => null, 'class' => null, 'request' => self::request()];

        foreach ($trace as $i => $frame) {
            if (!isset($frame['file'])) continue;
            $file = str_replace('\\', '/', $frame['file']);
            if (str_ends_with($file, 'system/Debug/Dumper.php') || str_ends_with($file, 'system/Support/helpers.php')) continue;

            $base = defined('BASE_PATH') ? str_replace('\\', '/', BASE_PATH) . '/' : '';
            $context['at'] = ($base && str_starts_with($file, $base) ? substr($file, strlen($base)) : $file) . ':' . $frame['line'];

            // The next frame is the function or method that contains the dd() call
            $outer = $trace[$i + 1] ?? null;
            if ($outer && isset($outer['function']) && !in_array($outer['function'], ['require', 'require_once', 'include', 'include_once'], true)) {
                $context['class'] = $outer['class'] ?? null;
                $context['in'] = self::describeCall($outer);
            }
            break;
        }
        return $context;
    }

    /** 'CategoryController::update($id = "4")' with the values it was called with */
    protected static function describeCall(array $frame): string
    {
        $class = $frame['class'] ?? null;
        $name = ($class ? substr(strrchr('\\' . $class, '\\'), 1) . '::' : '') . $frame['function'];
        $args = $frame['args'] ?? [];

        $names = [];
        try {
            $reflection = $class && method_exists($class, $frame['function'])
                ? new \ReflectionMethod($class, $frame['function'])
                : (function_exists($frame['function']) ? new \ReflectionFunction($frame['function']) : null);
            foreach ($reflection?->getParameters() ?? [] as $param) {
                $names[] = ($param->isVariadic() ? '...' : '') . '$' . $param->getName();
            }
        } catch (\ReflectionException) {
            // a closure or a dynamic call: show the values without names
        }

        $parts = [];
        foreach ($args as $index => $value) {
            $label = $names[$index] ?? (str_starts_with((string) end($names), '...') ? end($names) : null);
            $secret = $label !== null && preg_match('/pass|secret|token|key/i', $label);
            $shown = $secret ? '"***"' : self::short($value);
            $parts[] = $label !== null && !str_starts_with($label, '...') ? "$label = $shown" : $shown;
        }
        return $name . '(' . implode(', ', $parts) . ')';
    }

    /** A one-line preview of an argument */
    protected static function short(mixed $value): string
    {
        return match (true) {
            $value === null => 'null',
            is_bool($value) => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value) => json_encode(mb_strlen($value) > 40 ? mb_substr($value, 0, 40) . '…' : $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            is_array($value) => 'array(' . count($value) . ')',
            is_object($value) => substr(strrchr('\\' . get_class($value), '\\'), 1) . (isset($value->id) && is_scalar($value->id) ? ' #' . $value->id : ''),
            default => get_debug_type($value),
        };
    }

    /** "POST /categories" during a web request, null on the command line */
    protected static function request(): ?string
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' || !isset($_SERVER['REQUEST_METHOD'])) {
            return null;
        }
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        // The method the route sees: a form's _method=PUT shows as PUT
        $method = class_exists(\System\Http\Request::class) ? \System\Http\Request::method() : strtoupper((string) $_SERVER['REQUEST_METHOD']);
        return $method . ' ' . (mb_strlen($uri) > 120 ? mb_substr($uri, 0, 120) . '…' : $uri);
    }

    /** The header line for text output: "CategoryController::store() · app/…:24 · POST /categories" */
    public static function headline(array $context): string
    {
        return implode(' · ', array_filter([$context['in'], $context['at'], $context['request']]));
    }
}
