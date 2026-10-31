<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ config('app.name', 'Bongo') }} · Modern Lightweight PHP Framework</title>
    <style>
        :root {
            --bg-base: #020617;
            --bg-surface: #0b1120;
            --bg-card: #0f172a;
            --bg-card-hover: #1e293b;
            --border: #1e293b;
            --border-highlight: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --text-muted: #64748b;
            --emerald-500: #10b981;
            --emerald-400: #34d399;
            --emerald-950: #064e3b;
            --font-sans: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            --font-mono: 'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-primary);
            font-family: var(--font-sans);
            line-height: 1.6;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background-image: 
                radial-gradient(ellipse at 20% 0%, rgba(16, 185, 129, 0.12) 0%, transparent 60%),
                radial-gradient(ellipse at 80% 100%, rgba(56, 189, 248, 0.08) 0%, transparent 50%);
            -webkit-font-smoothing: antialiased;
        }

        .container {
            width: 100%;
            max-width: 1240px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        /* Navbar */
        nav {
            border-bottom: 1px solid var(--border);
            background: rgba(11, 17, 32, 0.8);
            backdrop-filter: blur(12px);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .nav-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 4.25rem;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            color: #fff;
            font-weight: 800;
            font-size: 1.35rem;
            letter-spacing: -0.025em;
        }
        .brand-icon {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.625rem;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--font-mono);
            font-weight: 800;
            font-size: 1.15rem;
            box-shadow: 0 0 15px rgba(16, 185, 129, 0.35);
        }
        .badge-version {
            font-family: var(--font-mono);
            font-size: 0.725rem;
            font-weight: 600;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.12);
            color: var(--emerald-400);
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            list-style: none;
        }
        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: color 0.15s ease;
            display: flex;
            align-items: center;
            gap: 0.35rem;
        }
        .nav-links a:hover {
            color: #fff;
        }
        .nav-links a.active {
            color: var(--emerald-400);
        }

        /* Hero */
        .hero {
            padding: 4rem 0 3rem;
            text-align: center;
        }
        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.35rem 0.85rem;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: var(--emerald-400);
            font-size: 0.8rem;
            font-weight: 600;
            font-family: var(--font-mono);
            margin-bottom: 1.75rem;
        }
        .hero-title {
            font-size: clamp(2.25rem, 5vw, 3.75rem);
            font-weight: 800;
            letter-spacing: -0.035em;
            line-height: 1.15;
            margin-bottom: 1.25rem;
        }
        .text-gradient {
            background: linear-gradient(135deg, #f8fafc 0%, #34d399 50%, #38bdf8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero-desc {
            font-size: 1.125rem;
            color: var(--text-secondary);
            max-width: 680px;
            margin: 0 auto 2.25rem;
            line-height: 1.6;
        }

        /* Action Buttons */
        .cta-group {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 3.5rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.35rem;
            border-radius: 0.75rem;
            font-size: 0.9rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: var(--emerald-500);
            color: #020617;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.35);
        }
        .btn-primary:hover {
            background: var(--emerald-400);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.45);
        }
        .btn-secondary {
            background: var(--bg-card);
            border-color: var(--border);
            color: var(--text-primary);
        }
        .btn-secondary:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-highlight);
            color: #fff;
        }

        /* Demo & Code Section */
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        @media (min-width: 900px) {
            .grid-2 {
                grid-template-columns: 1.15fr 0.85fr;
            }
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: border-color 0.2s ease;
        }
        .card:hover {
            border-color: var(--border-highlight);
        }
        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        .card-title {
            font-size: 1.05rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Code box */
        .code-box {
            background: #030712;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
            font-family: var(--font-mono);
            font-size: 0.825rem;
            color: #e2e8f0;
            overflow-x: auto;
            margin-bottom: 1rem;
        }
        .code-keyword { color: #f472b6; }
        .code-function { color: #60a5fa; }
        .code-string { color: #34d399; }
        .code-comment { color: #64748b; }

        /* Working example live viewer */
        .live-badge {
            font-family: var(--font-mono);
            font-size: 0.7rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0.375rem;
            background: rgba(16, 185, 129, 0.15);
            color: var(--emerald-400);
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-weight: 600;
        }
        .response-box {
            background: #030712;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
            padding: 1rem;
            font-family: var(--font-mono);
            font-size: 0.775rem;
            color: #94a3b8;
            min-height: 140px;
            max-height: 220px;
            overflow-y: auto;
            white-space: pre-wrap;
        }

        /* Steps list */
        .steps-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .step-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        .step-num {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 9999px;
            background: rgba(16, 185, 129, 0.15);
            color: var(--emerald-400);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-family: var(--font-mono);
            font-weight: 700;
            shrink-0: 0;
            margin-top: 0.1rem;
        }
        .step-item code {
            font-family: var(--font-mono);
            background: #030712;
            padding: 0.15rem 0.4rem;
            border-radius: 0.375rem;
            border: 1px solid var(--border);
            color: #38bdf8;
            font-size: 0.8rem;
        }

        /* Resource Grid */
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 1.25rem;
            margin-bottom: 4rem;
        }
        .resource-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 0.875rem;
            padding: 1.5rem;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s ease;
        }
        .resource-card:hover {
            border-color: var(--emerald-500);
            transform: translateY(-2px);
            background: var(--bg-card-hover);
        }
        .resource-title {
            font-weight: 700;
            font-size: 0.95rem;
            margin-bottom: 0.5rem;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .resource-desc {
            font-size: 0.825rem;
            color: var(--text-secondary);
            line-height: 1.5;
        }

        /* Footer */
        footer {
            border-top: 1px solid var(--border);
            padding: 2.5rem 0;
            margin-top: auto;
            background: var(--bg-surface);
            font-size: 0.8rem;
            color: var(--text-muted);
        }
        .footer-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1.5rem;
        }
        .footer-meta {
            font-family: var(--font-mono);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
    </style>
</head>
<body>

    <!-- Navigation Header -->
    <nav>
        <div class="container nav-content">
            <a href="/" class="brand">
                <div class="brand-icon">B</div>
                <span>Bongo</span>
                <span class="badge-version">v{{ $frameworkVersion }}</span>
            </a>
            <ul class="nav-links">
                <li><a href="https://bongo.nobotech.org" target="_blank" rel="noopener">Documentation ↗</a></li>
                <li><a href="https://bongo.nobotech.org/packages" target="_blank" rel="noopener">50 Packages ↗</a></li>
                <li><a href="https://bongo.nobotech.org/benchmarks" target="_blank" rel="noopener">Benchmarks ↗</a></li>
                <li><a href="https://github.com/Solayman/bongo-php" target="_blank" rel="noopener">GitHub ↗</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <header class="hero">
        <div class="container">
            <div class="hero-pill">
                <span>⚡ Pure PHP 8.0+ &middot; Zero Mandatory Dependencies</span>
            </div>
            <h1 class="hero-title">
                The modern, blazing-fast <br>
                <span class="text-gradient">PHP MVC framework</span>.
            </h1>
            <p class="hero-desc">
                Hello, <strong>{{ $name }}</strong>! Your new Bongo application is running smoothly. Engineered from the ground up for extreme speed, developer ergonomics, and instantaneous deployment with sub-2ms response times.
            </p>

            <div class="cta-group">
                <a href="https://bongo.nobotech.org" target="_blank" rel="noopener" class="btn btn-primary">
                    <span>Explore Documentation</span>
                    <span>&rarr;</span>
                </a>
                <a href="https://bongo.nobotech.org/packages" target="_blank" rel="noopener" class="btn btn-secondary">
                    <span>50 Official Packages</span>
                </a>
                <a href="https://github.com/Solayman/bongo-php/blob/master/DOCUMENTATION.md" target="_blank" rel="noopener" class="btn btn-secondary">
                    <span>Git Repo DOCUMENTATION.md</span>
                </a>
            </div>
        </div>
    </header>

    <!-- Main Content & Live Working Example -->
    <main class="container">
        <div class="grid-2">
            <!-- Left Card: Live Working Core Framework Example -->
            <div class="card">
                <div>
                    <div class="card-header">
                        <div class="card-title">
                            <span>⚡ Live Working Core Example</span>
                        </div>
                        <span id="timingBadge" class="live-badge">Route &middot; /api/demo</span>
                    </div>

                    <p style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 1rem;">
                        This interactive demo executes directly through Bongo's router, middleware pipeline, and controller dispatch:
                    </p>

                    <div class="code-box">
                        <span class="code-comment">// routes/web.php</span><br>
                        <span class="code-keyword">Route</span>::<span class="code-function">get</span>(<span class="code-string">'/api/demo'</span>, [<span class="code-keyword">HomeController</span>::<span class="code-keyword">class</span>, <span class="code-string">'demo'</span>]);
                    </div>

                    <div class="response-box" id="responseBox">Click "Dispatch Live Request" below to trigger the controller and inspect real-time performance...</div>
                </div>

                <div style="margin-top: 1.25rem; display: flex; align-items: center; justify-content: space-between;">
                    <button id="dispatchBtn" onclick="runDemo()" class="btn btn-primary" style="font-size: 0.825rem; padding: 0.55rem 1.15rem;">
                        <span>Dispatch Live Request</span>
                    </button>
                    <span id="statusInfo" style="font-family: var(--font-mono); font-size: 0.75rem; color: var(--text-muted);">
                        Method: GET &middot; Port: {{ config('app.port', 8000) }}
                    </span>
                </div>
            </div>

            <!-- Right Card: Quick Start Commands -->
            <div class="card">
                <div>
                    <div class="card-header">
                        <div class="card-title">
                            <span>🚀 Essential CLI Commands</span>
                        </div>
                        <span class="live-badge">php bongo</span>
                    </div>

                    <ul class="steps-list">
                        <li class="step-item">
                            <span class="step-num">1</span>
                            <div>
                                <div style="color: #fff; font-weight: 600; margin-bottom: 0.2rem;">Install First-Party Packages</div>
                                <code>php bongo add auth</code>
                            </div>
                        </li>
                        <li class="step-item">
                            <span class="step-num">2</span>
                            <div>
                                <div style="color: #fff; font-weight: 600; margin-bottom: 0.2rem;">Create a Controller</div>
                                <code>php bongo make:controller PostController</code>
                            </div>
                        </li>
                        <li class="step-item">
                            <span class="step-num">3</span>
                            <div>
                                <div style="color: #fff; font-weight: 600; margin-bottom: 0.2rem;">Create Model & Migration</div>
                                <code>php bongo make:model Post --migration</code>
                            </div>
                        </li>
                        <li class="step-item">
                            <span class="step-num">4</span>
                            <div>
                                <div style="color: #fff; font-weight: 600; margin-bottom: 0.2rem;">Run Built-in Test Suite</div>
                                <code>php bongo test</code>
                            </div>
                        </li>
                    </ul>
                </div>

                <div style="margin-top: 1.5rem; font-size: 0.775rem; color: var(--text-muted); border-top: 1px solid var(--border); pt-3;">
                    Zero external Composer dependencies required for basic MVC operations.
                </div>
            </div>
        </div>

        <!-- Documentation & Ecosystem Cards -->
        <div class="resources-grid">
            <a href="https://bongo.nobotech.org" target="_blank" rel="noopener" class="resource-card">
                <div>
                    <div class="resource-title">
                        <span>📖 Documentation Portal</span>
                        <span>↗</span>
                    </div>
                    <p class="resource-desc">
                        Full guides for Routing, Controllers, Database ORM, Migrations, Views, Native WebSockets, Queues, and Production Deployment.
                    </p>
                </div>
                <div style="font-family: var(--font-mono); font-size: 0.725rem; color: var(--emerald-400); margin-top: 1rem;">
                    bongo.nobotech.org
                </div>
            </a>

            <a href="https://bongo.nobotech.org/packages" target="_blank" rel="noopener" class="resource-card">
                <div>
                    <div class="resource-title">
                        <span>📦 50 Official Packages</span>
                        <span>↗</span>
                    </div>
                    <p class="resource-desc">
                        Interactive Package Explorer with 1-click install commands, .env configs, and code examples for bKash, Stripe, S3, FTP, Two-Factor & more.
                    </p>
                </div>
                <div style="font-family: var(--font-mono); font-size: 0.725rem; color: var(--emerald-400); margin-top: 1rem;">
                    bongo.nobotech.org/packages
                </div>
            </a>

            <a href="https://github.com/Solayman/bongo-php/blob/master/DOCUMENTATION.md" target="_blank" rel="noopener" class="resource-card">
                <div>
                    <div class="resource-title">
                        <span>🐙 DOCUMENTATION.md</span>
                        <span>↗</span>
                    </div>
                    <p class="resource-desc">
                        Direct link to the complete master Markdown documentation in the official Solayman/bongo-php Git repository.
                    </p>
                </div>
                <div style="font-family: var(--font-mono); font-size: 0.725rem; color: var(--emerald-400); margin-top: 1rem;">
                    github.com/Solayman/bongo-php
                </div>
            </a>

            <a href="https://github.com/Solayman/bongo-php-packages" target="_blank" rel="noopener" class="resource-card">
                <div>
                    <div class="resource-title">
                        <span>📦 Packages Repository</span>
                        <span>↗</span>
                    </div>
                    <p class="resource-desc">
                        Browse the source code and individual documentation folders for all 50 official packages on GitHub.
                    </p>
                </div>
                <div style="font-family: var(--font-mono); font-size: 0.725rem; color: var(--emerald-400); margin-top: 1rem;">
                    github.com/Solayman/bongo-php-packages
                </div>
            </a>
        </div>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container footer-content">
            <div class="footer-meta">
                <span>PHP <?= explode('-', PHP_VERSION)[0] ?></span>
                <span>&middot;</span>
                <span>Bongo v{{ $frameworkVersion }}</span>
                <span>&middot;</span>
                <span>Env: {{ $appEnv }}</span>
                <span>&middot;</span>
                <span>RAM: {{ $memoryUsage }}</span>
            </div>
            <div>
                Crafted with pride by <strong>Solayman Ali</strong> &middot; Nobotech Inc.
            </div>
        </div>
    </footer>

    <script>
        async function runDemo() {
            const btn = document.getElementById('dispatchBtn');
            const box = document.getElementById('responseBox');
            const badge = document.getElementById('timingBadge');
            
            btn.disabled = true;
            btn.innerText = 'Dispatching...';
            
            const start = performance.now();
            try {
                const res = await fetch('/api/demo');
                const duration = (performance.now() - start).toFixed(2);
                const data = await res.json();
                
                badge.innerText = `HTTP ${res.status} · ${duration} ms`;
                badge.style.background = 'rgba(16, 185, 129, 0.2)';
                badge.style.color = '#34d399';
                
                box.innerText = JSON.stringify(data, null, 2);
            } catch (err) {
                box.innerText = 'Failed to fetch /api/demo: ' + err.message;
            } finally {
                btn.disabled = false;
                btn.innerText = 'Dispatch Live Request';
            }
        }
    </script>
</body>
</html>
