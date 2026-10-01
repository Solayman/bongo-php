<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('asset/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand topbar">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= url('/') ?>"><i class="bi bi-boxes me-2"></i>{{ config('app.name') }}</a>
        <div class="navbar-nav ms-auto align-items-center gap-2">
            <?php if (auth()): ?>
                <a class="nav-link <?= is_active('/home') ?>" href="<?= url('/home') ?>">Home</a>
                <a class="nav-link <?= is_active('/system') ?>" href="<?= url('/system') ?>">System status</a>
                <form method="POST" action="<?= url('/logout') ?>" class="m-0">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-outline-light" type="submit">Sign out</button>
                </form>
            <?php else: ?>
                <a class="nav-link" href="<?= url('/login') ?>">Sign in</a>
            <?php endif; ?>
        </div>
    </div>
</nav>

<main class="container py-4">
    @include('partials.flash')
    @yield('content')
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
