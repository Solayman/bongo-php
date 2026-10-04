<?php

namespace System\Http\Client;

/**
 * The answer to an Http:: request:
 *   $r->ok(); $r->status(); $r->json('data.id'); $r->body(); $r->header('content-type');
 */
class Response
{
    protected ?array $decoded = null;

    public function __construct(protected int $status, protected array $headers = [], protected string $body = '')
    {
    }

    public function status(): int
    {
        return $this->status;
    }
    public function body(): string
    {
        return $this->body;
    }

    /** 2xx */
    public function ok(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
    public function failed(): bool
    {
        return $this->status >= 400;
    }
    public function clientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }
    public function serverError(): bool
    {
        return $this->status >= 500;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    /** The JSON body, or one value in it with dots: json('data.0.id') */
    public function json(?string $key = null, mixed $default = null): mixed
    {
        $this->decoded ??= (array) (json_decode($this->body, true) ?? []);
        if ($key === null) {
            return $this->decoded;
        }
        $value = $this->decoded;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }
        return $value;
    }

    /** Throw when the request failed (4xx/5xx), otherwise return $this */
    public function throw(): static
    {
        if ($this->failed()) {
            throw new RequestException("HTTP {$this->status}: " . mb_substr($this->body, 0, 200), $this);
        }
        return $this;
    }
}
