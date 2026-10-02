<?php
namespace System\Http;

use System\Storage\Storage;

/**
 * One file from a form upload ($_FILES), with safe checks.
 *
 *   $photo = Request::file('photo');
 *   if ($photo && $photo->isValid() && $photo->isImage()) {
 *       $path = $photo->store('products');   // "products/3f9c...e1.jpg" under public/storage
 *   }
 *
 * The stored name is random and its extension comes from the file's real
 * content type, never from the name the browser sent, so "shell.php.jpg"
 * or a PHP file renamed to .png cannot be stored as something executable.
 */
class UploadedFile
{
    /** Allowed real MIME types → extension used when storing */
    public const EXTENSIONS = [
        'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
        'application/pdf' => 'pdf', 'text/plain' => 'txt', 'text/csv' => 'csv',
        'application/zip' => 'zip', 'application/json' => 'json',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
    ];

    protected ?string $mime = null;

    public function __construct(
        protected string $clientName,
        protected string $path,
        protected int $error,
        protected int $size,
        protected bool $test = false
    ) {}

    /** Build from one entry of $_FILES */
    public static function fromArray(array $file): self
    {
        return new self(
            (string) ($file['name'] ?? ''),
            (string) ($file['tmp_name'] ?? ''),
            (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE),
            (int) ($file['size'] ?? 0)
        );
    }

    /** A file on disk pretending to be an upload (for tests and scripts) */
    public static function fake(string $path, ?string $clientName = null): self
    {
        return new self($clientName ?? basename($path), $path, UPLOAD_ERR_OK, (int) filesize($path), true);
    }

    public function isValid(): bool
    {
        return $this->error === UPLOAD_ERR_OK && ($this->test ? is_file($this->path) : is_uploaded_file($this->path));
    }

    public function error(): int
    {
        return $this->error;
    }

    public function errorMessage(): string
    {
        return match ($this->error) {
            UPLOAD_ERR_OK => 'no error',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'the file is larger than the server allows (' . ini_get('upload_max_filesize') . ')',
            UPLOAD_ERR_PARTIAL => 'the file was only partly uploaded',
            UPLOAD_ERR_NO_FILE => 'no file was sent',
            default => 'the server could not save the upload',
        };
    }

    /** The name the browser sent (display only — never trust it for storage) */
    public function clientName(): string
    {
        return $this->clientName;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function path(): string
    {
        return $this->path;
    }

    /** MIME type detected from the file's content */
    public function mimeType(): string
    {
        if ($this->mime === null) {
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $this->mime = $finfo ? (string) finfo_file($finfo, $this->path) : 'application/octet-stream';
            if ($finfo) finfo_close($finfo);
        }
        return $this->mime;
    }

    /** Safe extension from the real content type, or null when the type is not allowed */
    public function extension(): ?string
    {
        return self::EXTENSIONS[$this->mimeType()] ?? null;
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mimeType(), 'image/')
            && isset(self::EXTENSIONS[$this->mimeType()])
            && @getimagesize($this->path) !== false;
    }

    /**
     * Save under public/storage/$directory with a random name.
     * Returns the path relative to public/storage (store it in the database; show it with Storage::url()).
     */
    public function store(string $directory = ''): string
    {
        if (!$this->isValid()) {
            throw new \RuntimeException('Cannot store an invalid upload: ' . $this->errorMessage());
        }
        $extension = $this->extension();
        if ($extension === null) {
            throw new \RuntimeException('Refusing to store a file of type ' . $this->mimeType());
        }

        $relative = trim($directory, '/') . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        $relative = ltrim($relative, '/');
        Storage::putFile($relative, $this->path, !$this->test);

        return $relative;
    }
}
