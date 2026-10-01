<?php
namespace System\Testing;

class TestResponse
{
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
        protected HttpTestCase $test
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /**
     * Path part of the Location header ("/login")
     */
    public function location(): ?string
    {
        $location = $this->header('location');
        return $location === null ? null : (parse_url($location, PHP_URL_PATH) ?: '/');
    }

    public function csrfToken(): string
    {
        if (!preg_match('/name="_token" value="([^"]+)"/', $this->body, $m)
            && !preg_match('/name="csrf-token" content="([^"]+)"/', $this->body, $m)) {
            throw new AssertionFailed('No CSRF token found in the response');
        }
        return html_entity_decode($m[1]);
    }

    public function assertStatus(int $status): self
    {
        $this->test->check($this->status === $status, "Expected HTTP $status, got {$this->status}");
        return $this;
    }

    public function assertRedirect(?string $path = null): self
    {
        $this->test->check(in_array($this->status, [301, 302, 303], true), "Expected a redirect, got HTTP {$this->status}");
        if ($path !== null) {
            $this->test->check($this->location() === $path, "Expected redirect to $path, got " . var_export($this->location(), true));
        }
        return $this;
    }

    public function assertSee(string $text): self
    {
        $this->test->check(str_contains($this->body, $text), "Expected to see \"$text\" in the response");
        return $this;
    }

    public function assertDontSee(string $text): self
    {
        $this->test->check(!str_contains($this->body, $text), "Did not expect to see \"$text\" in the response");
        return $this;
    }
}
