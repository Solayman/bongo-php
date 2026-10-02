<!DOCTYPE html>
<html lang="{{ locale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="{{ asset('asset/css/admin.css') }}" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="card shadow-sm" style="width: 100%; max-width: 420px;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-bag-check-fill fs-1"></i>
                <h1 class="h4 mt-2 mb-0">{{ config('app.name') }}</h1>
                <p class="text-secondary small mb-0">@yield('subtitle')</p>
            </div>
            @if($message = flash('success'))
                <div class="alert alert-success py-2 small">{{ $message }}</div>
            @endif
            @yield('content')
            <div class="text-center small mt-3">
                @foreach(System\Translation\Lang::locales() as $code => $label)
                    <a href="?lang={{ $code }}" lang="{{ $code }}" class="mx-1 @if($code === locale()) fw-semibold text-body @endif">{{ $label }}</a>
                @endforeach
            </div>
        </div>
    </div>
</div>
</body>
</html>
