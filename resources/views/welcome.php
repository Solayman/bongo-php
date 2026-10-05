<!DOCTYPE html>
<html lang="{{ locale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Bongo') }} · Modern Lightweight PHP Framework</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #0b0f19;
            --surface-color: #111827;
            --surface-border: #1f2937;
            --text-primary: #f9fafb;
            --text-secondary: #9ca3af;
            --accent-brand: #6366f1;
            --accent-brand-hover: #4f46e5;
            --accent-emerald: #10b981;
        }
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
            background-image:
                radial-gradient(at 10% 20%, rgba(99, 102, 241, 0.12) 0px, transparent 50%),
                radial-gradient(at 90% 80%, rgba(16, 185, 129, 0.08) 0px, transparent 50%);
        }
        .navbar-brand {
            font-weight: 700;
            font-size: 1.25rem;
            color: #fff;
            letter-spacing: -0.5px;
        }
        .badge-version {
            background: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border: 1px solid rgba(99, 102, 241, 0.3);
            font-weight: 600;
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
            border-radius: 9999px;
        }
        .hero-title {
            font-size: clamp(2.5rem, 5vw, 4.25rem);
            font-weight: 800;
            line-height: 1.1;
            letter-spacing: -1.5px;
        }
        .hero-title .gradient-text {
            background: linear-gradient(135deg, #a5b4fc 0%, #6366f1 50%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-lead {
            color: var(--text-secondary);
            font-size: 1.15rem;
            line-height: 1.6;
            max-width: 640px;
        }
        .feature-card {
            background: rgba(17, 24, 39, 0.75);
            backdrop-filter: blur(12px);
            border: 1px solid var(--surface-border);
            border-radius: 16px;
            padding: 24px;
            transition: transform 0.2s ease, border-color 0.2s ease;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-3px);
            border-color: rgba(99, 102, 241, 0.4);
        }
        .feature-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            margin-bottom: 16px;
        }
        .terminal-box {
            background: #030712;
            border: 1px solid var(--surface-border);
            border-radius: 14px;
            padding: 18px 22px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.9rem;
            color: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .btn-brand {
            background: var(--accent-brand);
            color: #fff;
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 10px;
            border: none;
            transition: background 0.2s ease;
        }
        .btn-brand:hover {
            background: var(--accent-brand-hover);
            color: #fff;
        }
        .btn-outline-custom {
            border: 1px solid var(--surface-border);
            color: #e5e7eb;
            background: rgba(17, 24, 39, 0.6);
            font-weight: 600;
            padding: 10px 22px;
            border-radius: 10px;
            transition: all 0.2s ease;
        }
        .btn-outline-custom:hover {
            background: var(--surface-border);
            color: #fff;
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <nav class="navbar navbar-expand-lg border-bottom border-dark py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2" href="<?= url('/') ?>">
                <i class="bi bi-layers-fill text-primary fs-4"></i>
                <span>{{ config('app.name', 'Bongo') }}</span>
                <span class="badge-version ms-2">v{{ $frameworkVersion }}</span>
            </a>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="https://github.com/Solayman/bongo-php" target="_blank" rel="noopener" class="btn btn-sm btn-outline-custom d-none d-sm-inline-flex align-items-center gap-2">
                    <i class="bi bi-github"></i>
                    <span>GitHub</span>
                </a>
                @if(\System\Auth\Auth::check())
                    <a href="<?= url('/dashboard') ?>" class="btn btn-sm btn-brand d-inline-flex align-items-center gap-2">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                @else
                    <a href="<?= url('/login') ?>" class="btn btn-sm btn-brand d-inline-flex align-items-center gap-2">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>Sign in</span>
                    </a>
                @endif
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container my-auto py-5">
        <div class="row align-items-center g-5 py-4">
            <div class="col-lg-7">
                <div class="d-inline-flex align-items-center gap-2 badge-version mb-3">
                    <i class="bi bi-lightning-charge-fill text-warning"></i>
                    <span>Pure PHP 8.0+ &middot; Zero Mandatory Dependencies</span>
                </div>
                <h1 class="hero-title mb-4">
                    The modern, blazing-fast <span class="gradient-text">PHP framework</span>.
                </h1>
                <p class="hero-lead mb-4">
                    Bongo is built from the ground up for speed, elegance, and extreme developer productivity. Ultra-lightweight runtime (~600 KB), instantaneous boot times (7–13 ms), and zero mandatory external packages.
                </p>

                <div class="terminal-box mb-4">
                    <div>
                        <span class="text-secondary">$</span> <span class="text-info">php bongo</span> serve
                    </div>
                    <span class="text-secondary small">Running on http://127.0.0.1:8000</span>
                </div>

                <div class="d-flex align-items-center flex-wrap gap-3">
                    @if(\System\Auth\Auth::check())
                        <a href="<?= url('/dashboard') ?>" class="btn btn-brand btn-lg d-inline-flex align-items-center gap-2">
                            <i class="bi bi-speedometer2"></i>
                            <span>Go to Dashboard</span>
                        </a>
                    @else
                        <a href="<?= url('/login') ?>" class="btn btn-brand btn-lg d-inline-flex align-items-center gap-2">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <span>Sign in to Dashboard</span>
                        </a>
                    @endif
                    <a href="https://github.com/Solayman/bongo-php#readme" target="_blank" rel="noopener" class="btn btn-outline-custom btn-lg d-inline-flex align-items-center gap-2">
                        <i class="bi bi-book"></i>
                        <span>Documentation</span>
                    </a>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="feature-card">
                            <div class="feature-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-lightning-fill"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Ultra High Performance</h5>
                            <p class="text-secondary small mb-0">
                                Consistently serves requests in 7–13 ms with under 0.8 MB memory usage per request. Built-in route, view, and config compilers.
                            </p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="feature-card">
                            <div class="feature-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-shield-lock-fill"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Enterprise Security</h5>
                            <p class="text-secondary small mb-0">
                                Native CSRF protection, secure cookie sessions, XSS HTML sanitization, SQL injection prevention, rate limiting, and password hashing.
                            </p>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="feature-card">
                            <div class="feature-icon bg-info bg-opacity-10 text-info">
                                <i class="bi bi-database-fill-gear"></i>
                            </div>
                            <h5 class="fw-bold mb-2">Multi-Database & Queues</h5>
                            <p class="text-secondary small mb-0">
                                Native support for MySQL, PostgreSQL, and SQLite with Eloquent-style ORM, migrations, seeds, and Redis / Database background queues.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="border-top border-dark py-4 mt-auto">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3 text-secondary small">
            <div>
                <span>PHP <?= explode('-', $phpVersion)[0] ?></span> &middot;
                <span>Bongo v<?= $frameworkVersion ?></span>
            </div>
            <div>
                Crafted with pride for high-performance PHP web craftsmanship.
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
