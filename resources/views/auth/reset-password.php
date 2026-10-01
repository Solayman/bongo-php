@extends('layouts.auth')
@set('title', __('Reset password'))
@section('subtitle'){{ __('Choose a new password.') }}@endsection

@section('content')
@if(!$valid)
    <div class="alert alert-warning small">{{ __('This reset link is invalid or has expired.') }}</div>
    <a href="{{ route('password.request') }}" class="btn btn-dark w-100">{{ __('Request a new link') }}</a>
@else
<form method="POST" action="{{ route('password.update') }}" novalidate>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <input type="hidden" name="email" value="{{ $email }}">
    <div class="mb-3">
        <label class="form-label">{{ __('Email') }}</label>
        <input type="email" class="form-control" value="{{ $email }}" disabled>
    </div>
    <div class="mb-3">
        <label for="password" class="form-label">{{ __('New password') }}</label>
        <input type="password" id="password" name="password" autocomplete="new-password" autofocus
               class="form-control @error('password') is-invalid @enderror">
        @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
        <div class="form-text">{{ __('At least 8 characters.') }}</div>
    </div>
    <div class="mb-3">
        <label for="password_confirmation" class="form-label">{{ __('Confirm new password') }}</label>
        <input type="password" id="password_confirmation" name="password_confirmation" autocomplete="new-password" class="form-control">
    </div>
    <button type="submit" class="btn btn-dark w-100">{{ __('Reset password') }}</button>
</form>
@endif
@endsection
