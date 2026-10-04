<?php

namespace System\Debug;

/**
 * The page dd() shows in the browser: where it was called (method, file, line, request),
 * the source around the call, and each value in its own card.
 */
class DdPage
{
    public function __construct(protected array $values, protected array $context)
    {
    }

    public function render(): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
        $context = $this->context;
        [$file, $line] = $this->location($context['at']);
        $title = $context['in'] ?? basename($file);

        // Request badge, timing and memory
        $badge = '';
        if ($context['request']) {
            [$method, $path] = array_pad(explode(' ', $context['request'], 2), 2, '');
            $badge = '<span class="method m-' . strtolower($e($method)) . '">' . $e($method) . '</span><span class="path">' . $e($path) . '</span>';
        }
        $stats = [];
        if (isset($_SERVER['REQUEST_TIME_FLOAT'])) {
            $stats[] = number_format((microtime(true) - (float) $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1) . ' ms';
        }
        $stats[] = number_format(memory_get_peak_usage() / 1048576, 1) . ' MB';

        $editor = $this->editorLink($file, $line);
        $source = $line > 0 ? ExceptionPage::sourceLines($file, $line, 4) : '';

        $dumper = new Dumper();
        $cards = '';
        $count = count($this->values);
        foreach (array_values($this->values) as $i => $value) {
            $json = json_encode($dumper->plain($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            $cards .= '<section class="card value">'
                . '<header><span class="num">' . ($count > 1 ? '#' . ($i + 1) . ' · ' : '') . '</span><span class="vtype">' . $e($this->typeLabel($value)) . '</span>'
                . '<span class="tools"><button type="button" data-act="expand">Expand all</button><button type="button" data-act="collapse">Collapse all</button>'
                . '<button type="button" data-act="copy">Copy as JSON</button></span></header>'
                . $dumper->html($value)
                . '<script type="application/json" class="json">' . str_replace('</', '<\/', (string) $json) . '</script>'
                . '</section>';
        }

        $where = $e($context['at']) . ($editor ? ' · <a href="' . $e($editor) . '">Open in editor</a>' : '');
        $classTitle = $context['class'] ? ' title="' . $e($context['class']) . '"' : '';

        // Compact: one header line, the values right under it, the source folded away below
        return '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>dd() · ' . $e($title) . '</title>' . Dumper::styles() . '<style>' . self::css() . '</style></head><body><main>'
            . '<header class="bar"><span class="tag" title="dd() stopped the request here">dd()</span>'
            . '<code class="in"' . $classTitle . '>' . $e($title) . '</code>'
            . '<span class="where">' . $where . '</span>'
            . ($badge ? '<span class="req">' . $badge . '</span>' : '')
            . '<span class="stats">' . $e(implode(' · ', $stats)) . '</span></header>'
            . ($cards ?: '<p class="empty">dd() was called without values.</p>')
            . ($source ? '<details class="source"><summary>Source · ' . $e(basename($file)) . ' line ' . $line . '</summary>' . $source . '</details>' : '')
            . '<p class="note">dd() stops the request here. Remove the call (' . $e($context['at']) . ') to continue.</p>'
            . '</main><script>' . self::js() . '</script></body></html>';
    }

    /** "app/X.php:26" → [absolute path, 26] */
    protected function location(string $at): array
    {
        $line = 0;
        $file = $at;
        if (preg_match('/^(.*):(\d+)$/', $at, $m)) {
            [$file, $line] = [$m[1], (int) $m[2]];
        }
        if (defined('BASE_PATH') && !preg_match('#^([A-Za-z]:)?[/\\\\]#', $file)) {
            $file = BASE_PATH . '/' . $file;
        }
        return [$file, $line];
    }

    /** vscode://file/... (default), phpstorm://, sublime:// or none, from APP_EDITOR */
    protected function editorLink(string $file, int $line): ?string
    {
        $editor = function_exists('config') ? (string) config('app.editor', 'vscode') : 'vscode';
        if ($line < 1 || !is_file($file)) {
            return null;
        }
        $path = str_replace('\\', '/', (string) realpath($file));
        return match ($editor) {
            'vscode'   => 'vscode://file/' . $path . ':' . $line,
            'phpstorm' => 'phpstorm://open?file=' . rawurlencode($path) . '&line=' . $line,
            'sublime'  => 'subl://open?url=file://' . rawurlencode($path) . '&line=' . $line,
            default    => null,
        };
    }

    protected function typeLabel(mixed $value): string
    {
        $limit = (new Dumper())->maxItems();
        return match (true) {
            is_array($value) && count($value) > $limit
                => 'array (' . number_format(count($value)) . ' items) · showing the first ' . $limit,
            is_array($value)  => 'array (' . count($value) . ' ' . (count($value) === 1 ? 'item' : 'items') . ')',
            is_object($value) => get_class($value),
            is_string($value) => 'string (' . number_format(mb_strlen($value)) . ' characters)',
            default           => get_debug_type($value),
        };
    }

    protected static function css(): string
    {
        return <<<'CSS'
:root{--bg:#f4f5f7;--card:#fff;--line:#d8dee4;--text:#1f2328;--muted:#57606a;--accent:#0969da;--code:#1e1f2b}
@media (prefers-color-scheme:dark){:root{--bg:#0d1117;--card:#161b22;--line:#30363d;--text:#e6edf3;--muted:#8b949e;--accent:#58a6ff;--code:#11121a}}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
main{max-width:1200px;margin:0 auto;padding:12px 16px 32px}
.bar,.card{background:var(--card);border:1px solid var(--line);border-radius:8px}
.bar{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;padding:8px 12px;border-left:4px solid var(--accent);font:12.5px ui-monospace,Consolas,monospace;color:var(--muted)}
.tag{font:700 11px system-ui,sans-serif;background:var(--accent);color:#fff;border-radius:4px;padding:2px 7px}
.in{font:600 13.5px ui-monospace,Consolas,monospace;color:var(--accent);word-break:break-word}
.bar a{color:var(--accent);text-decoration:none}.bar a:hover{text-decoration:underline}
.req{display:inline-flex;align-items:center;gap:6px}.stats{margin-left:auto}
.source{margin:10px 0 0}.source summary{cursor:pointer;color:var(--muted);font-size:12.5px;padding:6px 2px}
.source[open] summary{margin-bottom:6px}
.method{font:700 11px system-ui,sans-serif;padding:2px 7px;border-radius:4px;color:#fff;background:#6e7781;letter-spacing:.03em}
.m-get{background:#0969da}.m-post{background:#1a7f37}.m-put,.m-patch{background:#9a6700}.m-delete{background:#cf222e}
.path{color:var(--text)}
.code{background:var(--code);border-radius:8px;overflow:auto;font:12.5px/1.6 ui-monospace,Consolas,monospace;color:#cdd6f4}
.code .ln{display:flex}.code .no{flex:0 0 52px;text-align:right;padding-right:12px;color:#6c7086;user-select:none}
.code .src{white-space:pre;padding-right:16px}.code .hit{background:#1f3a5f}.code .hit .no{color:#89b4fa;font-weight:700}
.card.value{margin:8px 0;overflow:hidden}
.card header{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:8px 12px;border-bottom:1px solid var(--line);font-size:13px}
.num{color:var(--muted)}.vtype{font:600 13px ui-monospace,Consolas,monospace}
.tools{margin-left:auto;display:flex;gap:6px}
.tools button{font:12px system-ui,sans-serif;color:var(--text);background:transparent;border:1px solid var(--line);border-radius:5px;padding:3px 9px;cursor:pointer}
.tools button:hover{border-color:var(--accent);color:var(--accent)}
.card .bongo-dump{margin:0;border-radius:0;padding:14px 16px;font-size:13.5px;background:var(--code)}
.empty{color:var(--muted);font-style:italic}.note{color:var(--muted);font-size:12px;margin-top:10px}
@media (max-width:640px){.stats{margin-left:0}.tools{margin-left:0}}
CSS;
    }

    protected static function js(): string
    {
        return <<<'JS'
document.querySelectorAll('.card.value').forEach(function (card) {
  card.querySelectorAll('button').forEach(function (button) {
    button.addEventListener('click', function () {
      var act = button.dataset.act;
      if (act === 'copy') {
        var json = card.querySelector('script.json').textContent;
        var done = function () { var old = button.textContent; button.textContent = 'Copied'; setTimeout(function () { button.textContent = old; }, 1200); };
        if (navigator.clipboard) navigator.clipboard.writeText(json).then(done); else { prompt('JSON', json); }
      } else {
        card.querySelectorAll('details').forEach(function (d) { d.open = act === 'expand'; });
      }
    });
  });
});
JS;
    }
}
