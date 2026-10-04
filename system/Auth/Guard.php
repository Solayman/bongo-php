<?php

namespace System\Auth;

/**
 * Common shape of every guard (session, jwt, or your own).
 */
abstract class Guard
{
    protected ?object $user = null;
    protected bool $resolved = false;

    public function __construct(protected string $name, protected UserProvider $provider, protected array $config = [])
    {
    }

    /** The authenticated user, or null */
    abstract public function user(): ?object;

    public function name(): string
    {
        return $this->name;
    }

    public function provider(): UserProvider
    {
        return $this->provider;
    }

    public function config(string $key, $default = null)
    {
        return $this->config[$key] ?? $default;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user ? (int) $user->id : null;
    }

    /** Check credentials without logging in */
    public function validate(string $identifier, string $password): bool
    {
        return $this->provider->findByCredentials($identifier, $password) !== null;
    }

    /** Forget the cached user so the next user() call reloads it */
    public function refreshUser(): void
    {
        $this->user = null;
        $this->resolved = false;
    }

    /** Use this user for the rest of the request (no session, no token) */
    public function setUser(?object $user): void
    {
        $this->user = $user;
        $this->resolved = true;
    }
}
