@extends('layouts.shop')
@set('title', __('Verify your email address'))

@section('content')
<div class="mx-auto text-center" style="max-width: 460px;">
    <div class="card"><div class="card-body p-4">
        <i class="bi bi-envelope-check display-5 text-secondary"></i>
        <h1 class="h4 mt-2 mb-3">{{ __('Verify your email address') }}</h1>
        <p class="text-secondary">{{ __('We sent a link to :email. Click it to finish setting up your account, then you can check out.', ['email' => $email]) }}</p>
        <form method="POST" action="{{ route('shop.verification.send') }}" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-outline-dark">{{ __('Send the link again') }}</button>
        </form>
    </div></div>
</div>
@endsection
