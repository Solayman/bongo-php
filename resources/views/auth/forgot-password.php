@extends('layouts.auth')
@set('title', __('Forgot password'))
@section('subtitle'){{ __('We will email you a link to choose a new password.') }}@endsection

@section('content')
<form method="POST" action="{{ route('password.email') }}" novalidate>
    @csrf
    <div class="mb-3">
        <label for="email" class="form-label">{{ __('Email') }}</label>
        <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="email" autofocus
               class="form-control @error('email') is-invalid @enderror">
        @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    <button type="submit" class="btn btn-dark w-100">{{ __('Send reset link') }}</button>
    <div class="text-center mt-2"><a href="{{ route('login') }}" class="small">{{ __('Back to sign in') }}</a></div>
</form>
@endsection
