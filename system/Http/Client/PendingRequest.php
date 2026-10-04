<?php

namespace System\Http\Client;

use RuntimeException;

/**
 * One outgoing HTTP request, built fluently (start it with Http::...):
 *   Http::withToken($key)->timeout(10)->retry(3, 200)->post($url, ['to' => $phone]);
 * Bodies are JSON unless asForm() is used. Uses cURL when available, PHP streams otherwise.
 */
class PendingRequest
{
    protected array $headers = ['Accept' => 'application/json', 'User-Agent' => 'Bongo'];
    protected int $timeout = 30;
    protected int $tries = 1;
    protected int $retryDelayMs = 0;
    protected bool $form = false;

    public function __construct(protected ?Factory $factory = null)
    {
    }

    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /** Authorization: Bearer <token> */
    public function withToken(string $token, string $type = 'Bearer'): static
    {
        return $this->withHeaders(['Authorization' => trim("$type $token")]);
    }

    public function withBasicAuth(string $user, string $password): static
    {
        return $this->withHeaders(['Authorization' => 'Basic ' . base64_encode("$user:$password")]);
    }

    /** Send the body as a form (application/x-www-form-urlencoded) instead of JSON */
    public function asForm(): static
    {
        $this->form = true;
        return $this;
    }

    public function timeout(int $seconds): static
    {
        $this->timeout = max(1, $seconds);
        return $this;
    }

    /** Try up to $times when the connection fails or the server answers 5xx, waiting $sleepMs between tries */
    public function retry(int $times, int $sleepMs = 0): static
    {
        $this->tries = max(1, $times);
        $this->retryDelayMs = max(0, $sleepMs);
        return $this;
    }

    public function get(string $url, array $query = []): Response
    {
        return $this->send('GET', $url . ($query ? (str_contains($url, '?') ? '&' : '?') . http_build_query($query) : ''));
    }

    public function post(string $url, array $data = []): Response
    {
        return $this->send('POST', $url, $data);
    }
    public function put(string $url, array $data = []): Response
    {
        return $this->send('PUT', $url, $data);
    }
    public function patch(string $url, array $data = []): Response
    {
        return $this->send('PATCH', $url, $data);
    }
    public function delete(string $url, array $data = []): Response
    {
        return $this->send('DELETE', $url, $data);
    }

    public function send(string $method, string $url, array $data = []): Response
    {
        if (!preg_match('#^https?://#i', $url)) {
            throw new RuntimeException("Not an http(s) URL: $url");
        }
        $headers = $this->headers;
        $body = null;
        if ($method !== 'GET' && $data !== []) {
            $body = $this->form ? http_build_query($data) : json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers['Content-Type'] = $this->form ? 'application/x-www-form-urlencoded' : 'application/json';
        }
        $request = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body, 'data' => $data];

        for ($attempt = 1;; $attempt++) {
            try {
                $response = $this->factory?->fakeResponse($request) ?? $this->transport($request);
                if ($response->status() < 500 || $attempt >= $this->tries) {
                    $this->factory?->record($request, $response);
                    return $response;
                }
            } catch (ConnectionException $e) {
                if ($attempt >= $this->tries) {
                    throw $e;
                }
            }
            if ($this->retryDelayMs) {
                usleep($this->retryDelayMs * 1000);
            }
        }
    }

    protected function transport(array $request): Response
    {
        $lines = [];
        foreach ($request['headers'] as $name => $value) {
            $lines[] = "$name: $value";
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($request['url']);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => $request['method'],
                CURLOPT_HTTPHEADER     => $lines,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER         => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 5,
            ]);
            if ($request['body'] !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request['body']);
            }
            $raw = curl_exec($ch);
            if ($raw === false) {
                $error = curl_error($ch);
                curl_close($ch);
                throw new ConnectionException("{$request['method']} {$request['url']} failed: $error");
            }
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            return new Response($status, self::parseHeaders(substr($raw, 0, $headerSize)), (string) substr($raw, $headerSize));
        }

        $context = stream_context_create(['http' => [
            'method' => $request['method'], 'header' => implode("\r\n", $lines), 'content' => $request['body'] ?? '',
            'timeout' => $this->timeout, 'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($request['url'], false, $context);
        if ($body === false) {
            throw new ConnectionException("{$request['method']} {$request['url']} failed");
        }
        $responseHeaders = $http_response_header ?? [];
        $statusLines = array_values(array_filter($responseHeaders, fn($h) => str_starts_with($h, 'HTTP/')));
        preg_match('#HTTP/\S+\s+(\d{3})#', (string) ($statusLines[count($statusLines) - 1] ?? ''), $m);
        return new Response((int) ($m[1] ?? 0), self::parseHeaders(implode("\r\n", $responseHeaders)), $body);
    }

    /** Raw header text → [lower-case name => value] (the last block wins after redirects) */
    protected static function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (preg_split('/\r?\n/', $raw) as $line) {
            if (str_starts_with($line, 'HTTP/')) {
                $headers = [];
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }
        return $headers;
    }
}
