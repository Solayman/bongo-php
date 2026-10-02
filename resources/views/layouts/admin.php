<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('asset/css/admin.css') ?>" rel="stylesheet">
</head>
<body>
<div class="d-flex">
    @include('partials.sidebar')

    <div class="flex-grow-1 min-vw-0">
        <header class="topbar d-flex align-items-center justify-content-between px-4">
            <h1 class="h5 mb-0">@yield('title')</h1>
            <div class="d-flex align-items-center gap-3">
                <a href="<?= url('/profile') ?>" class="text-body text-decoration-none small">
                    <i class="bi bi-person-circle me-1"></i>{{ auth()->name }}
                </a>
                <form method="POST" action="<?= url('/logout') ?>" class="m-0">
                    @csrf
                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                        <i class="bi bi-box-arrow-right me-1"></i>Sign out
                    </button>
                </form>
            </div>
        </header>

        <main class="p-4">
            @include('partials.flash')
            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
@stack('scripts')
</body>
</html>
