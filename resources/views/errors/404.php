<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $status }} · {{ $message }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="d-flex align-items-center justify-content-center p-3" style="min-height: 100vh;">
    <div class="text-center">
        <div class="display-4 fw-semibold">{{ $status }}</div>
        <p class="lead mb-1">{{ $message }}</p>
        <p class="text-secondary">We couldn't find that page.</p>
        <a href="<?= url('/') ?>" class="btn btn-dark">Go to home page</a>
    </div>
</div>
</body>
</html>
