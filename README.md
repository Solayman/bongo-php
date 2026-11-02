<div align="center">

# 🥁 Bongo PHP Framework

### The High-Performance, Zero-Dependency PHP MVC Framework Engineered for Speed, Simplicity, and Full Source Auditability.

A full-stack, enterprise-grade web application framework crafted in ~720 KB of clean, modern, pure PHP.  
**Zero Composer required. Zero `vendor/` bloat. Zero Node.js runtime. Zero build steps. cPanel & VPS ready.**

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Dependencies](https://img.shields.io/badge/dependencies-0%20required-brightgreen?style=flat-square)](WHY_BONGO.md)
[![Test Suite](https://img.shields.io/badge/automated%20tests-410%20passing%20(1784%20assertions)-brightgreen?style=flat-square)](TESTING_GUIDE.md)
[![Memory Footprint](https://img.shields.io/badge/RAM%20per%20request-0.77%20MB-success?style=flat-square)](#-performance--benchmarks)
[![Core Size](https://img.shields.io/badge/core%20size-~720%20KB-informational?style=flat-square)](#-performance--benchmarks)
[![Official Packages](https://img.shields.io/badge/official%20packages-50%20available-blueviolet?style=flat-square)](https://github.com/Solayman/bongo-php-packages)
[![Hosting Ready](https://img.shields.io/badge/hosting-cPanel%20%7C%20VPS%20%7C%20Docker-blue?style=flat-square)](#-deploy-anywhere)
[![Localization](https://img.shields.io/badge/i18n-English%20%7C%20বাংলা-orange?style=flat-square)](DOCUMENTATION.md#localization)
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](LICENSE)
[![Facebook Community](https://img.shields.io/badge/Facebook-Nobotech%20Labs-1877F2?style=flat-square&logo=facebook&logoColor=white)](https://www.facebook.com/profile.php?id=61595418016860)

[**Official Website**](https://nobotech.org) • [**Documentation Portal**](https://bongo.nobotech.org) • [**50 Official Packages**](https://bongo.nobotech.org/packages) • [**Packages on GitHub**](https://github.com/Solayman/bongo-php-packages) • [**Live Benchmarks**](https://bongo.nobotech.org/benchmarks) • [**Why Bongo?**](WHY_BONGO.md) • [**Facebook Community**](https://www.facebook.com/profile.php?id=61595418016860)

</div>

---

## ⚡ The Philosophy

Most modern PHP frameworks demand significant operational and architectural overhead before writing a single line of code—hundreds of external packages, multi-megabyte `vendor/` directories, complex build pipelines, opaque abstraction wrappers, and heavy memory footprints.

**Bongo PHP takes an intentional, first-principles path:**

* **Zero Mandatory Dependencies:** Clone the repository, configure your database, and run. No Composer dependency resolver to wait for, no package breaking on minor updates, and no third-party supply-chain vulnerabilities.
* **Fully Auditable Codebase:** Total framework code is ~18,500 lines across 128 structured files (~720 KB). Every routine—from the request pipeline and Active Record ORM to packages and WebSockets—can be read and audited in an afternoon.
* **Predictable, Measured Performance:** About **0.77 MB of RAM** per request and **sub-10ms** response latencies with OpCache enabled (sub-2.5ms on LiteSpeed/Nginx PHP-FPM). Loads 24–45 files per request with configuration and route caches on. Measure your own application with `php bongo benchmark`.
* **Deploy Anywhere:** Runs seamlessly on standard shared hosting (cPanel), minimalist VPS instances, Docker containers, or high-density serverless environments without requiring root access or Composer.

---

## 🚀 Quick Start

Get a fully functional MVC application running locally in under 60 seconds:

### Clean Starter Skeleton (Recommended for new projects)
The `release` branch provides a clean, production-ready starter skeleton (**~800 KB**, 183 files) containing the core framework, starter controllers, models, views, and migrations—with zero demo clutter and zero test fixtures:

```bash
# 1. Clone the clean starter skeleton
git clone -b release https://github.com/Solayman/bongo-php.git my-app && cd my-app

# 2. Configure environment (Windows: copy .env.example .env)
cp .env.example .env

# 3. Generate security key and run initial migrations
php bongo key:generate
php bongo migrate

# 4. Start local development server
php bongo serve             # Listening on http://127.0.0.1:8000 (supports --port 8000, 8002, -p 8002)
```

Visit your application in the browser:
* **Welcome Page:** `http://127.0.0.1:8000/`
* **Admin Sign In:** `http://127.0.0.1:8000/login` *(Login: `admin@example.com` / `password`)*
* **Admin Dashboard:** `http://127.0.0.1:8000/dashboard`

### Official Feature Testbed & Package Sandbox
If you are evaluating Bongo's complete ecosystem (E-Commerce Storefront, Blog CMS, File Manager, TinyMCE 6, and official packages):

```bash
# 1. Clone the official testbed repository
git clone https://github.com/Solayman/bongo-package-and-feature-test.git testbed && cd testbed

# 2. Configure environment (Windows: copy .env.example .env)
cp .env.example .env

# 3. Generate security key, migrate database, and seed full demo
php bongo key:generate
php bongo migrate
php bongo db:seed

# 4. Start local development server
php bongo serve --port 8002
```

Visit the testbed application in the browser:
* **Testbed Admin / CMS:** `http://127.0.0.1:8002/login` *(Pre-filled: `admin@example.com` / `password`)*
* **Storefront Demo:** `http://127.0.0.1:8002/shop` *(Pre-filled: `rahim1@example.com` / `password`)*
* **Public Blog:** `http://127.0.0.1:8002/blog`

> [!TIP]
> Composer packages are **100% optional**. If your project ever requires a third-party Composer package, Bongo automatically detects and registers `vendor/autoload.php` with zero configuration.

---

## 💻 Code Tour

If you have used modern MVC frameworks like Laravel, Bongo's fluent syntax, helpers, and conventions will feel immediately intuitive.

### Declarative Routing (`routes/web.php`)
```php
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::post('/products', [ProductController::class, 'store'])->middleware('auth');

// Grouped API routes with JWT protection and rate throttling
Route::prefix('/api/v1')->middleware('throttle:60,1')->group(function () {
    Route::get('/profile', [ApiController::class, 'profile'])->middleware('auth:api');
});
```

### Controller & Request Validation (`app/Http/Controllers/ProductController.php`)
```php
public function store(Request $request): Response
{
    $validated = $request->validate([
        'name'  => 'required|string|max:150',
        'price' => 'required|numeric|min:0',
        'image' => 'nullable|file|mimes:jpeg,png,webp|max:2048',
    ]);

    $product = Product::create($validated);

    flash('success', 'Product created successfully.');
    return redirect(route('products.index'));
}
```

### Active Record ORM & Relationships (`app/Models/Category.php`)
```php
class Category extends Model
{
    protected array $fillable = ['name', 'slug'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

// Fluent querying with eager loading and relation counting
$categories = Category::withCount('products')
    ->orderBy('name')
    ->get();
```

### Expressive Template Views (`resources/views/categories/index.php`)
```html
@extends('layouts.admin')

@section('content')
    <div class="header">
        <h1>Categories</h1>
        @can('manage-catalog')
            <a href="{{ route('categories.create') }}" class="btn">Add Category</a>
        @endcan
    </div>

    @foreach($categories as $category)
        <p>{{ $category->name }} ({{ $category->products_count }} products)</p>
    @endforeach
@endsection
```

### Pure-PHP Real-Time WebSockets (No Node.js / Pusher Required)
```php
// Dispatch real-time events from PHP
Broadcast::to('orders')->send('order.created', ['id' => $order->id, 'amount' => $order->total]);
```
```javascript
// Listen instantly in the browser
BongoSocket.connect('{{ websocket_url() }}')
    .on('orders', 'order.created', (order) => {
        alert('New order #' + order.id);
    });
```

### Integrated Test Suite (No Complex Setup Needed)
```php
$this->get('/products')
    ->assertStatus(200)
    ->assertSee('Yoga Mat');
```
```bash
php bongo test              # Runs 410 unit and HTTP integration tests (1,784 assertions)
```

---

## 🧰 Everything Included Out of the Box

Bongo includes an exhaustive set of full-stack batteries built in:

| Component | Capabilities |
|---|---|
| **HTTP & Routing** | RESTful verb routing (`get`, `post`, `put`, `patch`, `delete`), route groups, route prefixing, named parameters, signed URLs, CORS, security headers, middleware stacks, and form requests (`make:request`). |
| **Database & ORM** | MySQL/MariaDB, PostgreSQL (`pgsql`), and SQLite query builder, simultaneous multi-database & multi-tenancy (`DB::connection()`, `Model::on()`), isolated transactions & savepoints, Active Record models, relationships (`hasOne`, `hasMany`, `belongsToMany`), eager loading, soft deletes, tracked schema migrations with rollback, and seeders. |
| **Authentication & Access** | Multi-guard authentication (Session and stateless JWT), password hashing with Argon2id + pepper, account lockout, "Remember me", password reset flows, signed email verification, rich-text HTML XSS sanitization (`clean_html()`), and authorization policies (`Gate` / `Policy`). |
| **Storage & Uploads** | Multi-disk storage (`Storage::disk('public')`, private `Storage::disk('local')`, and cloud `Storage::disk('s3')`), presigned temporary URLs (`Storage::temporaryUrl()`), custom driver extensibility (`Storage::extend()`), streaming file downloads (`Storage::download()`), 35+ validation rules, array wildcards (`items.*.qty`), and upload validation inspected via real magic bytes. |
| **Spreadsheets & Docs** | Unified import/export for CSV and Excel `.xlsx` (`Spreadsheet::read()`, `Spreadsheet::download()`) with UTF-8 BOM, and printable vector documents/PDFs (`Printable::invoice()`) with flawless Bangla and multilingual typography. |
| **Async & Background** | High-throughput job queues (Database and Redis drivers), worker daemon (`php bongo queue:work`), task scheduler (execute recurring jobs without server cron in dev via `php bongo schedule:work`), and queued background mail. |
| **Logging & Observability**| Text and structured JSON logging (`LOG_FORMAT=json`), daily rotated logs in `storage/logs/`, levels, secret masking, and structured exception formatting for Datadog, CloudWatch, and ELK. |
| **Extensible CLI** | Pure-PHP CLI with custom console commands (`php bongo make:command <Name>`), base `Command` class, and automatic discovery from `app/Commands/`. |
| **Communication** | Built-in SMTP mailer, HTML/text templates, attachments, notifications (Mail, SMS, Slack), and an HTTP client with retry policies (`Http::get/post`). |
| **Real-Time WebSockets** | Native pure-PHP WebSocket server (`php bongo websocket:serve [--origins=...]`) with channel multiplexing and public/private subscriptions. |
| **50 Official Packages** | Zero-dependency package manager (`System\Package`), update diff safety, app override priority, and 50 official packages (`sslcommerz`, `bkash`, `nagad`, `aamarpay`, `stripe`, `paypal`, `sms`, `s3`, `social-login`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`). |
| **Developer Ergonomics** | Collapsible zero-freeze `dd()` / `dump()` profiling, actionable debug exception screens with auto-fix hints, browser mailbox inspector, and interactive shell (`php bongo tinker`). |
| **Security Auditing** | `php bongo security:check` scans server configuration, permissions, security headers, and production readiness before launch. |
| **Multilingual (I18n)** | Shipped with bilingual support for **English** and **Bengali (বাংলা)** across validation errors and UI text. Extensible via JSON dictionaries. |

---

## 📊 Performance & Benchmarks

*Measured with `php bongo benchmark` (PHP 8.3, Windows 11 reference environment, built-in server, PostgreSQL, `config:cache` + `route:cache` + `view:cache`, median of 200 requests).*

| Route | Execution Type | Loaded Files | Peak RAM | OpCache off | OpCache on |
|---|---|---:|---:|---:|---:|
| `GET /login` | HTML page, session & CSRF | 34 | 0.69 MB | 28.3 ms | **6.8 ms** |
| `GET /api/health` | JSON API with rate limiter | 24 | 0.67 MB | 26.2 ms | **13.0 ms** |
| `GET /shop` | Product catalog, 3 queries | 45 | 0.83 MB | 119.7 ms | 85.7 ms, **13.4 ms** with `DB_PERSISTENT=true` |

*On production Linux servers running LiteSpeed or Nginx with PHP-FPM and OpCache preloading (`system/preload.php`), median response latencies drop below **2.5 ms**.*

### Architectural Comparison: Bongo vs Typical PHP MVC Framework

| Metric | Bongo PHP Framework | Typical PHP MVC Framework | Why It Matters |
|---|---|---|---|
| **RAM per Request** | **0.77 MB RAM** | 14 MB – 32 MB RAM | Enables high density on low-cost cPanel and VPS hosting. |
| **External Dependencies** | **0 (Zero Vendor Packages)** | 75 – 140 Packages | Immune to supply-chain attacks, abandoned libraries, and dependency hell. |
| **Core Framework Size** | **~720 KB** | 55 MB – 110 MB | Deploy in seconds over FTP, Git, or CI/CD without gigabytes of vendor files. |
| **Real-Time Capabilities** | **Native WebSockets + SSE** | Requires 3rd-party SaaS (Pusher/Ably) | Zero monthly external service fees for real-time events. |
| **Starter Skeleton** | **183 files (~800 KB)** | 4,000+ files (~80 MB) | Instant cloning, instantaneous file indexing, and fast cold boot. |

---

## 🛠️ CLI Tooling (`bongo`)

The framework ships with an integrated, high-speed pure-PHP CLI runner (`php bongo`):

```bash
php bongo help              # Show all available commands
php bongo migrate           # Run database migrations
php bongo migrate:rollback  # Rollback last migration batch
php bongo db:seed           # Populate database with seeds
php bongo tinker            # Open interactive PHP REPL
php bongo security:check    # Audit security configuration & file permissions
php bongo websocket:serve   # Start native pure-PHP WebSocket daemon
php bongo queue:work        # Run background queue worker
php bongo schedule:work     # Run scheduled tasks locally
php bongo config:cache      # Cache configuration for production speed
php bongo route:cache       # Precompile registered routes for instantaneous dispatch
php bongo view:cache        # Precompile and lint all templates ahead of time
php bongo benchmark         # Measure real-world latency and memory usage
php bongo upgrade           # Safely upgrade framework with auto-backup & rollback
php bongo add <pkg>         # Enable in config/packages.php and install package
php bongo package:install   # Install configured packages or specific package
php bongo package:update    # Update installed package(s) with diff safety net
php bongo package:publish   # Publish package assets (config, views, lang)
php bongo package:remove    # Remove package files cleanly
php bongo package:list      # List installed packages and available updates
```

Generate boilerplate components instantly:
```bash
php bongo make:command SendWeeklyDigest
php bongo make:controller UserController
php bongo make:model Invoice
php bongo make:migration create_invoices_table
php bongo make:middleware CheckSubscription
php bongo make:request StoreInvoiceRequest
php bongo make:policy InvoicePolicy
php bongo make:job ProcessPayment
php bongo make:mail WelcomeEmail
php bongo make:notification OrderShippedNotification
```

---

## 📋 System Requirements

* **PHP 8.0, 8.1, 8.2, 8.3+**
* Extensions: `pdo_mysql`, `pdo_pgsql`, or `pdo_sqlite`; `openssl`, `mbstring`
* Database: MySQL / MariaDB (5.7+), PostgreSQL (12+), or SQLite (3.x+)
* Web Server: Apache (with `mod_rewrite`), LiteSpeed, Nginx, or PHP built-in CLI server

---

## 🌐 Community, Support & Official Channels

Stay connected with the Nobo Tech community, get technical assistance, report issues, and follow framework releases:

* **Official Facebook Page:** [Nobotech Labs on Facebook](https://www.facebook.com/profile.php?id=61595418016860) — Announcements, tutorials, and community discussions.
* **Official Website:** [nobotech.org](https://nobotech.org) — Official company portal, philosophy, and products.
* **Documentation Portal:** [bongo.nobotech.org](https://bongo.nobotech.org) — In-depth guides, API reference, and tutorials.
* **Official Packages Repository:** [Solayman/bongo-php-packages](https://github.com/Solayman/bongo-php-packages) — Monorepo of all 50 official zero-dependency packages.
* **Online Package Explorer:** [bongo.nobotech.org/packages](https://bongo.nobotech.org/packages) — Interactive visual browser for packages.
* **Lead Architect:** Engineered with precision by **Solayman Ali** ([ratonmsa@gmail.com](mailto:ratonmsa@gmail.com)).
* **Support Email:** [support@nobotech.org](mailto:support@nobotech.org) · **Admin Email:** [admin@nobotech.org](mailto:admin@nobotech.org)

---

## 📚 Documentation & Guides

* **[Why Bongo?](WHY_BONGO.md)**: In-depth comparison with Laravel, Symfony, CodeIgniter 4, and Slim.
* **[Comprehensive Documentation](DOCUMENTATION.md)**: Master guide with complete reference examples for all modules.
* **[Testing Guide](TESTING_GUIDE.md)**: Verification checklist and test suite documentation (410 tests passing).
* **[Upgrade Guide](UPGRADE.md)**: Framework versioning, upgrade lifecycle, and rollback instructions.
* **[Changelog](CHANGELOG.md)**: Detailed history of releases, security patches, and improvements.

---

## 🤝 Contributing & Security

* **Contributing:** Please read our [Contribution Guidelines](CONTRIBUTING.md) and [Code of Conduct](CODE_OF_CONDUCT.md) before submitting pull requests.
* **Security Disclosures:** If you discover a vulnerability, please review our [Security Policy](SECURITY.md) to report it responsibly.

---

<div align="center">

Bongo is open-source software licensed under the [MIT License](LICENSE).  
Copyright © 2026 **[Nobo Tech](https://nobotech.org)**. Engineered by **Solayman Ali**.

⭐ **Star this repository if Bongo PHP helps your development workflow!**

<br />

<sub><b>Search & Keywords:</b> PHP MVC Framework · Zero-Dependency PHP · Lightweight PHP Framework · High-Performance PHP · Fast PHP MVC · cPanel PHP Framework · VPS PHP Framework · Pure PHP WebSockets · Active Record ORM · PHP Query Builder · Pure PHP 8.3 · Nobo Tech · Solayman Ali</sub>

</div>
