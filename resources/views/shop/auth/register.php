{: layout 'layouts.shop' :}
{: block 'title', __('Create an account') :}

{: block 'content' :}
<div class="mx-auto" style="max-width: 460px;">
    <div class="card"><div class="card-body p-4">
        <h1 class="h4 mb-3">{{ __('Create an account') }}</h1>
        <form method="POST" action="{{ route('shop.register') }}" novalidate>
            {: csrf :}
            <div class="mb-3">
                <label for="name" class="form-label">{{ __('Name') }}</label>
                <input id="name" name="name" value="{{ old('name') }}" autocomplete="name" autofocus class="form-control {: error 'name' :} is-invalid {: end :}">
                {: error 'name' :} <div class="invalid-feedback">{{ $message }}</div> {: end :}
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" class="form-control {: error 'email' :} is-invalid {: end :}">
                {: error 'email' :} <div class="invalid-feedback">{{ $message }}</div> {: end :}
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" autocomplete="new-password" class="form-control {: error 'password' :} is-invalid {: end :}">
                {: error 'password' :} <div class="invalid-feedback">{{ $message }}</div> {: end :}
                <div class="form-text">{{ __('At least 8 characters.') }}</div>
            </div>
            <div class="mb-3">
                <label for="password_confirmation" class="form-label">{{ __('Confirm password') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
            </div>
            <button type="submit" class="btn btn-dark w-100">{{ __('Create account') }}</button>
        </form>
        <div class="small text-center mt-3"><a href="{{ route('shop.login') }}">{{ __('Already have an account? Sign in') }}</a></div>
    </div></div>
</div>
{: end :}
