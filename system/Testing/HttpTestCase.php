<?php
namespace System\Testing;

use System\Database\DB;

/**
 * Feature tests: real HTTP requests against the app running on a test server
 * (see Runner). Cookies are kept per test, like one browser.
 */
abstract class HttpTestCase extends TestCase
{
    protected array $cookies = [];

    public function setUp(): void
    {
        $this->cookies = [];
    }

    protected function get(string $path, array $headers = []): TestResponse
    {
        return $this->request('GET', $path, [], $headers);
    }

    protected function post(string $path, array $data = [], array $headers = []): TestResponse
    {
        return $this->request('POST', $path, $data, $headers);
    }

    /**
     * POST with the CSRF token taken from the given page (like a real form submit)
     */
    protected function submit(string $formPagePath, string $action, array $data = []): TestResponse
    {
        $page = $this->get($formPagePath);
        return $this->post($action, $data + ['_token' => $page->csrfToken()]);
    }

    /** Send a JSON body (API tests): $this->jsonRequest('POST', '/api/x', ['a' => 1], ['Authorization' => 'Bearer …']) */
    protected function jsonRequest(string $method, string $path, array $data = [], array $headers = []): TestResponse
    {
        $headers += ['Accept' => 'application/json', 'Content-Type' => 'application/json'];
        return $this->request($method, $path, $method === 'GET' ? [] : json_encode($data), $headers);
    }

    protected function request(string $method, string $path, array|string $data = [], array $headers = []): TestResponse
    {
        $ch = curl_init(Runner::$baseUrl . $path);

        $headerLines = ['Accept: text/html'];
        foreach ($headers as $name => $value) {
            $headerLines[] = "$name: $value";
        }
        if ($this->cookies) {
            $pairs = [];
            foreach ($this->cookies as $name => $value) $pairs[] = "$name=$value";
            $headerLines[] = 'Cookie: ' . implode('; ', $pairs);
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headerLines,
        ]);
        if ($method !== 'GET' && $method !== 'HEAD' && ($data !== [] || $method !== 'OPTIONS')) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($data) ? $data : http_build_query($data));
        }

        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new \RuntimeException('HTTP request failed: ' . curl_error($ch));
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerText = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);

        $responseHeaders = [];
        foreach (preg_split('/\r?\n/', $headerText) as $line) {
            if (!str_contains($line, ':')) continue;
            [$name, $value] = array_map('trim', explode(':', $line, 2));
            $responseHeaders[strtolower($name)][] = $value;

            if (strtolower($name) === 'set-cookie') {
                [$pair] = explode(';', $value, 2);
                [$cookieName, $cookieValue] = array_pad(explode('=', $pair, 2), 2, '');
                if ($cookieValue === 'deleted' || $cookieValue === '') unset($this->cookies[$cookieName]);
                else $this->cookies[$cookieName] = $cookieValue;
            }
        }

        return new TestResponse($status, $responseHeaders, $body, $this);
    }

    /**
     * Log in as the seeded admin and return the dashboard response
     */
    protected function loginAsAdmin(): TestResponse
    {
        $response = $this->submit('/login', '/login', ['email' => 'admin@example.com', 'password' => 'password']);
        $this->assertSame(302, $response->status, 'Admin login should redirect');
        return $response;
    }

    protected function db(string $table)
    {
        return DB::table($table);
    }

    /** used by TestResponse */
    public function check(bool $condition, string $message): void
    {
        $this->assert($condition, $message);
    }
}
