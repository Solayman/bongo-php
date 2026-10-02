@extends('layouts.shop')
@set('title', __('Sign in'))

@section('content')
<div class="mx-auto" style="max-width: 420px;">
    <div class="card"><div class="card-body p-4">
        <h1 class="h4 mb-3">{{ __('Sign in to your account') }}</h1>
        <form method="POST" action="{{ route('shop.login') }}" novalidate>
            @csrf
            <div class="mb-3">
                <label for="email" class="form-label">{{ __('Email') }}</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus class="form-control @error('email') is-invalid @enderror">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">{{ __('Password') }}</label>
                <input type="password" id="password" name="password" autocomplete="current-password" class="form-control @error('password') is-invalid @enderror">
                @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-dark w-100">{{ __('Sign in') }}</button>
        </form>
        <div class="d-flex justify-content-between small mt-3">
            <a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            <a href="{{ route('shop.register') }}">{{ __('Create an account') }}</a>
        </div>
        @if(config('app.env') !== 'production')
            <p class="text-secondary small text-center mt-3 mb-0">{{ __('Demo login') }}: rahim1@example.com / password</p>
        @endif
    </div></div>
</div>
@endsection
