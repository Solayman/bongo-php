<?php
// Application helpers. Framework helpers live in system/Support/helpers.php.

if (!function_exists('dd')) {
    function dd(...$args)
    {
        // --- Find real caller (skip helper files) ---
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $caller = null;
        foreach ($backtrace as $trace) {
            if (
                isset($trace['file']) &&
                !str_contains($trace['file'], 'helpers') &&
                !str_contains($trace['file'], 'commonHelper') &&
                !str_contains($trace['file'], 'vendor')
            ) {
                $caller = $trace;
                break;
            }
        }

        // --- Make file path relative ---
        $file = $caller['file'] ?? 'unknown';
        $line = $caller['line'] ?? '?';
        $root = realpath(BASE_PATH);

        if ($root && str_starts_with($file, $root)) {
            $file = str_replace('\\', '/', substr($file, strlen($root) + 1));
        } else {
            $file = basename($file);
        }

        // --- Output HTML ---
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">';
        echo '<title>Debug Dump</title>';
        echo '<style>
            body {
                font-family: "Fira Code", Consolas, monospace;
                background: #1e1e2f;
                color: #f1f1f1;
                margin: 0;
                padding: 30px;
            }
            .dump-wrapper {
                max-width: 1000px;
                margin: 0 auto;
            }
            .header {
                background: #28293d;
                padding: 15px 20px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 15px;
                color: #8be9fd;
                border: 1px solid #3c3f58;
            }
            .dump {
                background: #2b2c3e;
                border-radius: 8px;
                border: 1px solid #3c3f58;
                padding: 15px;
                margin-bottom: 20px;
                font-size: 14px;
                line-height: 1.5;
                overflow-x: auto;
            }
            .type { color: #8be9fd; font-weight: bold; }
            .key { color: #50fa7b; }
            .string { color: #f1fa8c; }
            .number { color: #bd93f9; }
            .bool { color: #ff79c6; }
            .null { color: #999; font-style: italic; }
            details { margin-left: 10px; }
            details summary {
                cursor: pointer;
                color: #ffb86c;
                user-select: none;
                outline: none;
            }
            pre { margin: 0; white-space: pre-wrap; word-break: break-word; }
            .footer {
                margin-top: 30px;
                color: #888;
                font-size: 13px;
                text-align: center;
            }
        </style></head><body><div class="dump-wrapper">';

        echo "<div class='header'>🧭 Dump called at <strong>{$file}</strong> line <strong>{$line}</strong></div>";

        foreach ($args as $i => $arg) {
            echo "<div class='dump'>";
            echo "<div><strong class='type'>#" . ($i + 1) . "</strong> — " . gettype($arg) . "</div>";
            echo "<hr style='border-color:#3c3f58;opacity:0.5;margin:8px 0;'>";
            echo "<pre>" . _prettyPrint($arg) . "</pre>";
            echo "</div>";
        }

        echo "<div class='footer'>BongoMvc dd() debug dump</div>";
        echo "</div></body></html>";

        die;
    }

    // --- Recursive pretty printer with collapsible sections ---
    function _prettyPrint($value, $depth = 0)
    {
        if (is_array($value)) {
            $count = count($value);
            $indent = str_repeat('  ', $depth);
            $out = "{$indent}<details open><summary>Array ({$count})</summary>\n";
            $out .= "{$indent}[\n";

            $i = 0;
            foreach ($value as $k => $v) {
                $i++;
                $key = is_int($k) ? $k : "'{$k}'";
                $out .= str_repeat('  ', $depth + 1)
                    . "<span class='key'>{$key}</span> => "
                    . _prettyPrint($v, $depth + 1);
                if ($i < $count) $out .= ","; // comma only if not last
                $out .= "\n";
            }

            $out .= "{$indent}]</details>";
            return $out;
        }
        elseif (is_object($value)) {
            $props = (array) $value;
            $class = get_class($value);
            $indent = str_repeat('  ', $depth);
            $out = "{$indent}<details open><summary>Object({$class})</summary>\n";
            $out .= "{$indent}{\n";

            $i = 0; $count = count($props);
            foreach ($props as $k => $v) {
                $i++;
                $out .= str_repeat('  ', $depth + 1)
                    . "<span class='key'>{$k}</span>: "
                    . _prettyPrint($v, $depth + 1);
                if ($i < $count) $out .= ",";
                $out .= "\n";
            }

            $out .= "{$indent}}</details>";
            return $out;
        }
        elseif (is_string($value)) {
            return "<span class='string'>'" . htmlspecialchars($value) . "'</span>";
        }
        elseif (is_int($value) || is_float($value)) {
            return "<span class='number'>{$value}</span>";
        }
        elseif (is_bool($value)) {
            return "<span class='bool'>" . ($value ? 'true' : 'false') . "</span>";
        }
        elseif (is_null($value)) {
            return "<span class='null'>null</span>";
        }
        else {
            return htmlspecialchars((string) $value);
        }
    }

}



