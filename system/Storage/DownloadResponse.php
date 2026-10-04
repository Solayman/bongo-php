<?php

namespace System\Storage;

/**
 * A file sent to the browser as a download. Return it from a controller:
 *   return Storage::disk('local')->download('invoices/42.pdf', 'Invoice 42.pdf');
 * $deleteAfterSend removes a temporary file once it has been sent.
 */
class DownloadResponse
{
    public array $headers = [];
    public ?string $mimeType = null;

    public function __construct(
        public string $path,
        public string $filename,
        array|string $headers = [],
        ?string $mimeType = null,
        public bool $deleteAfterSend = false
    ) {
        if (is_string($headers)) {
            $this->mimeType = $headers;
            $this->headers = [];
        } else {
            $this->headers = $headers;
            $this->mimeType = $mimeType;
        }
    }

    public function send(): void
    {
        if (!is_file($this->path)) {
            throw new \System\Http\HttpException(404, "File not found [{$this->filename}]");
        }
        $mime = $this->mimeType ?: (@mime_content_type($this->path) ?: 'application/octet-stream');

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . self::headerValue($mime));
            header('Content-Length: ' . (string) filesize($this->path));
            header('Content-Disposition: ' . self::disposition($this->filename));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: private, no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            foreach ($this->headers as $name => $value) {
                if (preg_match('/^[A-Za-z0-9-]+$/', (string) $name)) {
                    header($name . ': ' . self::headerValue((string) $value));
                }
            }
        }
        try {
            readfile($this->path);
        } finally {
            if ($this->deleteAfterSend) {
                @unlink($this->path);
            }
        }
    }

    /** attachment; filename="ascii fallback"; filename*=UTF-8''<the real name> (Bangla names work) */
    public static function disposition(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));
        $ascii = preg_replace('/[^\x20-\x7E]|["\\\\]/', '_', $filename);
        return 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($filename);
    }

    /** Header values never contain line breaks (no header injection) */
    protected static function headerValue(string $value): string
    {
        return str_replace(["\r", "\n", "\0"], '', $value);
    }
}
