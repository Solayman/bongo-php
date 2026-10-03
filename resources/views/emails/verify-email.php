<!DOCTYPE html>
<html lang="{{ locale() }}">
<body style="margin:0;padding:24px;background:#f5f6f8;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="480" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e5e7eb;border-radius:8px;">
    <tr><td style="padding:28px;">
        <h1 style="font-size:20px;margin:0 0 16px;">{{ config('app.name') }}</h1>
        <p style="margin:0 0 12px;">{{ __('Hello :name,', ['name' => $user->name ?? '']) }}</p>
        <p style="margin:0 0 20px;">{{ __('Please confirm that this is your email address.') }}</p>
        <p style="margin:0 0 20px;"><a href="{{ $link }}" style="background:#111827;color:#ffffff;padding:10px 18px;border-radius:6px;text-decoration:none;display:inline-block;">{{ __('Verify email address') }}</a></p>
        <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">{{ __('This link works for 60 minutes.') }}</p>
        <p style="margin:0;font-size:13px;color:#6b7280;">{{ __('If you did not create an account, you can ignore this email.') }}</p>
    </td></tr>
</table>
</td></tr></table>
</body>
</html>
