<?php

namespace System\Debug;

use Throwable;

/**
 * The developer error page shown when APP_DEBUG=true:
 * exception, the source around the failing line, a stack trace with code,
 * and the request (with secrets masked). Never shown in production.
 */
class ExceptionPage
{
    protected const SECRET = '/pass|token|secret|key|auth|cookie|session/i';

    public function __construct(protected Throwable $exception, protected int $status = 500)
    {
    }

    public function render(): string
    {
        $e = $this->exception;
        $h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $frames = $this->frames();
        $frameHtml = '';
        foreach ($frames as $i => $frame) {
            $snippet = $frame['file'] ? $this->snippet($frame['file'], $frame['line'], 5) : '';
            $label = $frame['call'] !== '' ? $h($frame['call']) : '<em>main</em>';
            $where = $frame['file'] ? $h($this->relative($frame['file'])) . ':' . $frame['line'] : '[internal]';
            $frameHtml .= '<details class="frame' . ($frame['app'] ? ' app' : '') . '"' . ($i === 0 ? ' open' : '') . '>'
                . '<summary><span class="idx">#' . $i . '</span> <span class="call">' . $label . '</span><span class="where">' . $where . '</span></summary>'
                . $snippet . '</details>';
        }

        $previous = '';
        for ($p = $e->getPrevious(); $p; $p = $p->getPrevious()) {
            $previous .= '<div class="prev">Caused by <strong>' . $h(get_class($p)) . '</strong>: ' . $h($p->getMessage())
                . ' <span class="where">' . $h($this->relative($p->getFile())) . ':' . $p->getLine() . '</span></div>';
        }

        $hint = Hint::for($e);
        $hintHtml = $hint ? '<div class="hint"><strong>How to fix:</strong> ' . $h($hint) . '</div>' : '';
        $title = $h(get_class($e));
        $message = $h($e->getMessage());
        $location = $h($this->relative($e->getFile())) . ':' . $e->getLine();
        $main = $this->snippet($e->getFile(), $e->getLine(), 10);
        $request = $this->table($this->requestInfo());
        $input = $this->table($this->mask($_POST + $_GET));
        $headers = $this->table($this->mask($this->headers()));
        $env = $this->table([
            'PHP' => PHP_VERSION,
            'Environment' => function_exists('config') ? config('app.env') : '',
            'Time' => date('Y-m-d H:i:s T'),
            'Memory peak' => round(memory_get_peak_usage() / 1048576, 1) . ' MB',
        ]);

        return <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$this->status} · {$title}</title>
<style>
*{box-sizing:border-box}body{margin:0;background:#f4f5f7;color:#1f2328;font:14px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif}
.wrap{max-width:1100px;margin:0 auto;padding:24px 16px 48px}
.head{background:#fff;border:1px solid #d8dee4;border-left:6px solid #d1242f;border-radius:8px;padding:20px 24px}
.status{color:#57606a;font-size:12px;text-transform:uppercase;letter-spacing:.06em}
h1{margin:4px 0 6px;font-size:20px;word-break:break-word}.msg{font-size:17px;color:#d1242f;margin:0 0 8px;word-break:break-word}
.where{color:#57606a;font-family:ui-monospace,Consolas,monospace;font-size:12px;margin-left:8px}
.prev{margin-top:6px;color:#57606a}
.hint{margin-top:14px;padding:10px 14px;background:#fff8c5;border:1px solid #d4a72c;border-radius:6px;font-size:14px}
h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#57606a;margin:28px 0 8px}
.code{background:#1e1f2b;border-radius:8px;overflow:auto;font:12.5px/1.6 ui-monospace,Consolas,monospace;color:#cdd6f4;margin:8px 0}
.code .ln{display:flex}.code .no{flex:0 0 52px;text-align:right;padding-right:12px;color:#6c7086;user-select:none}
.code .src{white-space:pre;padding-right:16px}.code .hit{background:#5a1d2a}.code .hit .no{color:#f38ba8;font-weight:700}
.frame{background:#fff;border:1px solid #d8dee4;border-radius:6px;margin:6px 0;padding:8px 12px}
.frame summary{cursor:pointer;font-family:ui-monospace,Consolas,monospace;font-size:12.5px;word-break:break-all}
.frame.app{border-left:4px solid #0969da}.idx{color:#8c959f}.call{color:#1f2328}
table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d8dee4;border-radius:6px;overflow:hidden}
td{padding:6px 12px;border-top:1px solid #eaeef2;vertical-align:top;font-family:ui-monospace,Consolas,monospace;font-size:12.5px;word-break:break-all}
td:first-child{width:220px;color:#57606a;font-family:system-ui,sans-serif}tr:first-child td{border-top:0}
.empty{color:#8c959f;font-style:italic}.note{color:#8c959f;font-size:12px;margin-top:24px}
</style></head><body><div class="wrap">
<div class="head"><div class="status">{$this->status} · Server error</div><h1>{$title}</h1><p class="msg">{$message}</p>
<div class="where" style="margin-left:0">{$location}</div>{$previous}{$hintHtml}</div>
<h2>Source</h2>{$main}
<h2>Stack trace</h2>{$frameHtml}
<h2>Request</h2>{$request}
<h2>Input</h2>{$input}
<h2>Headers</h2>{$headers}
<h2>Environment</h2>{$env}
<p class="note">You see this page because APP_DEBUG is on. Set APP_DEBUG=false (or APP_ENV=production) to show a plain error page instead. Blue frames are your app code.</p>
</div></body></html>
HTML;
    }

    /** Frames starting with the throw site */
    public function frames(): array
    {
        $e = $this->exception;
        $frames = [['file' => $e->getFile(), 'line' => $e->getLine(), 'call' => '']];
        $trace = $e->getTrace();

        foreach ($trace as $i => $t) {
            $call = ($t['class'] ?? '') . ($t['type'] ?? '') . ($t['function'] ?? '') . '()';
            // The call in frame $i happened at the file/line of frame $i
            $frames[$i]['call'] = $call;
            $frames[] = ['file' => $t['file'] ?? null, 'line' => $t['line'] ?? 0, 'call' => ''];
        }

        foreach ($frames as &$frame) {
            $file = str_replace('\\', '/', (string) $frame['file']);
            $frame['app'] = $file !== '' && !str_contains($file, '/system/') && !str_contains($file, '/vendor/') && !str_contains($file, '/public/index.php');
        }

        return array_values(array_filter($frames, fn($f) => $f['file'] || $f['call']));
    }

    /** Highlighted source lines around $line */
    public function snippet(string $file, int $line, int $radius): string
    {
        return self::sourceLines($file, $line, $radius);
    }

    /** The same, for other debug pages (the dd() page) */
    public static function sourceLines(string $file, int $line, int $radius): string
    {
        if (!is_file($file) || !is_readable($file)) {
            return '';
        }
        $lines = @file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $from = max(1, $line - $radius);
        $to = min(count($lines), $line + $radius);

        $html = '<div class="code">';
        for ($n = $from; $n <= $to; $n++) {
            $src = htmlspecialchars(str_replace("\t", '    ', $lines[$n - 1] ?? ''), ENT_QUOTES, 'UTF-8');
            $html .= '<div class="ln' . ($n === $line ? ' hit' : '') . '"><span class="no">' . $n . '</span><span class="src">' . ($src === '' ? ' ' : $src) . '</span></div>';
        }
        return $html . '</div>';
    }

    protected function requestInfo(): array
    {
        $scheme = \System\Http\Request::isSecure() ? 'https' : 'http';
        return [
            'Method' => $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            'URL'    => isset($_SERVER['HTTP_HOST']) ? $scheme . '://' . $_SERVER['HTTP_HOST'] . ($_SERVER['REQUEST_URI'] ?? '') : '',
            'IP'     => \System\Http\Request::ip(),
            'User'   => function_exists('auth') && session_status() === PHP_SESSION_ACTIVE ? (auth()->email ?? 'guest') : '',
        ];
    }

    protected function headers(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[ucwords(strtolower(str_replace('_', '-', substr($key, 5))), '-')] = $value;
            }
        }
        return $headers;
    }

    /** Hide values of keys that look secret */
    public function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (preg_match(self::SECRET, (string) $key)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = json_encode($this->mask($value));
            }
        }
        return $data;
    }

    protected function table(array $rows): string
    {
        if (!$rows) {
            return '<table><tr><td class="empty" colspan="2">none</td></tr></table>';
        }
        $html = '<table>';
        foreach ($rows as $k => $v) {
            $html .= '<tr><td>' . htmlspecialchars((string) $k) . '</td><td>' . htmlspecialchars(is_scalar($v) || $v === null ? (string) $v : json_encode($v)) . '</td></tr>';
        }
        return $html . '</table>';
    }

    protected function relative(?string $file): string
    {
        $file = str_replace('\\', '/', (string) $file);
        $base = defined('BASE_PATH') ? str_replace('\\', '/', BASE_PATH) . '/' : '';
        return $base && str_starts_with($file, $base) ? substr($file, strlen($base)) : $file;
    }
}
