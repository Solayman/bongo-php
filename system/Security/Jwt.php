<?php

namespace System\Security;

/**
 * JSON Web Tokens signed with HMAC-SHA256 (HS256).
 *
 *   $token  = Jwt::encode(['sub' => 5], 3600);     // valid for an hour
 *   $claims = Jwt::decode($token);                 // throws JwtException if invalid
 *
 * The secret is JWT_SECRET from .env, or a key derived from APP_KEY.
 * Only HS256 is accepted, so a token claiming "alg": "none" (or anything else) is rejected.
 */
class Jwt
{
    public const ALGORITHM = 'HS256';

    /**
     * @param array    $claims  Your claims (sub, role, ...). iat, nbf, exp, jti and iss are added.
     * @param int|null $ttl     Seconds until it expires (default: config jwt.ttl minutes)
     */
    public static function encode(array $claims, ?int $ttl = null, ?string $secret = null): string
    {
        $now = time();
        $ttl ??= (int) config('jwt.ttl', 60) * 60;

        $claims += [
            'iss' => (string) config('jwt.issuer', config('app.url')),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];

        $header = self::base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => self::ALGORITHM]));
        $payload = self::base64UrlEncode(json_encode($claims, JSON_UNESCAPED_SLASHES));
        $signature = self::base64UrlEncode(hash_hmac('sha256', "$header.$payload", $secret ?? self::secret(), true));

        return "$header.$payload.$signature";
    }

    /**
     * Verify and return the claims.
     *
     * @param bool $allowExpired  Accept an expired token (used to refresh within the refresh window)
     * @throws JwtException
     */
    public static function decode(string $token, bool $allowExpired = false, ?string $secret = null): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            throw new JwtException('Malformed token');
        }
        [$header64, $payload64, $signature64] = $parts;

        $header = json_decode(self::base64UrlDecode($header64), true);
        if (!is_array($header) || ($header['alg'] ?? null) !== self::ALGORITHM) {
            throw new JwtException('Unsupported token algorithm');
        }

        $expected = hash_hmac('sha256', "$header64.$payload64", $secret ?? self::secret(), true);
        if (!hash_equals($expected, self::base64UrlDecode($signature64))) {
            throw new JwtException('Invalid token signature');
        }

        $claims = json_decode(self::base64UrlDecode($payload64), true);
        if (!is_array($claims)) {
            throw new JwtException('Malformed token payload');
        }

        $now = time();
        $leeway = (int) config('jwt.leeway', 30);

        if (isset($claims['nbf']) && $claims['nbf'] > $now + $leeway) {
            throw new JwtException('Token is not valid yet');
        }
        if (isset($claims['iat']) && $claims['iat'] > $now + $leeway) {
            throw new JwtException('Token was issued in the future');
        }
        if (!$allowExpired && isset($claims['exp']) && $claims['exp'] < $now - $leeway) {
            throw new JwtException('Token has expired');
        }

        $issuer = config('jwt.issuer', config('app.url'));
        if ($issuer && isset($claims['iss']) && $claims['iss'] !== $issuer) {
            throw new JwtException('Token was issued by another application');
        }

        return $claims;
    }

    protected static function secret(): string
    {
        $secret = (string) env('JWT_SECRET', '');
        return $secret !== '' ? $secret : Key::derive('jwt');
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        $decoded = base64_decode(strtr($data, '-_', '+/') . str_repeat('=', (4 - strlen($data) % 4) % 4), true);
        return $decoded === false ? '' : $decoded;
    }
}
