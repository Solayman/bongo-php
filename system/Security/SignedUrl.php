<?php
namespace System\Security;

/**
 * Links that can't be changed or used after they expire (email verification, unsubscribe ...):
 *
 *   $link = signed_route('shop.verification.verify', ['id' => $user->id], 60);
 *   Route::get(...)->middleware('signed');      // 403 when the link was changed or is too old
 *
 * The signature is an HMAC of the whole URL (with ?expires=) under a key derived from APP_KEY.
 */
class SignedUrl
{
    public static function make(string $url, int $minutes = 60): string
    {
        $url .= (str_contains($url, '?') ? '&' : '?') . 'expires=' . (time() + $minutes * 60);
        return $url . '&signature=' . self::signature($url);
    }

    /** Is this URL (default: the current request) unchanged and not expired? */
    public static function valid(?string $url = null): bool
    {
        $url ??= \System\Http\Request::fullUrl();
        if (!preg_match('/^(.*)[?&]signature=([a-f0-9]{64})$/', $url, $m)) {
            return false;
        }
        [$unsigned, $signature] = [$m[1], $m[2]];
        if (!hash_equals(self::signature($unsigned), $signature)) {
            return false;
        }
        parse_str((string) parse_url($unsigned, PHP_URL_QUERY), $query);
        return isset($query['expires']) && (int) $query['expires'] >= time();
    }

    /** Signed over the path and query only, so http/https or a proxy's host does not matter */
    protected static function signature(string $url): string
    {
        $parts = parse_url($url);
        $target = ($parts['path'] ?? '/') . (isset($parts['query']) ? '?' . $parts['query'] : '');
        return hash_hmac('sha256', $target, Key::derive('signed-url'));
    }
}
