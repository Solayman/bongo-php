<?php

namespace System\Http\Client;

/**
 * Behind Http:: — also holds the fake responses and the sent requests in tests:
 *   Http::fake(['api.example.com/*' => Http::response(['id' => 7], 201)]);
 *   ... code that calls the API ...
 *   Http::sent()  // [['method' => 'POST', 'url' => ..., 'data' => [...]], ...]
 */
class Factory
{
    protected ?array $fakes = null;
    protected array $sent = [];

    public function fake(array $responses = []): void
    {
        $this->fakes = $responses;
        $this->sent = [];
    }

    public function isFaked(): bool
    {
        return $this->fakes !== null;
    }

    /** The fake response for a request: the first matching URL pattern ("*" = anything), or 200 {} */
    public function fakeResponse(array $request): ?Response
    {
        if ($this->fakes === null) {
            return null;
        }
        $url = preg_replace('#^https?://#', '', $request['url']);
        foreach ($this->fakes as $pattern => $response) {
            $regex = '#^' . str_replace('\*', '.*', preg_quote(preg_replace('#^https?://#', '', (string) $pattern), '#')) . '$#';
            if (preg_match($regex, $url)) {
                return $response instanceof \Closure ? $response($request) : $response;
            }
        }
        return new Response(200, ['content-type' => 'application/json'], '{}');
    }

    public function record(array $request, Response $response): void
    {
        if ($this->fakes !== null) {
            $this->sent[] = $request + ['status' => $response->status()];
        }
    }

    public function sent(): array
    {
        return $this->sent;
    }
}
