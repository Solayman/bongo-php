<?php
namespace System\Storage;

class DownloadResponse
{
    public function __construct(
        public string $path,
        public string $filename,
        public array $headers = []
    ) {}

    public function send(): void
    {
        if (!is_file($this->path)) {
            throw new \System\Http\HttpException(404, "File not found [{$this->filename}]");
        }
        $mime = @mime_content_type($this->path) ?: 'application/octet-stream';
        $size = filesize($this->path);

        if (!headers_sent()) {
            http_response_code(200);
            header('Content-Type: ' . $mime);
            header('Content-Length: ' . (string) $size);
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $this->filename) . '"');
            header('Cache-Control: private, no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            foreach ($this->headers as $k => $v) {
                header("$k: $v");
            }
        }
        readfile($this->path);
    }
}
