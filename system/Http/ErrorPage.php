<?php
namespace System\Http;

use Throwable;

class ErrorPage
{
    /**
     * Send an error response. Uses resources/views/errors/{status}.php when it
     * exists, otherwise a built-in page. $exception details are shown only in debug.
     */
    public static function render(int $status, string $message, ?Throwable $exception = null): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
        }

        $viewFile = base_path("resources/views/errors/$status.php");
        if (!$exception && file_exists($viewFile)) {
            try {
                echo view("errors.$status", ['status' => $status, 'message' => $message]);
                return;
            } catch (Throwable $e) {
                // fall through to the built-in page
            }
        }

        $title = e("$status · $message");
        $details = '';
        if ($exception) {
            $details = '<p class="msg">' . e(get_class($exception) . ': ' . $exception->getMessage()) . '</p>'
                . '<p class="loc">' . e($exception->getFile() . ':' . $exception->getLine()) . '</p>'
                . '<pre>' . e($exception->getTraceAsString()) . '</pre>';
        }

        echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>$title</title>
<style>
  body{font-family:system-ui,sans-serif;background:#f6f7f9;color:#1f2328;margin:0;padding:48px 16px}
  .box{max-width:880px;margin:0 auto;background:#fff;border:1px solid #d8dee4;border-radius:8px;padding:32px}
  h1{margin:0 0 8px;font-size:28px} .msg{font-weight:600;color:#b42318} .loc{color:#57606a;font-family:monospace}
  pre{background:#f6f8fa;padding:16px;border-radius:6px;overflow:auto;font-size:12px}
</style></head>
<body><div class="box"><h1>$title</h1>$details</div></body></html>
HTML;
    }
}
