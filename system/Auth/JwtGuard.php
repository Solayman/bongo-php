<?php
namespace System\Auth;

use System\Database\DB;
use System\Http\Request;
use System\Log\Log;
use System\Security\Jwt;
use System\Security\JwtException;

/**
 * Stateless API authentication with "Authorization: Bearer <token>".
 * Logout and refresh revoke the old token's id (jti) in the jwt_revocations table.
 */
class JwtGuard extends Guard
{
    protected ?array $claims = null;
    protected ?string $error = null;

    public function user(): ?object
    {
        if ($this->resolved) {
            return $this->user;
        }
        $this->resolved = true;

        $token = $this->token();
        if ($token === null) {
            $this->error = 'No token provided';
            return null;
        }

        try {
            $claims = Jwt::decode($token);
            if (($claims['guard'] ?? $this->name) !== $this->name) {
                throw new JwtException('Token belongs to another guard');
            }
            if ($this->isRevoked($claims['jti'] ?? '')) {
                throw new JwtException('Token has been revoked');
            }
            $this->user = $this->provider->findById($claims['sub'] ?? null);
            if ($this->user === null) {
                throw new JwtException('Token user no longer exists');
            }
            $this->claims = $claims;
        } catch (JwtException $e) {
            $this->error = $e->getMessage();
            $this->user = null;
        }

        return $this->user;
    }

    /** Bearer token from the request, if any */
    public function token(): ?string
    {
        $header = (string) Request::header('Authorization', '');
        if (preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $m[1];
        }
        return null;
    }

    /** Why the last user() call found no user ("Token has expired", ...) */
    public function error(): ?string
    {
        return $this->error;
    }

    public function claims(): ?array
    {
        $this->user();
        return $this->claims;
    }

    /** Check credentials and return a new token, or null */
    public function attempt(string $identifier, string $password): ?string
    {
        $user = $this->provider->findByCredentials($identifier, $password);
        return $user ? $this->login($user) : null;
    }

    /** Issue a token for this user */
    public function login(object $user, array $claims = []): string
    {
        $token = Jwt::encode($claims + ['sub' => (int) $user->id, 'guard' => $this->name]);
        $this->setUser($user);
        $this->claims = Jwt::decode($token);
        Log::info('API token issued for user {user_id}', ['user_id' => (int) $user->id, 'guard' => $this->name, 'ip' => \System\Http\Request::ip()]);
        return $token;
    }

    /** Revoke the current token */
    public function logout(): void
    {
        $claims = $this->claims();
        if ($claims) {
            $this->revoke($claims['jti'], (int) $claims['exp']);
            Log::info('API token revoked for user {user_id}', ['user_id' => $claims['sub'] ?? null]);
        }
        $this->setUser(null);
        $this->claims = null;
    }

    /**
     * Swap the current token (even if expired, within jwt.refresh_ttl) for a new one.
     * The old token is revoked. Returns null when it cannot be refreshed.
     */
    public function refresh(): ?string
    {
        $token = $this->token();
        if ($token === null) {
            $this->error = 'No token provided';
            return null;
        }

        try {
            $claims = Jwt::decode($token, true);
        } catch (JwtException $e) {
            $this->error = $e->getMessage();
            return null;
        }

        $windowEnds = ($claims['iat'] ?? 0) + (int) config('jwt.refresh_ttl', 20160) * 60;
        if (time() > $windowEnds) {
            $this->error = 'Token can no longer be refreshed';
            return null;
        }
        if ($this->isRevoked($claims['jti'] ?? '') || ($claims['guard'] ?? $this->name) !== $this->name) {
            $this->error = 'Token has been revoked';
            return null;
        }

        $user = $this->provider->findById($claims['sub'] ?? null);
        if (!$user) {
            $this->error = 'Token user no longer exists';
            return null;
        }

        $this->revoke($claims['jti'], max((int) $claims['exp'], $windowEnds));
        $this->refreshUser();
        return $this->login($user);
    }

    protected function revoke(string $jti, int $expiresAt): void
    {
        DB::table('jwt_revocations')->insert(['jti' => $jti, 'expires_at' => date('Y-m-d H:i:s', $expiresAt)]);

        // Occasionally remove revocations of tokens that have expired anyway
        if (random_int(1, 50) === 1) {
            DB::table('jwt_revocations')->where('expires_at', '<', date('Y-m-d H:i:s'))->delete();
        }
    }

    protected function isRevoked(string $jti): bool
    {
        return $jti === '' || DB::table('jwt_revocations')->where('jti', $jti)->count() > 0;
    }
}
