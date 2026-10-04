<div align="center">

# Bongo PHP Framework

### High-performance, zero-dependency PHP MVC framework engineered for simplicity, auditability, and speed.

A complete full-stack web framework contained in ~480 KB of clean, readable code.  
**Zero external dependencies. No Composer required. No Node.js runtime. No build steps.**

[![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-777bb4?style=flat-square&logo=php&logoColor=white)](https://www.php.net)
[![Dependencies](https://img.shields.io/badge/dependencies-0%20required-success?style=flat-square)](DOCUMENTATION.md)
[![Test Suite](https://img.shields.io/badge/automated%20tests-333%20passing-success?style=flat-square)](TESTING_GUIDE.md)
[![Core Footprint](https://img.shields.io/badge/core%20size-~170%20KB-informational?style=flat-square)](#empirical-benchmarks)
[![License](https://img.shields.io/badge/license-MIT-blue?style=flat-square)](LICENSE)
[![Localization](https://img.shields.io/badge/i18n-English%20%7C%20বাংলা-orange?style=flat-square)](DOCUMENTATION.md#localization)

[**Quick Start**](#quick-start) • [**Code Tour**](#code-tour) • [**Architectural Pillars**](#architectural-pillars) • [**Framework Comparison**](#framework-comparison) • [**Documentation**](DOCUMENTATION.md)

</div>

---

## Executive Summary

Modern web development frameworks increasingly carry substantial cognitive, operational, and architectural overhead:

* **Dependency Sprawl:** Standard setups pull hundreds of third-party packages before the first line of application logic is written.
* **Supply-Chain Exposure:** Large `vendor/` trees increase attack surfaces and expose systems to upstream vulnerabilities and transitive breaks.
* **Opaque Abstractions:** Troubleshooting unexpected behavior often leads through deep call stacks and nested packages.
* **Deployment Friction:** Heavy memory footprints and rigid environment prerequisites complicate hosting on cost-effective infrastructure.

**Bongo takes an intentional, first-principles approach.** Everything required to build, secure, and scale modern web applications is built directly into a compact, unified codebase of approximately **13,000 lines**. You can read, audit, and understand the entire framework in an afternoon.

---

## Quick Start

Get a fully functional MVC application running locally in under a minute:

```bash
# Clone the repository
git clone https://github.com/Solayman/bongo-php.git my-app && cd my-app

# Configure environment (MySQL or SQLite)
cp .env.example .env        # Windows: copy .env.example .env

# Initialize application key and database
php bongo key:generate
php bongo migrate

# Start development server
php bongo serve             # Listening on http://127.0.0.1:8000
```

> [!NOTE]
> Because Bongo has zero required third-party packages, there is no `composer install`, no `npm install`, and no assets build step. Clone and run immediately.

---

## Code Tour

Bongo adheres to industry-standard MVC conventions, providing an ergonomic and expressive API without unnecessary layers of abstraction.

### 1. Declarative Routing & Middleware (`routes/web.php`)
```php
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::post('/products', [ProductController::class, 'store'])->middleware('auth');
```

### 2. Request Handling & Validation (`app/Controllers/ProductController.php`)
```php
public function store(Request $request): Response
{
    $validated = $request->validate([
        'name'  => 'required|string|max:150',
        'price' => 'required|numeric|min:0',
        'image' => 'nullable|file|mimes:jpeg,png,webp|max:2048',
    ]);

    $product = Product::create($validated);

    flash('success', 'Product published successfully.');
    return redirect(route('products.index'));
}
```

### 3. Active Record Models & Relationships (`app/Models/Category.php`)
```php
class Category extends Model
{
    protected array $fillable = ['name', 'slug'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

// Fluent querying with eager loading and aggregations
$categories = Category::withCount('products')
    ->orderBy('name')
    ->get();
```

### 4. Expressive Template Engine (`resources/views/categories/index.php`)
```html
@extends('layouts.admin')

@section('content')
    <div class="category-grid">
        @foreach($categories as $category)
            <div class="card">
                <h3>{{ $category->name }}</h3>
                <span class="badge">{{ $category->products_count }} items</span>
            </div>
        @endforeach
    </div>
@endsection
```

### 5. Native Real-Time WebSockets (Pure PHP, No External Services)
```php
// Backend broadcast (event dispatch)
Broadcast::to('orders')->send('order.created', ['id' => $order->id, 'total' => $order->total]);
```
```javascript
// Browser client listener
BongoSocket.connect('{{ websocket_url() }}')
    .on('orders', 'order.created', (order) => {
        console.log('Order received:', order);
    });
```

### 6. Zero-Setup Integrated Testing
```php
$this->get('/products')
    ->assertStatus(200)
    ->assertSee('Yoga Mat');
```
```bash
php bongo test              # Runs 333 unit & feature tests with isolated SQLite/MySQL runner
```

---

## Architectural Pillars

### 1. Full-Source Auditability
The core framework consists of approximately **13,000 lines of code across 114 files**. Components are modular and focused, averaging 200–600 lines each. Engineers can inspect any internal routine—from the request pipeline to the database grammar—in minutes without navigating third-party repositories.

### 2. Zero-Dependency Resilience
With zero mandatory external dependencies, Bongo shields applications from supply-chain compromises, unintended automated breakage, and dependency conflicts. Deploy smoothly to shared hosting, minimal VPS instances, or bare containers. Composer integration remains fully optional: if `vendor/autoload.php` is present, it is seamlessly loaded.

### 3. Predictable Low-Latency Runtime
Engineered for efficient memory usage and rapid execution cycles:

| Metric | Measured Value |
|---|---|
| **Peak Memory per Request** | **0.45 MB – 0.65 MB** |
| **Response Latency (Worst-Case, OpCache Disabled)** | **8.5 ms** (JSON API) to **18.9 ms** (Full DB page) |
| **Framework Files Loaded per Request** | **20 – 28 files** |
| **Boot & Route Optimization** | **`config:cache` (36% startup speedup)** & **`route:cache` (zero-regex dispatch)** |

### 4. Comprehensive Security by Default
Security mechanisms are embedded directly into the framework foundation:
* **Authentication & Credentials:** Argon2id hashing with system pepper, login attempt throttling, and account lockout protection.
* **Form & Session Security:** CSRF verification enforced by default, strict SameSite session cookie controls.
* **Input & Output Hardening:** Automatic output escaping in views, strict validation against real file contents (magic-byte inspection).
* **Cryptographic Primitives:** AES-256-GCM encryption, JWT authentication with token revocation, signed URLs.
* **Infrastructure Protection:** Automated security audits via `php bongo security:check`, security headers, trusted proxy handling, and CORS configuration.

### 5. Actionable Diagnostics & Developer Tooling
* **Targeted Debugging:** Clear exception screens pinpointing the exact file, call stack, database queries executed, and automated remediation hints (e.g., *Table missing → Run: `php bongo migrate`*).
* **Performant Profiling:** Clean `dd()` / `dump()` display with collapsible nodes, optimized to render collections exceeding 1,000 records without browser freezing.
* **CLI Workspace:** Interactive REPL (`php bongo tinker`), automated scaffolding (`php bongo make:*`), and extensible custom commands engine.

### 6. Battery-Included Application Suite

| Domain | Integrated Capabilities |
|---|---|
| **HTTP Layer** | Attribute routing, route groups, named parameters, middleware pipelines, signed URLs, form requests |
| **Data & ORM** | MySQL/MariaDB, PostgreSQL (`pgsql`), and SQLite support, simultaneous multi-database & multi-tenancy (`DB::connection()`, `Model::on()`), isolated transactions, query builder, relations (`hasOne`, `hasMany`, `belongsToMany`), soft deletes, schema migrations, and seeders |
| **Storage & Files** | Multi-disk storage (`public` vs private `local`), streaming file downloads (`Storage::download()`), magic-byte upload validation |
| **Spreadsheets & Docs** | Unified CSV and Excel `.xlsx` import/export (`Spreadsheet::read()`, `Spreadsheet::download()`) with UTF-8 BOM, and printable vector documents/PDFs (`Printable::invoice()`) with flawless Bangla and multilingual typography |
| **Identity & Access** | Multi-guard authentication (Session & JWT), authorization gates, policies, password reset flows, email verification |
| **Async & Scheduling** | Database and Redis-backed queues, in-process scheduler (run tasks without system crontab in development), queued mail |
| **Logging & Telemetry**| Text and structured JSON logging (`LOG_FORMAT=json`), secret masking, daily rotation, structured exception stack traces |
| **Communication** | Built-in SMTP mailer, test mailbox UI, notifications (Mail, SMS, Slack), resilient HTTP client with retries |
| **Real-Time** | Standalone WebSocket daemon with channel multiplexing written in native PHP |
| **Performance & Ops** | File & Redis caching, Redis sessions, route/config caching, maintenance mode, atomic framework self-upgrade |

### 7. Native Multilingual Support
Built-in bidirectional localization for **English and Bengali (বাংলা)** out of the box, covering framework errors, form validation, and application UI. Additional languages can be added with a single JSON translation dictionary.

### 8. Self-Contained Testing Framework
Shipped with an internal test runner featuring transaction-isolated databases, an HTTP test runner, and test doubles (`Mail::fake()`, `Http::fake()`, `Notify::fake()`, `Broadcast::fake()`), eliminating the need to install or configure external testing harnesses.

---

## Framework Comparison

Architectural trade-offs compared objectively across common PHP ecosystems:

| Feature / Consideration | **Bongo PHP** | Laravel | Symfony | CodeIgniter 4 | Slim |
|---|---|---|---|---|---|
| **Architecture** | **Full-Stack, Zero-Dep** | Full-Stack | Component-Based | Full-Stack, Lightweight | Micro-Framework |
| **Required Dependencies** | **0** | ~50+ packages | ~40+ packages | Minimal | Several |
| **Fresh Skeleton Footprint** | **~170 KB** | 30 MB+ | 25 MB+ | ~2 MB | ~1 MB |
| **Total Core Code** | **~13k lines (Fully auditable)** | ~300k+ lines | ~500k+ lines | ~80k lines | Minimal router only |
| **ORM, Auth, Queues, Mail** | **Integrated** | Integrated | Integrated / Bundles | Partially Integrated | External Packages Required |
| **Native WebSockets** | **Integrated (Pure PHP)** | Separate Service / Reverb | Third-Party Bundle | Not Included | Not Included |
| **Built-in Security Auditor** | **`security:check`** | External package | External package | Not Included | Not Included |
| **Native Bilingual (বাংলা)** | **Built-in** | Community Package | Community Package | Community Package | Not Included |
| **Ecosystem Maturity** | **v0.9.x (Focused)** | Industry Standard | Enterprise Standard | Mature | Mature |

---

## Workload Fit & Architectural Scope

### Optimal Use Cases
* **Enterprise Internal Tools & Admin Portals:** Applications requiring high maintainability, low server overhead, and deterministic execution.
* **High-Throughput Microservices & JSON APIs:** Services demanding rapid boot times and low memory consumption.
* **Edge & Resource-Constrained Environments:** Shared hosting, embedded appliances, or minimal cloud instances lacking root access or Composer/Node toolchains.
* **Security & Audit-Sensitive Organizations:** Teams needing complete visibility and audit control over every line of framework code running in production.

### Current Architectural Boundaries
* **Supported Databases:** Native first-class support for **MySQL / MariaDB**, **PostgreSQL (`pgsql`)**, and **SQLite**.
* **Queue Drivers:** High-reliability Database and Redis queue drivers are built in (with delayed retry sets and failed job tracking).
* **Ecosystem Scale:** Focuses on standard web patterns. Third-party integrations (e.g., custom OAuth providers, specialized payment gateways) are implemented cleanly in application code or via optional Composer packages.

---

## Frequently Asked Questions

**Can I still utilize third-party Composer packages?**  
Yes. Bongo does not require Composer, but it remains fully compatible with it. If `vendor/autoload.php` exists, the framework automatically registers the autoloader.

**Is Bongo suitable for production workloads?**  
Bongo includes 340 automated test cases, strict type hinting, regression suites, and production security verification (`php bongo security:check`). As a v0.9.x release, core interfaces are stable while feature expansion continues.

**How does framework maintenance and upgrading work?**  
The framework provides an isolated upgrade utility (`php bongo upgrade`) that verifies cryptographic SHA-256 checksums, creates an automated pre-upgrade backup, and patches core `system/` components without touching application files. Upgrades can be reversed using `php bongo upgrade --rollback`.

**Who maintains Bongo?**  
Bongo is engineered and maintained by [Nobotech](https://nobotech.xyz) and open-sourced under the MIT License. Contributions and architectural discussions are welcome via [GitHub Issues](https://github.com/Solayman/bongo-php/issues) and [Pull Requests](https://github.com/Solayman/bongo-php/pulls).

---

<details>
<summary><b>Empirical Benchmarks & Measurement Methodology</b></summary>

All benchmarks recorded on a clean reference environment using PHP 8.3 on Windows.

**Codebase Size** (Excluding application and demo layer):
```bash
find system -name "*.php" | wc -l                     # 114 files
find system -name "*.php" -exec cat {} + | wc -l      # 12,779 lines
```

**Per-Request Resource Utilization**:  
Executed against `public/index.php` in isolated PHP processes under production configuration with compiled views. Memory represents `memory_get_peak_usage()`; latency represents the median of 6 runs with OpCache disabled (worst-case baseline). Under standard production OpCache, response times are faster.

| Route | Execution Type | Loaded Files | Peak Memory | Latency |
|---|---|---:|---:|---:|
| `GET /api/health` | Plain JSON, zero DB queries | 39 | 0.45 MB | 8.5 ms |
| `GET /login` | HTML response, layout, CSRF initialization | 47 | 0.53 MB | 9.1 ms |
| `GET /api/products` | REST endpoint with JWT validation | 40 | 0.45 MB | 9.4 ms |
| `GET /shop` | Product catalog with relational queries & i18n | 56 | 0.65 MB | 18.9 ms |

**Configuration Cache Benchmark (`php bongo config:cache`)**:  
Bootstrap compilation reduced framework initialization latency from 17.3 ms to 11.1 ms across 15 median test cycles.

</details>

---

<div align="center">

**[Documentation](DOCUMENTATION.md)** • **[Contribution Guidelines](CONTRIBUTING.md)** • **[Security Policy](SECURITY.md)** • **[Issue Tracker](https://github.com/Solayman/bongo-php/issues)**

<sub>Maintained by Nobotech · Distributed under the MIT License</sub>

</div>
