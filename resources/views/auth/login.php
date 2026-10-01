<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign in · {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= asset('asset/css/app.css') ?>" rel="stylesheet">
</head>
<body>
<div class="auth-page">
    <div class="card shadow-sm" style="width: 100%; max-width: 400px;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <i class="bi bi-boxes fs-1"></i>
                <h1 class="h4 mt-2 mb-0">{{ config('app.name') }}</h1>
                <p class="text-secondary small mb-0">Sign in to continue</p>
            </div>

            <?php if ($message = flash('success')): ?>
                <div class="alert alert-success py-2 small">{{ $message }}</div>
            <?php endif; ?>
            <?php if ($message = flash('error')): ?>
                <div class="alert alert-warning py-2 small">{{ $message }}</div>
            <?php endif; ?>

            <form method="POST" action="<?= url('/login') ?>" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" autocomplete="username" autofocus
                           class="form-control <?= error('email') ? 'is-invalid' : '' ?>">
                    <div class="invalid-feedback">{{ error('email') }}</div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password"
                           class="form-control <?= error('password') ? 'is-invalid' : '' ?>">
                    <div class="invalid-feedback">{{ error('password') }}</div>
                </div>
                <button type="submit" class="btn btn-dark w-100">Sign in</button>
            </form>

            <?php if (config('app.env') !== 'production'): ?>
                <p class="text-secondary small text-center mt-3 mb-0">Demo login: admin@example.com / password</p>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
