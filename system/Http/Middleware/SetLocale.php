<?php
namespace System\Http\Middleware;

use System\Http\Request;
use System\Http\Session;
use System\Translation\Lang;

/**
 * Chooses the language for the request (web group, after "session"):
 *   1. ?lang=bn in the URL (remembered in the session)
 *   2. the language saved in the session
 *   3. the browser's Accept-Language header
 *   4. APP_LOCALE
 */
class SetLocale
{
    public function handle(): void
    {
        $supported = array_keys(Lang::locales());

        $requested = Request::query('lang');
        if ($requested !== null && in_array($requested, $supported, true)) {
            Session::put('locale', $requested);
        }

        $locale = Session::get('locale') ?? $this->fromBrowser($supported);
        if ($locale !== null && in_array($locale, $supported, true)) {
            Lang::setLocale($locale);
        }
    }

    /** Best supported match for Accept-Language ("bn-BD,bn;q=0.9,en;q=0.8") */
    protected function fromBrowser(array $supported): ?string
    {
        $header = (string) Request::header('Accept-Language', '');
        $candidates = [];
        foreach (explode(',', $header) as $i => $part) {
            if (!preg_match('/^\s*([a-zA-Z]{1,8})(?:-[a-zA-Z0-9]{1,8})*\s*(?:;\s*q=([0-9.]+))?/', $part, $m)) continue;
            $candidates[] = [strtolower($m[1]), isset($m[2]) ? (float) $m[2] : 1.0, $i];
        }
        usort($candidates, fn($a, $b) => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
        foreach ($candidates as [$lang]) {
            if (in_array($lang, $supported, true)) return $lang;
        }
        return null;
    }
}
