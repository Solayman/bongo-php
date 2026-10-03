<?php
namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Models\User;
use System\Http\Request;

class VerifyEmailController extends Controller
{
    /** "Check your email" page, with a button to send the link again */
    public function notice(Request $request)
    {
        $user = User::findOrFail($request->user('customer')->id);
        if ($user->hasVerifiedEmail()) {
            redirect(route('shop.index'));
        }
        return $this->view('shop.verify-email', ['email' => $user->email]);
    }

    /** The link from the email (route middleware "signed" already checked it is unchanged and fresh) */
    public function verify(Request $request, $id)
    {
        $user = User::findOrFail($id);
        if (!hash_equals(sha1((string) $user->email), (string) $request->hash)) {
            abort(403, 'This link is for a different email address.');
        }
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
        flash('success', __('Your email address is verified. Thank you!'));
        redirect(route('shop.index'));
    }

    public function resend(Request $request)
    {
        $user = User::findOrFail($request->user('customer')->id);
        if (!$user->hasVerifiedEmail()) {
            $user->sendEmailVerification();
        }
        flash('success', __('A new verification link has been sent to :email.', ['email' => $user->email]));
        redirect(route('shop.verification.notice'));
    }
}
