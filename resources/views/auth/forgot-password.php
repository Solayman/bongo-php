{: layout 'layouts.auth' :}
{: block 'title', __('Forgot password') :}
{: block 'subtitle' :}{{ __('We will email you a link to choose a new password.') }}{: end :}

{: block 'content' :}
<form method="POST" action="{{ route('password.email') }}" novalidate>
    {: csrf :}
    <div class="mb-3">
        <label for="email" class="form-label">{{ __('Email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus
               class="form-control {: error 'email' :} is-invalid {: end :}">
        {: error 'email' :} <div class="invalid-feedback">{{ $message }}</div> {: end :}
    </div>
    <button type="submit" class="btn btn-dark w-100">{{ __('Send reset link') }}</button>
    <div class="text-center mt-2"><a href="{{ route('login') }}" class="small">{{ __('Back to sign in') }}</a></div>
</form>
{: end :}
