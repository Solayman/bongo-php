# Project Instructions & Memory — Bongo PHP Framework

## 1. Multi-Repository Workspace Structure (`D:\laravel\framework\`)
- **`bongo-php`**: Core framework repository (`solayman` branch for active development, `master` for production).
- **`bongo-php-packages`**: Official package suite repository containing all 44 packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`, `captcha`, `aamarpay`, `telegram`, `bangla-tools`, `bd-locations`, `webhooks`, `impersonate`, `queue-monitor`, `discord`, `toast`, `sweet-alert`, `zoom`, `google-meet`, `courier`, `invoice`, `whatsapp`, `currency`, `coupon`, `push-notification`, `geoip`, `shopping-cart`, `qr-code`, `device-detector`, `honeypot`) with 330 passing package tests (1,357 assertions).
- **`bongo-package-and-feature-test`**: Official testbed and application sandbox with 42 passing feature tests (449 assertions). **RULE: Always implement and test new features in this repository.** (`bongo-test` junction links here).
- **`BongoMvc`**: Directory junction linking to `bongo-php` for seamless package test runner (`run_tests.php`) execution.
- **`bongo-test`**: Directory junction linking to `bongo-package-and-feature-test`.

---

## 2. Git, Branching & Merge Request (MR) Policy
- **Full MR & Merge Access**: The AI agent has full authority to make Pull Requests / Merge Requests (MRs) and merge them.
- **Always Use `solayman` Branch**: All development code, package additions, and framework updates must be committed to the `solayman` branch.
- **Always Make MR with `solayman` & Merge**:
  1. Commit code to `solayman`.
  2. Push to `origin/solayman`.
  3. Create PR/MR targeting `master` with `head: solayman` (via GitHub REST API or `gh pr create`).
  4. Merge the PR/MR into `master` immediately (`merge_method: merge`).
  5. Sync local `master` and `solayman` branches (`git merge origin/master`).
  6. **ALWAYS Update the `release` Branch Immediately**: Run `php bongo release:branch` and `git push origin refs/heads/release:release`.
  7. **Sync Feature Testbed**: Pull the fresh starter skeleton into `bongo-package-and-feature-test` (`git pull origin release`).
- **ALWAYS Update the Release Branch (`release`) (CRITICAL - MANDATORY)**:
  - The `release` branch is the **clean starter skeleton** (~800 KB, 183 files, zero demo store, zero test fixtures) that end-users and developers clone (`git clone -b release`) to start fresh projects with Bongo PHP.
  - **RULE**: Every time a batch of work or a PR is merged into `master`, the `release` branch MUST be built and pushed immediately. Never let `release` drift behind `master`.
- **Package MRs (`Solayman/bongo-php-packages`)**:
  - **RULE: If any package needs an MR, go with `bongo-php-packages`.**
  - Commit to dedicated working branch, push to origin, create PR targeting `master`, and merge immediately.

---

## 3. Environment & Credential Policy
- **Check `.env` for `GITHUB_TOKEN`**: Every repository maintains a `.env` file containing a dedicated `GITHUB_TOKEN` (Fine-grained PAT) for API operations, package catalog lookups, PR creation, and merges:
  - `bongo-php/.env`: `GITHUB_TOKEN`
  - `bongo-package-and-feature-test/.env`: `GITHUB_TOKEN`
  - `bongo-php-packages/.env`: `GITHUB_TOKEN`
- Never remove, overwrite, or expose credentials. Use them for authenticated GitHub API operations.

---

## 4. Core Architecture & Principles
- **Zero Required Dependencies**: The framework must remain self-contained (no mandatory Composer packages, no Node.js runtime, no asset build step). Pure PHP 8.0+.
- **Minimal Size & Overhead**: Keep `system/` file count low (~127 files) and footprint ~626 KB (~17,400 lines). Fresh starter skeleton on `release` branch is ~800 KB (183 files). Never introduce bloated dependencies or excessive abstractions.
- **Performance Characteristics**: About **0.67–0.83 MB per request** and **7–13 ms per page with OpCache** (`php bongo benchmark`, Windows, built-in server; see DOCUMENTATION.md "Performance").
- **Production Optimizations**:
  - `php bongo config:cache`: Compiles `config/*.php` and `.env` into `storage/cache/config.php`.
  - `php bongo route:cache`: Compiles registered routes into `storage/cache/routes.php`.
  - `php bongo view:cache`: Precompiles and lints templates in `storage/cache/views/`.
  - `php bongo benchmark`: Measures request duration, memory usage, and throughput with OpCache on vs off.
- **Supported Queue Drivers**:
  - `sync`: Executes jobs immediately inline.
  - `database`: Uses `jobs` table with row locking.
  - `redis`: High-throughput Redis lists and delayed sorted sets (`QUEUE_DRIVER=redis`), with Lua atomic reservation and HMAC signing.
- **Supported Database Drivers & Multi-Database Architecture**:
  - `mysql`: Default enterprise MySQL/MariaDB driver.
  - `sqlite`: Zero-configuration file database (`database.sqlite`).
  - `pgsql`: Native PostgreSQL driver with `PostgresGrammar` DDL, `BIGSERIAL`, `JSONB`, `BOOLEAN`, schema search paths, double-quote identifier escaping, atomic `RETURNING "id"`, and `ON CONFLICT` upsert.
  - **Simultaneous Multi-Database & Multi-Tenancy**: Full support for concurrent querying of multiple named connections in the same request via `DB::connection('name')`, isolated per-connection transaction lifecycles and savepoints (`$conn->transaction(fn() => ...)`), model routing (`protected static ?string $connection = 'name';` and `Model::on('name')`), cross-database relationships, connection-scoped schemas (`Schema::connection('name')`), dynamic runtime connection registration (`Connection::addConnection()`), and migration CLI targeting (`php bongo migrate --database=name`).
- **Storage Subsystem**:
  - Multi-disk isolation (`config/storage.php`): `'public'` (`public/storage`), private `'local'` (`storage/app`), cloud `'s3'` (via official `s3` package), and `'ftp'` (via official `ftp` package).
  - Presigned temporary URLs via `Storage::temporaryUrl()`, runtime driver extension via `Storage::extend()`, test disk injection via `Storage::set()`, and safe streaming downloads via `Storage::download()`.
- **Extensible CLI**:
  - Base class `System\Console\Command` with `handle(array $args, array $options): int` and styled output helpers (`info`, `warn`, `error`, `line`).
  - Auto-discovery of custom commands from `app/Commands/*.php`.
- **Structured Logging**:
  - Supports `LOG_FORMAT=text` and `LOG_FORMAT=json` with secret masking and structured exception objects.
- **Data & Document Subsystem**:
  - `System\Data\Spreadsheet`: Unified CSV and OpenXML Excel (`.xlsx`) import and export engine with UTF-8 BOM, automatic format detection, and streaming downloads.
  - `System\Data\Printable`: Responsive, print-ready document and vector PDF engine with `@media print` CSS, native browser print/PDF trigger, and headless browser PDF compilation bridge (Chrome/Edge/Chromium/wkhtmltopdf) so Bengali and other Indic scripts keep their ligatures and vowel signs (a real browser engine shapes the text).
- **Official Package Subsystem & Ecosystem**:
  - `System\Package`: Zero-dependency package manager and runtime loader (`Installer` and `PackageManager`), SHA-256 integrity verification, safe publishing with app override priority (`resources/views/vendor/<name>/`, `lang/vendor/<name>/`), developer safety net (`--backup`, `--force`), package migration management, CLI command auto-discovery, runtime `bootstrap.php` execution, and full CLI suite (`php bongo add / package:*`).
  - **Official Packages Suite (`Solayman/bongo-php-packages`)**: 44 production packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`, `captcha`, `aamarpay`, `telegram`, `bangla-tools`, `bd-locations`, `webhooks`, `impersonate`, `queue-monitor`, `discord`, `toast`, `sweet-alert`, `zoom`, `google-meet`, `courier`, `invoice`, `whatsapp`, `currency`, `coupon`, `push-notification`, `geoip`, `shopping-cart`, `qr-code`, `device-detector`, `honeypot`) with 330 passing package tests (1,357 assertions).
- **HTML Sanitization & Stored XSS Prevention Subsystem**:
  - `System\Security\HtmlSanitizer` and global helper `clean_html()`. Uses native `DOMDocument` wrapped in a UTF-8 envelope so non-Latin scripts (Bengali and other Indic scripts) retain native characters without numeric character reference mangling. Strips dangerous tags (`<script>`, `<iframe>`, `<style>`, `<svg>`, `<math>`, `<object>`, `<embed>`), inline event handlers `on*`, dangerous URL schemes (`javascript:`, `vbscript:`), dangerous CSS properties, and enforces `rel="noopener noreferrer"` on `target="_blank"`.
- **WebSocket Server**:
  - Standalone pure-PHP WebSocket daemon (`php bongo websocket:serve`), with deterministic command-line origin restrictions via `--origins=` / `--allowed-origins=`.

---

## 5. Recent Implementations & Framework Optimizations (Detailed Reference)

### A. Route Caching (`php bongo route:cache` / `route:clear`)
- **Problem Solved:** Dynamically parsing route files (`routes/web.php`, `routes/api.php`) and evaluating regex rules on every HTTP request adds latency as route counts grow.
- **Implementation:** Added `Router::toCache()` to export serializable route definitions, middleware groups, and aliases into `storage/cache/routes.php`. Added `Router::fromCache()` to restore the router instantly in production. Added `ROUTE_CACHE` constant and CLI commands (`php bongo route:cache` and `php bongo route:clear`). Safety: Validates that all route actions are serializable `[Controller::class, 'method']` arrays; throws a clear `RuntimeException` if closure routes are detected. Refactored `/api/health` from an inline closure to `App\Http\Controllers\Api\HealthController` so 100% of framework routes can be cached. Tested in `tests/Unit/RouteCacheTest.php`.

### B. Redis Queue Driver (`QUEUE_DRIVER=redis`)
- **Problem Solved:** Database queues (`QUEUE_DRIVER=database`) incur row-locking overhead on MySQL/SQLite under high concurrency.
- **Implementation:** Pure-PHP Redis driver integration in `System\Queue\Queue::push()` and `System\Queue\Worker`. Ready jobs are pushed to Redis lists (`RPUSH` / `LPOP`). Delayed jobs are scheduled using Redis sorted sets (`ZADD` / `ZRANGEBYSCORE`). The worker automatically migrates due delayed jobs into the main queue before popping. Lua script handles atomic reservation in `:reserved`. Unified Failure Handling: When jobs fail permanently, error details are stored in the database `failed_jobs` table (`php bongo queue:failed`, `queue:retry`, `queue:flush`). Tested in `tests/Unit/RedisTest.php`.

### C. Framework Runtime Optimizations
- **Static Route Fast-Path:** Updated `RouteDefinition::match()` to check `$this->path === $path` for routes without dynamic `{parameters}`, skipping regular expressions for ~80% of standard application routes.
- **Autoloader Speedup:** Replaced array iteration and runtime `strlen()` in `config/bootstrap.php` with direct prefix checks (`strncmp('App\\', ...)` and `strncmp('System\\', ...)`) and `is_file()` lookups.

### D. Native PostgreSQL Database Driver & Schema Grammar (`DB_CONNECTION=pgsql`)
- **Problem Solved:** Enterprise environments and high-concurrency cloud deployments frequently require PostgreSQL instead of MySQL or SQLite.
- **Implementation:** Added `connectPgsql()` and `isPgsql()` to `System\Database\Connection` with schema search path and timezone alignment. Created `System\Database\Schema\PostgresGrammar` handling `BIGSERIAL PRIMARY KEY`, `BOOLEAN`, `JSONB`, `VARCHAR(...) CHECK (...)` for enums, double-quote identifier escaping (`"table"."col"`), column comments, and automatic trigger functions for `useCurrentOnUpdate()`. Updated `Schema::rename()`, `hasColumn()`, and `tables()` using PostgreSQL's `information_schema`. Updated `QueryBuilder::insertGetId()` with atomic `RETURNING "id"`; updated `upsert()` with `ON CONFLICT (...) DO UPDATE SET ...` with `EXCLUDED` references; updated `sharedLock()` to `FOR SHARE`. Tested in `tests/Unit/PostgresSchemaTest.php`.

### E. Multi-Disk File Storage (`Storage::disk('local')` vs `Storage::disk('public')`)
- **Problem Solved:** Applications previously saved files only to the web-accessible `public/storage` folder. Private documents (invoices, export CSVs, IDs) were at risk of unintentional public exposure.
- **Implementation:** Created `System\Storage\Disk` representing isolated storage mounts with full filesystem operations (`put`, `get`, `exists`, `missing`, `size`, `lastModified`, `delete`, `url`, `download`, `files`, `allFiles`, `makeDirectory`, `deleteDirectory`). Configured `public` (web accessible via `/storage/...`) and `local` (rooted at `storage/app`, completely private outside document root) in `config/storage.php`. Created `System\Storage\DownloadResponse` with HTTP headers (`Content-Disposition`, `Content-Length`, `Content-Type`) and streaming via `readfile()`. Updated `UploadedFile::store($dir, $disk = null)` allowing uploaded files to be directly stored to private or public disks. Tested in `tests/Unit/StorageTest.php`.

### F. Custom Console Commands Engine (`make:command` & Auto-Discovery)
- **Problem Solved:** Extending the CLI previously required hacking the core `bongo` binary.
- **Implementation:** Created `System\Console\Command` base class with `handle(array $args, array $options): int`, and styled terminal output helpers (`$this->info()`, `$this->warn()`, `$this->error()`, `$this->line()`). Added `php bongo make:command <Name>` generator in `System\Console\Generator::command()`. The `bongo` CLI dynamically scans `app/Commands/*.php`, instantiates subclasses of `System\Console\Command`, lists them in `php bongo help`, and executes them with parsed options (`--flag`, `--key=val`). Tested in `tests/Unit/CommandTest.php`.

### G. Structured JSON Logging (`LOG_FORMAT=json`)
- **Problem Solved:** Containerized environments (Docker, Kubernetes) and centralized log collectors require single-line JSON logs rather than bracketed text logs.
- **Implementation:** Added `format` option to `System\Log\Logger` (`LOG_FORMAT=text` or `LOG_FORMAT=json`), configurable in `config/logging.php` and `.env`. In JSON mode, outputs newline-delimited JSON objects containing `timestamp`, `env`, `level`, `message`, sanitized/masked `context`, and structured `exception` data (`class`, `message`, `code`, `file`, `line`, string array `trace`). Updated `Logger::recent()` to seamlessly parse both structured JSON log lines and legacy bracketed text log lines. Tested in `tests/Unit/LoggerTest.php`.

### H. Unified Spreadsheet Engine (`Spreadsheet::read()` & `Spreadsheet::download()`)
- **Problem Solved:** Need to import/export tabular data in both CSV and Microsoft Excel (.xlsx) without massive 40MB packages (PhpSpreadsheet) and duplicate code.
- **Implementation:** Created `System\Data\Spreadsheet` providing a single, unified interface for both CSV and genuine OpenXML `.xlsx` files. Native OpenXML generator using PHP's standard `ZipArchive` producing real `.xlsx` workbooks with bold shaded headers. Reads both inline strings and `xl/sharedStrings.xml` tables across uploaded `.xlsx` files. Injects UTF-8 BOM (`\xEF\xBB\xBF`) into CSVs so Excel renders Bengali and multilingual characters without mojibake. `Spreadsheet::read($file)` automatically detects file format and returns normalized associative rows. Tested in `tests/Unit/SpreadsheetTest.php`.

### I. Printable Documents & Vector PDF Engine (`System\Data\Printable`)
- **Problem Solved:** Traditional PHP PDF generators (DomPDF, FPDF, TCPDF) break Indic/Bengali scripts completely because they lack complex OpenType text shaping.
- **Implementation:** Created `System\Data\Printable` producing high-resolution, print-optimized HTML5 documents with standard `@media print` CSS. One-click browser print/PDF trigger, plus headless browser bridge (Chrome/Edge/Chromium/wkhtmltopdf) to shape text accurately into vector PDFs.

### J. Simultaneous Multi-Database & Multi-Tenancy Engine
- **Problem Solved:** Applications and multi-tenant platforms need to query multiple distinct databases concurrently within the same request or background job.
- **Implementation:** Named PDO instances in `System\Database\Connection::$instances[$name]`. Dynamic runtime connection registration (`Connection::addConnection($name, $config)`). Dedicated connection runners (`System\Database\DBConnection`) with per-connection transaction state and savepoints (`SAVEPOINT bongo_sp_{name}_{level}`). Static DB facade `DB::connection(?string $name = null)`. QueryBuilder awareness (`$connection` property). Model connection routing (`protected static ?string $connection = 'name';` and `Model::on('name')`). Relationship connection inheritance. Connection-scoped schema (`Schema::connection('name')`). Migration targeting (`php bongo migrate --database=name`). Tested in `tests/Unit/MultiDatabaseTest.php`.

### K. Performance Tools (`php bongo benchmark`, OpCache & DB_PERSISTENT)
- `php bongo benchmark`: measures median/p95/req/s/memory/status per URL, OpCache off vs on. Use it for every performance number in documentation.
- `system/preload.php`: OpCache preloading for Linux/macOS production servers.
- `DB_PERSISTENT=true`: persistent MySQL/PostgreSQL connections; unfinished transactions are rolled back. Measured: `/shop` on PostgreSQL 85.7 → 13.4 ms.
- N+1 warning: `debug_stats()['repeated']` (same SQL 3+ times) in the debug footer and logged at shutdown for pages without a footer (debug mode only).
- `cache_for($seconds, etag:, public:)` helper: `Cache-Control`, ETag, 304 on match; private by default, adds `Vary: Cookie`.

### L. Official Package Subsystem (Pure PHP, Zero Dependencies)
- **Architecture:** Zero-dependency package manager and runtime loader (`System\Package\Installer` and `PackageManager`), SHA-256 integrity verification, safe publishing with app override priority (`resources/views/vendor/<name>/`, `lang/vendor/<name>/`), developer safety net (`--backup`, `--force`), package migration management, CLI command auto-discovery, runtime `bootstrap.php` execution, and full CLI suite (`php bongo add / package:*`).
- **Official Packages Suite (`Solayman/bongo-php-packages`)**: 20 production packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`) with 174 passing package tests (706 assertions).

### M. Demo Store Blog & TinyMCE 6 / File Manager Integration
- Full Blog section in demo application: post CRUD (`PostController`), slug generation, status workflow (`published`/`draft`), TinyMCE 6 rich text editor, Bongo File Manager popup/modal picker integration (`bongoTinyMCEPicker()`), public frontend blog feed and post view (`BlogController`), and integration tests in `BlogTest.php`.
- Multi-directory migration generation: `Migrator::make()` safely handles array migration paths, directing new migrations to application directory when package migration directories are active.

### N. HTML Sanitization, Rich-Text Stored XSS Prevention, N+1 Query Resolution & WebSocket Origin Restrictions (PR #117)
- **HTML Sanitizer (`System\Security\HtmlSanitizer`):** Zero-dependency pure-PHP sanitizer using native `DOMDocument` wrapped in a UTF-8 envelope so non-Latin/Bengali Indic scripts preserve ligatures and characters without entity corruption. Recursively strips dangerous elements (`<script>`, `<iframe>`, `<style>`, `<svg>`, `<math>`, `<object>`, `<embed>`, `<applet>`), all inline event handlers (`onload`, `onerror`, `onclick`, etc.), invalid protocols (`javascript:`, `vbscript:`, dangerous `data:`), and unsafe CSS constructs. Auto-enforces `rel="noopener noreferrer"` on `target="_blank"` links.
- **Global Helper (`clean_html()`):** Added in `app/Helpers/commonHelper.php` for seamless template and controller sanitization.
- **Blog Stored XSS Elimination:** Integrated in `App\Http\Controllers\PostController` (`store()` and `update()`) and template view `resources/views/blog/show.php` via `{!! clean_html($post->content) !!}`.
- **N+1 Query Elimination:** Eager loaded user relation via `Post::query()->with('user')` in `PostController::index()` and `BlogController::index()`.
- **WebSocket Allowed Origins CLI Flag:** Added `--origins=` / `--allowed-origins=` option to `php bongo websocket:serve`.
- **Testing:** 8 unit tests in `tests/Unit/HtmlSanitizerTest.php` (31 assertions) and integration tests in `tests/Feature/BlogTest.php`. Total test suite: **380 tests passing (1,932 assertions)** across MySQL, PostgreSQL 18, and SQLite.

### O. Official Packages Suite Security Hardening & Zero-Vulnerability Assurance (PRs #39 & #40 in bongo-php-packages)
- **`file-manager` Hardening:**
  - Blocked all executable extensions (`php`, `php3`-`php8`, `pht`, `phps`, `phtml`, `phar`, `pgif`, `inc`, `cgi`, `pl`, `py`, `asp`, `aspx`, `jsp`, `sh`, `bash`, `cmd`, `bat`, `exe`, etc.) and server configurations (`.htaccess`, `.env`, `ini`, `conf`).
  - Added null-byte injection (`\0`) protection and multi-extension execution bypass protection (`avatar.php.png`, `file.phtml.jpg`).
  - Enforced `realpath()` boundary containment checks across all filesystem operations (`createFolder`, `deleteItem`, `renameItem`, `moveItem`, `copyItem`, `saveFile`, `readFile`, `download`, `thumbnail`) ensuring no operation or symlink can escape the storage root directory.
  - Added `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'` and `X-Content-Type-Options: nosniff` headers for streaming SVG files, neutralizing SVG Stored XSS.
  - Added CRLF (`\r`, `\n`) and quote sanitization on `Content-Disposition` in download endpoint to eliminate HTTP response splitting.
  - Replaced single-quoted `htmlspecialchars()` in client JavaScript initialization with `json_encode(..., JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)` to eliminate JavaScript script injection.
- **`two-factor` Hardening:** Added `resolveUserId()` authentication guard to prevent unauthenticated guest requests from operating on default user ID 1 in production across setup, verification, and disabling.
- **`seo` Hardening:** Added `JSON_HEX_TAG | JSON_HEX_AMP` to Schema.org JSON-LD structured script generation, preventing HTML script tag breakout.
- **`api-tools` Hardening:** Encoded OpenAPI specification URL in Swagger UI template via JSON hex encoding.
- **Package Testing:** **180 tests passing (745 assertions)** across all 20 packages in `bongo-php-packages`.

---

## 6. Important CLI Commands

```bash
# Production Optimization Commands
php bongo config:cache                    # Compiles config/ and .env into storage/cache/config.php
php bongo route:cache                     # Compiles routes into storage/cache/routes.php
php bongo view:cache                      # Precompiles and lints all template views
php bongo benchmark                       # Measures request time/memory, OpCache off vs on

# Cache Clearing
php bongo config:clear                    # Clears config cache
php bongo route:clear                     # Clears route cache
php bongo view:clear                      # Clears compiled views

# Development & Operations
php bongo serve                           # Runs local dev server on http://127.0.0.1:8000
php bongo migrate                         # Runs database migrations
php bongo migrate --database=<name>       # Runs migrations targeting a specific named connection
php bongo db:seed                         # Populates database seeds
php bongo queue:work                      # Runs queue worker (QUEUE_DRIVER=database or redis)
php bongo websocket:serve [--origins=...] # Runs standalone WebSocket server with optional origin whitelist
php bongo security:check                  # Runs pre-launch security & permission checks
php bongo test                            # Runs test runner on isolated test database
php bongo make:command <Name>             # Generates custom CLI command in app/Commands/

# Package Subsystem Commands
php bongo add <name>[@ver]                # Enables in config/packages.php and installs package
php bongo package:install                 # Installs package(s) from config or argument
php bongo package:update                  # Updates package(s) with local diff safety net
php bongo package:remove <name>           # Removes package files and comments in config
php bongo package:list                    # Lists installed and available packages
php bongo package:publish <name>          # Publishes config, views, or lang to app
php bongo package:diff <name>             # Shows local modifications in packages/<name>/
php bongo package:test <name>             # Runs test suite of installed package

# Maintainer Release Commands
php bongo release:branch                  # Commits clean starter skeleton to release branch
php bongo release:build <folder>          # Builds fresh clean starter project in folder
php bongo release:package [--out=dist]    # Builds zip archive and sha256 for GitHub release
```

### P. Professional Internal DebugBar (`System\Debug\DebugBar`) & Decoupled Framework Architecture
- **Internal DebugBar (`System\Debug\DebugBar`):**
  - Integrated zero-dependency pure-PHP interactive DevTools toolbar injected in debug mode (`APP_DEBUG=true`).
  - Dark-themed floating panel with 7 comprehensive tabs:
    - `Request`: HTTP method, URI, route name, action controller, IP, user-agent, route parameters, headers, query parameters, POST payload, session data, cookies.
    - `Messages`: Live application log entries recorded in the request (`LOG.info`, `LOG.warning`, `LOG.error`) with badges and contextual data.
    - `Timeline`: Execution milestones (boot time, application runtime, total database query duration, peak memory usage).
    - `Views`: Rendered view templates hierarchy, individual render execution duration (ms), and passed data keys.
    - `Queries`: Detailed database query collector with duration (ms), syntax-highlighted SQL statements, connection name, parameter bindings, one-click copy SQL button, and automated N+1 query detection (highlighted warning badge with repeated query counts).
    - `Models`: Total models hydrated during the request grouped by model class.
    - `Cache`: Cache access events (hits, misses, writes, forget operations).
  - Right-side status indicators:
    - ⚡ OpCache active/disabled badge.
    - 🗄️ Peak memory usage (MB).
    - ⏱️ Page response time (ms).
    - Route badge with link to `/system` status.
    - Minimize / restore floating chevron pill with badge count indicators.
- **Decoupled Architecture & Dedicated Sandbox Testbed:**
  - Migrated entire e-commerce storefront demo (Product, Category, Order, OrderItem, Post models, Shop controllers, cart services, customer storefront, and feature tests) to official testbed sandbox repository (`bongo-package-and-feature-test`).
  - Streamlined `bongo-php` to a pure, high-performance starter framework containing only core engine (`system/`), authentication & security subsystem, clean starter admin dashboard, and self-contained unit and feature tests (**353 tests passing, 1,600 assertions**).

---

## 7. Development Guidelines & Rules for AI Agents

1. **Maintain Zero-Dependency Architecture**: Never install or introduce mandatory third-party Composer packages or npm dependencies.
2. **Minimal Size Principle**: Keep `system/` file count low (~127 files) and footprint ~626 KB (~17,400 lines). Do not create excessive abstraction layers.
3. **PHP 8.0+ Compatibility**: Use strict types, constructor property promotion, union types, and match expressions. Avoid PHP 8.1+ features that break PHP 8.0 support (e.g., `never` return type).
4. **Mandatory Documentation Synchronization**: Whenever ANY code change, feature, optimization, or bug fix is made, updating all relevant documentation files (`DOCUMENTATION.md`, `README.md`, `WHY_BONGO.md`, `CHANGELOG.md`, `CLAUDE.md`, and `GEMINI.md`) and `.env.example` is **MANDATORY AND HIGH PRIORITY**.
5. **PSR-12 Standard**: Run `php phpcs.phar` (rules in `phpcs.xml`); every PR must have 0 errors.
6. **Testing Rules**: Test every database a change touches (MySQL, PostgreSQL 18, SQLite). Grammar-only unit tests are not enough.
7. **Only Measured Numbers in Docs**: Never write "100%", "<15ms" or "pixel-perfect" without a reproducible measurement (`php bongo benchmark`).
8. **Never Describe Bongo with Laravel or Blade Terms**: Never use "Blade-compatible" etc. Comparison tables that name other frameworks are fine.
9. **Security Defaults**:
   - Escape or whitelist everything that reaches HTML/CSS/shell/headers.
   - Delete temporary files.
   - Never `unserialize` data that is not cryptographically signed or generated locally.
   - Mask secrets in logs.
10. **Release Branch Policy**: Every time a batch of work or a PR is merged into `master`, build and push the `release` branch immediately (`php bongo release:branch` and `git push origin refs/heads/release:release`).

---

## 8. Verified Review Status (Claude & Gemini, 2026-10-05): no re-check needed

Claude verified **all features from PRs #78–#94** on real MySQL, **PostgreSQL 18**, SQLite and Redis servers. The bugs found are fixed in #95 (database), #96 (security), #97 (PSR-12), #98 (Redis queue), docs in #99, and security/XSS/N+1 in #117. **Do not re-verify these; build on them.**

| Area (PR) | Status | Notes |
|---|---|---|
| Route caching, static fast path, autoloader (#82) | ✅ Verified, no changes needed | `toCache`/`fromCache` restore everything; tests ignore the route cache; the write is atomic. |
| Redis queue driver (#82) | ✅ Verified, **fixed in #98** | Jobs are reserved atomically (Lua) in `:reserved` and come back after a crash. Entries are HMAC-signed with APP_KEY. |
| PostgreSQL driver and grammar (#84) | ✅ Verified on PostgreSQL 18, **fixed in #95** | Per-blueprint quoting, pgsql `upsert` unique key, `DROP ... CASCADE` without superuser, `php bongo test` on pgsql. |
| Multi-database / multi-tenancy (#89) | ✅ Verified, **fixed in #95** | `setConnectionName()` is now per model class; connection routing, transactions and savepoints are correct. |
| Multi-disk storage, downloads (#87) | ✅ Verified, **fixed in #96** | Path traversal is blocked (it was correct). Fixed: symlink-safe `deleteDirectory`, safe `Content-Disposition`, `deleteAfterSend`. |
| Custom console commands (#87) | ✅ Verified, **fixed in #96** | A broken `app/Commands` file is skipped; name clashes with built-ins are reported. |
| JSON logging (#87) | ✅ Verified, **fixed in #96** | Secret-like keys are masked by pattern; log lines can't be forged. |
| Spreadsheet csv/xlsx (#88) | ✅ Verified, **fixed in #96** | CSV formula injection blocked, XML-safe text, zip-bomb limit, temp files deleted. |
| Printable HTML/PDF (#88) | ✅ Verified, **fixed in #96** | All attributes are validated or escaped; temp files deleted; Chrome output captured. |
| Code style | ✅ **PSR-12, 0 errors** (#97) | Rules in `phpcs.xml`. Check with `php phpcs.phar`. |
| Docs (#78–#94) | ✅ Corrected (#99) | Real numbers only, no "Blade" wording, correct paths, unique section numbers. |
| Package Subsystem & Official Suite (Phases A, B, C, D) | ✅ **Complete, 24 packages, 213 package tests** | Pure-PHP manager (`System\Package`), SHA-256 verification, update diff safety, app overrides, `bootstrap.php` runner, storage extensibility (`Storage::extend()`, `Storage::temporaryUrl()`), and 24 official packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`, `captcha`, `aamarpay`, `telegram`, `bangla-tools`). |
| Demo Store Blog & TinyMCE 6 / File Manager (#114+) | ✅ Verified, 3 tests | Post CRUD, TinyMCE 6 editor, Bongo File Manager modal/popup pickers, draft visibility, and array migration path support in Migrator. |
| HTML Sanitization & Stored XSS Fix (#117) | ✅ Verified, 9 tests | `HtmlSanitizer` & `clean_html()` helper, DOMDocument with UTF-8 envelope (Unicode/Bengali safe), post content sanitized in PostController & show.php, N+1 query resolved on blog listings via eager loading `with('user')`, `--origins` flag added to `websocket:serve`. |
| Package Security Hardening (PRs #39, #40 & #41) | ✅ Verified, 182 package tests | Realpath boundary containment, CRLF header sanitization, JSON hex script escaping, auth guards in two-factor, JSON-LD hex escaping in SEO, ActivityLogger trusted proxy IP resolution, FTP path normalization traversal defense, and BackupManager filename sanitization. |
| Package N+1 Elimination & Polymorphic Eager Loading (PR #42) | ✅ Verified, 182 package tests | Batched permission & role revocation via `whereIn('name', ...)` in `permission`; polymorphic subject/causer relationship resolution and `Activity::loadMorphs()` batched eager loading in `activity-log`. |
| Core Framework CRLF & HTTP Response Splitting Hardening | ✅ Verified, 2 tests | Sanitized `redirect()` helper and CORS `Access-Control-Allow-Origin` echoing by stripping `\r`, `\n`, and `\0`. |
| Internal DebugBar & Decoupled Architecture (#122, #124) | ✅ **Verified, 357 framework tests (1,606 assertions)** | Professional floating dark-theme DevTools toolbar with Request, Messages, Timeline, Views, Queries (N+1 detection), Models, Cache, OpCache, Memory, and Time widgets; decoupled starter framework with demo store moved to `bongo-package-and-feature-test`. |
| Package Suite Expansion: Captcha, AamarPay, Telegram, BanglaTools (PR #43) | ✅ **Verified, 208 package tests (853 assertions)** | 4 new production packages added: `captcha` (Turnstile, reCAPTCHA v2/v3, hCaptcha, pure-PHP math & GD image), `aamarpay` (payment gateway with TrxCheck & CLI verify), `telegram` (Bot API alerts & notifications), and `bangla-tools` (Bangla numerals, words, Taka currency, Bongabdo calendar, and Unicode normalizer). |
| Built-in Server Router & Port Versatility (PRs #125, #126, #129, #134) | ✅ **Verified** | Added `server.php` router script for dynamic routes under PHP built-in server, versatile port options (`--port 8002`, `port -8002`, `-p 8002`), dynamic host & port detection in `url()`. |
| Welcome Landing Page & Core Polish (PRs #127, #128, #129) | ✅ **Verified** | Modern responsive framework landing page at `/`, `/dashboard` protected behind auth, brand name normalized to Bongo. |
| Blueprint Composite Keys & Helper Enhancements (PRs #131, #132) | ✅ **Verified** | Added `unsignedBigInteger` and `unsignedInteger` helpers, composite and table-level primary key support to Blueprint across MySQL, SQLite, and PostgreSQL. |
| Test Runner Package Migrations Integration (PR #133) | ✅ **Verified** | Package migrations automatically discovered and executed in test database during `php bongo test`. |
| API Tools Spec Editor, Metadata & Router (PRs #44, #45, #46 in packages) | ✅ **Verified, 213 package tests (886 assertions)** | Custom OpenAPI spec merge, `@summary`/`@description`/`@tags` docblock annotations, public/docs/ collision prevention, 12 tests in `api-tools`. |
| Testbed Interactive 24-Package Showcase & Sandbox (PRs #6-#12 in testbed) | ✅ **Verified, 42 tests (449 assertions)** | Interactive package showcase consoles (`/packages`, `/packages/payments`, `/packages/bangla`, `/packages/security`, `/packages/media`, `/packages/utilities`, `/packages/api`), mock payment simulation for 6 gateways, in-browser OpenAPI editor and request sandbox. |
| Package Expansion: bd-locations, webhooks, impersonate, queue-monitor, discord (PR #48 in packages) | ✅ **Verified, 232 package tests (980 assertions)** | 5 new official packages: `bd-locations` (8 divisions, 64 districts, upazilas, postal codes, shipping zones), `webhooks` (HMAC-SHA256 signature and tolerance verification), `impersonate` (admin user impersonation session swapping), `queue-monitor` (queue dashboard and failed job management), `discord` (webhook embeds and alerts). |
| Package Expansion: toast, sweet-alert, zoom, google-meet (PR #49 in packages) | ✅ **Verified, 254 package tests (1,082 assertions)** | 4 new official packages: `toast` (flash & dynamic toasts with progress bars), `sweet-alert` (modal alerts & confirm dialogs), `zoom` (Server-to-Server OAuth meetings API & webhooks), `google-meet` (Service Account RS256 JWT Google Calendar & Meet). |
| Package Expansion: courier, invoice, whatsapp (PR #50 in packages) | ✅ **Verified, 274 package tests (1,155 assertions)** | 3 new official packages: `courier` (Bangladesh Steadfast & Pathao logistics, order creation, live tracking, webhooks), `invoice` (print-ready A4 CSS and 80mm/58mm thermal POS receipts), `whatsapp` (Meta Cloud API v20.0 transactional messaging, OTP templates, media & webhooks). |
| Package Expansion: currency, coupon, push-notification, geoip (PR #51 in packages) | ✅ **Verified, 299 package tests (1,239 assertions)** | 4 new official packages: `currency` (multi-currency conversion, live exchange rates, BDT/USD formatting), `coupon` (promotions, vouchers, discount calculation), `push-notification` (Web Push VAPID RFC 8292 & FCM v1), `geoip` (IP address geolocation & country/currency/timezone detection). |

**Current framework verification totals:**
- Core framework tests: **357 tests passing (1,606 assertions)** across MySQL, PostgreSQL 18, and SQLite.
- System metrics: **128 modular files, ~17,900 lines, ~640 KB** in `system/`.
- Official package suite: **40 packages, 299 passing package tests (1,239 assertions)**.
- Testbed sandbox: **42 feature tests passing (449 assertions)**.
