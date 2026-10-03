<?php
namespace System\Http\Middleware;

use System\Auth\Auth;
use System\Http\Request;

/**
 * "verified:customer,shop.verification.notice": a signed-in user whose email_verified_at is
 * empty is sent to the notice page (API requests get 403 JSON).
 */
class EnsureEmailIsVerified
{
    public function handle(string $guard = '', string $noticeRoute = 'verification.notice'): void
    {
        $user = $guard === '' ? Auth::user() : Auth::guard($guard)->user();
        if (!$user || !empty($user->email_verified_at)) {
            return;
        }
        if (Request::wantsJson()) {
            response(['message' => 'Your email address is not verified.'], 'json', 403);
        }
        redirect(route($noticeRoute));
    }
}
