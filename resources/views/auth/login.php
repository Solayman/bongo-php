<!DOCTYPE html>
<html lang="{{ locale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('asset/css/admin.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-bag-check-fill fs-1"></i>
                <h1 class="h4 mt-2 mb-0">{{ config('app.name') }}</h1>
                <p class="text-secondary small mb-0">{{ __('Sign in to the admin dashboard') }}</p>
            </div>

            @if($message = flash('success'))
                <div class="alert alert-success py-2 small">{{ $message }}</div>
            @endif
            @if($message = flash('error'))
                <div class="alert alert-warning py-2 small">{{ $message }}</div>
            @endif

            <form method="POST" action="<?= url('/login') ?>" novalidate>
                @csrf
                <div class="mb-3">
                    <label for="email" class="form-label">{{ __('Email') }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus
                           class="form-control <?= error('email') ? 'is-invalid' : '' ?>">
                    <div class="invalid-feedback">{{ error('email') }}</div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">{{ __('Password') }}</label>
                    <input type="password" id="password" name="password" autocomplete="current-password"
                           class="form-control <?= error('password') ? 'is-invalid' : '' ?>">
                    <div class="invalid-feedback">{{ error('password') }}</div>
                </div>
                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
                    <label class="form-check-label" for="remember">{{ __('Remember me') }}</label>
                </div>
                <button type="submit" class="btn btn-dark w-100">{{ __('Sign in') }}</button>
                <div class="text-center mt-2">
                    <a href="{{ route('password.request') }}" class="small">{{ __('Forgot your password?') }}</a>
                </div>
            </form>

            <div class="text-center small mt-3">
                @foreach(System\Translation\Lang::locales() as $code => $label)
                    <a href="?lang={{ $code }}" lang="{{ $code }}" class="mx-1 @if($code === locale()) fw-semibold text-body @endif">{{ $label }}</a>
                @endforeach
            </div>
            @if(config('app.env') !== 'production')
                <p class="text-secondary small text-center mt-3 mb-0">{{ __('Demo login') }}: admin@example.com / password</p>
            @endif
        </div>
    </div>
</div>
</body>
</html>
