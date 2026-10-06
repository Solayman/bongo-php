# CLAUDE.md — Architecture & Review Guide for Bongo PHP Framework

This document is prepared for **Claude AI** (and other internal reviewers and agents) to understand the framework's architecture, recent feature implementations, strict operational constraints, and technical roadmap during this testing stage.

---

## 1. Project Overview & Core Philosophy

**Bongo PHP Framework** is a lightweight, high-performance PHP MVC framework built from first principles with **zero required external dependencies**.

* **Size & Footprint:** ~15,000 lines across 127 modular files in `system/` (~616 KB total footprint).
* **Starter Skeleton:** ~800 KB across 183 files on the `release` branch (clean blank slate for direct end-users; zero demo clutter).
* **Resource Utilization:** About **0.67–0.83 MB per request** and **7–13 ms per page with OpCache** (`php bongo benchmark`, Windows, built-in server; see DOCUMENTATION.md "Performance").
* **Zero Dependencies:** No mandatory Composer packages, no Node.js runtime, no asset compilation steps. Pure PHP 8.0+.
* **Auditable Architecture:** Written in readable, single-responsibility PHP modules averaging about 135 lines per file (largest modules 500–900 lines).
* **Batteries Included:** Routing, @-directive templates, Active Record ORM (MySQL, PostgreSQL & SQLite), Multi-guard Auth (Session & JWT), Multi-disk Storage, Packages (`System\Package`), Queues (Sync, Database & Redis), In-process Scheduler, pure-PHP WebSockets, SMTP Mailer & Mailbox UI, Notifications, and automated security auditing.

---

## 2. Recent Implementations & Framework Optimizations

The following high-priority features and optimizations were recently implemented and verified:

### A. Route Caching (`php bongo route:cache` / `route:clear`)
* **Problem Solved:** Dynamically parsing route files (`routes/web.php`, `routes/api.php`) and evaluating regex rules on every HTTP request adds latency as route counts grow.
* **Implementation:**
  * Added `Router::toCache()` to export serializable route definitions, middleware groups, and aliases into `storage/cache/routes.php`.
  * Added `Router::fromCache()` to restore the router instantly in production.
  * Added `ROUTE_CACHE` constant and CLI commands (`php bongo route:cache` and `php bongo route:clear`).
  * Safety: Validates that all route actions are serializable `[Controller::class, 'method']` arrays; throws a clear `RuntimeException` if closure routes are detected.
  * Refactored `/api/health` from an inline closure to `App\Http\Controllers\Api\HealthController` so 100% of framework routes can be cached.
  * Unit tested in `tests/Unit/RouteCacheTest.php`.

### B. Redis Queue Driver (`QUEUE_DRIVER=redis`)
* **Problem Solved:** Database queues (`QUEUE_DRIVER=database`) incur row-locking overhead on MySQL/SQLite under high concurrency.
* **Implementation:**
  * Pure-PHP Redis driver integration in `System\Queue\Queue::push()` and `System\Queue\Worker`.
  * Ready jobs are pushed to Redis lists (`RPUSH` / `LPOP`).
  * Delayed jobs are scheduled using Redis sorted sets (`ZADD` / `ZRANGEBYSCORE`). The worker automatically migrates due delayed jobs into the main queue before popping.
  * Unified Failure Handling: When jobs fail permanently, error details are stored in the database `failed_jobs` table. This allows `php bongo queue:failed`, `queue:retry`, and `queue:flush` to work seamlessly across both `database` and `redis` drivers.
  * Unit tested in `tests/Unit/RedisTest.php`.

### C. Framework Runtime Optimizations
* **Static Route Fast-Path:** Updated `RouteDefinition::match()` to check `$this->path === $path` for routes without dynamic `{parameters}`. This skips regular expression execution for ~80% of standard application routes.
* **Autoloader Speedup:** Replaced array iteration and runtime `strlen()` in `config/bootstrap.php` with direct prefix checks (`strncmp('App\\', ...)` and `strncmp('System\\', ...)`) and `is_file()` lookups.

### D. Native PostgreSQL Database Driver & Schema Grammar (`DB_CONNECTION=pgsql`)
* **Problem Solved:** Enterprise environments and high-concurrency cloud deployments frequently require PostgreSQL instead of MySQL or SQLite.
* **Implementation:**
  * **Driver Connection:** Added `connectPgsql()` and `isPgsql()` to `System\Database\Connection` with schema search path and timezone alignment.
  * **PostgresGrammar:** Created `System\Database\Schema\PostgresGrammar` handling `BIGSERIAL PRIMARY KEY`, `BOOLEAN`, `JSONB`, `VARCHAR(...) CHECK (...)` for enums, double-quote identifier escaping (`"table"."col"`), column comments, and automatic trigger functions for `useCurrentOnUpdate()`.
  * **Schema Integration:** Updated `Schema::rename()` (`ALTER TABLE ... RENAME TO`), `hasColumn()` and `tables()` using PostgreSQL's `information_schema`, and foreign key bypasses via `session_replication_role = 'replica'`.
  * **Atomic Inserts & Upsert:** Updated `QueryBuilder::insertGetId()` with atomic `RETURNING "id"` clause; updated `upsert()` with `ON CONFLICT (...) DO UPDATE SET ...` with `EXCLUDED` references; updated `sharedLock()` to `FOR SHARE`.
  * **Migration State:** Updated `Migrator::ensureTable()` with PostgreSQL `SERIAL PRIMARY KEY` tracking table.
  * **Unit Testing:** Verified via `tests/Unit/PostgresSchemaTest.php`.

### E. Multi-Disk File Storage (`Storage::disk('local')` vs `Storage::disk('public')`)
* **Problem Solved:** Applications previously saved files only to the web-accessible `public/storage` folder. Private documents (invoices, export CSVs, IDs) were at risk of unintentional public exposure.
* **Implementation:**
  * **Disk Architecture:** Created `System\Storage\Disk` representing isolated storage mounts with full filesystem operations (`put`, `get`, `exists`, `missing`, `size`, `lastModified`, `delete`, `url`, `download`, `files`, `allFiles`, `makeDirectory`, `deleteDirectory`).
  * **Configured Disks:** Configured `public` (rooted at `public/storage`, web accessible via `/storage/...`) and `local` (rooted at `storage/app`, completely private outside document root) in `config/storage.php`.
  * **Streaming File Downloads:** Created `System\Storage\DownloadResponse` with HTTP headers (`Content-Disposition`, `Content-Length`, `Content-Type`) and streaming via `readfile()`. Integrated into `Router::send()`.
  * **Uploaded File Targeting:** Updated `UploadedFile::store($dir, $disk = null)` allowing uploaded files to be directly stored to private or public disks.
  * **Unit Testing:** Verified in `tests/Unit/StorageTest.php` (4 tests, 26 assertions).

### F. Custom Console Commands Engine (`make:command` & Auto-Discovery)
* **Problem Solved:** Extending the CLI previously required hacking the core `bongo` binary.
* **Implementation:**
  * **Command Base Class:** Created `System\Console\Command` base class with `handle(array $args, array $options): int`, and styled terminal output helpers (`$this->info()`, `$this->warn()`, `$this->error()`, `$this->line()`). Supports both `$name` and `$signature`.
  * **CLI Generator:** Added `php bongo make:command <Name>` generator in `System\Console\Generator::command()`.
  * **Automatic Discovery:** The `bongo` CLI dynamically scans `app/Commands/*.php`, instantiates subclasses of `System\Console\Command`, lists them in `php bongo help`, and executes them with parsed options (`--flag`, `--key=val`).
  * **Unit Testing:** Verified in `tests/Unit/CommandTest.php` (2 tests, 11 assertions).

### G. Structured JSON Logging (`LOG_FORMAT=json`)
* **Problem Solved:** Containerized environments (Docker, Kubernetes) and centralized log collectors (Datadog, CloudWatch, Loki/ELK) require single-line JSON logs rather than bracketed text logs.
* **Implementation:**
  * Added `format` option to `System\Log\Logger` (`LOG_FORMAT=text` or `LOG_FORMAT=json`), configurable in `config/logging.php` and `.env`.
  * In JSON mode, outputs newline-delimited JSON objects containing `timestamp`, `env`, `level`, `message`, sanitized/masked `context`, and structured `exception` data (`class`, `message`, `code`, `file`, `line`, and string array `trace`).
  * Updated `Logger::recent()` to seamlessly parse both structured JSON log lines and legacy bracketed text log lines.
  * Unit tested in `tests/Unit/LoggerTest.php` (12 tests, 43 assertions).

### H. Unified Spreadsheet Engine (`Spreadsheet::read()` & `Spreadsheet::download()`)
* **Problem Solved:** Developers and operations teams frequently need to import/export tabular data in both CSV and Microsoft Excel (.xlsx), but traditional solutions require massive 40MB packages (PhpSpreadsheet) and duplicate code for each format.
* **Implementation:**
  * Created `System\Data\Spreadsheet` providing a single, unified interface for both CSV and genuine OpenXML `.xlsx` files.
  * Native OpenXML generator using PHP's standard `ZipArchive` producing real `.xlsx` workbooks with bold shaded headers.
  * Reads both inline strings and `xl/sharedStrings.xml` tables across uploaded `.xlsx` files.
  * Injects UTF-8 BOM (`\xEF\xBB\xBF`) into CSVs so Excel renders Bengali and multilingual characters without mojibake.
  * Single `Spreadsheet::read($file)` automatically detects file format and returns normalized associative rows.
  * Unit tested in `tests/Unit/SpreadsheetTest.php` (3 tests, 21 assertions).

### I. Printable Documents & Vector PDF Engine (`System\Data\Printable`)
* **Problem Solved:** Traditional PHP PDF generators (DomPDF, FPDF, TCPDF) break Indic/Bengali scripts completely because they lack complex OpenType text shaping (ligatures/যুক্তাক্ষর split and vowel marks/কার render backwards or as question marks).
* **Implementation:**
  * Created `System\Data\Printable` producing high-resolution, print-optimized HTML5 documents with standard `@media print` CSS.
### J. Simultaneous Multi-Database & Multi-Tenancy Engine
* **Problem Solved:** Applications, microservices, and multi-tenant SaaS platforms frequently need to query multiple distinct databases concurrently within the same HTTP request or background job (e.g. master DB + tenant DB, transactional MySQL + analytics PostgreSQL).
* **Implementation:**
  * **Connection Registry (`System\Database\Connection`):** Caches named PDO instances in `self::$instances[$name]` and resolves dialect drivers (`mysql`, `pgsql`, `sqlite`) per connection. Added dynamic runtime connection registration (`Connection::addConnection($name, $config)`) and default connection switcher (`setDefaultConnection`).
  * **Dedicated Connection Runners (`System\Database\DBConnection`):** Connection-scoped query runner with per-connection transaction state and savepoints (`SAVEPOINT bongo_sp_{name}_{level}`).
  * **Static DB Facade (`System\Database\DB`):** Added `DB::connection(?string $name = null)` returning `DBConnection`, and updated query logging to record the originating connection for every executed query.
  * **QueryBuilder Connection Awareness (`System\Database\QueryBuilder`):** Added `$connection` property, routing all raw queries, model hydrations, row locks, offset limits, and upserts to the specific connection driver. Added `create()` method on builder for model creation on target connection.
  * **Model Connection Routing (`System\Database\Model`):** Supports `protected static ?string $connection = 'tenant'` for static model routing, `Model::on('tenant')` for dynamic query targeting, and instance-level `$model->getConnection()` / `$model->setConnection()` so retrieved models save back to their original database.
  * **Relationship Inheritance (`System\Database\Relation`):** Child relationships (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`) automatically inherit the parent model's connection when the related model does not specify its own connection.
  * **Connection-Scoped Schema (`System\Database\Schema\SchemaBuilder` & `Schema`):** `Schema::connection('tenant')->create(...)`, `table()`, `drop()`, `hasTable()`, and `withoutForeignKeyChecks()`.
  * **Migration CLI Flag:** `php bongo migrate --database=tenant`, `migrate:status`, `migrate:rollback`, `migrate:fresh`, and `db:seed`.
  * **Unit Testing:** 7 unit tests in `tests/Unit/MultiDatabaseTest.php` (30 assertions). Full test suite at the time: **349 passed (1,780 assertions)**.

### K. Performance Tools (Claude, 2026-10-04; tested on MySQL, PostgreSQL, SQLite: 353 pass)
* `php bongo benchmark` (`System\Console\Benchmark`): measures median/p95/req/s/memory/status per URL, OpCache off vs on. **Use it for every performance number in the docs.** Git Bash: prefix `MSYS_NO_PATHCONV=1`.
* `system/preload.php`: OpCache preloading (Linux/macOS only; Windows PHP has none, so its gain is unmeasured).
* `DB_PERSISTENT=true` (`Connection::options()`/`cleanPersistent()`): persistent MySQL/PostgreSQL connections; leftover transactions are rolled back. Measured: `/shop` on PostgreSQL 85.7 → 13.4 ms (PostgreSQL connect is ~60 ms on Windows).
* N+1 warning: `debug_stats()['repeated']` (same SQL 3+ times) in the debug footer, and logged at shutdown for pages without a footer (debug mode only).
* `cache_for($seconds, etag:, public:)` helper: `Cache-Control`, ETag, 304 on match; private by default, adds `Vary: Cookie`.
* `ThrottleRequests` skips the session-guard user lookup when no session is started (API routes).
* Known, not a framework bug: on Windows, Defender scanning adds ~10 ms to each file-cache write (rate limiter). Documented; Linux is unaffected.

### L. Official Package Subsystem (Pure PHP, Zero Dependencies)
* **Problem Solved:** Providing modular first-party and regional packages (such as payment gateways like SSLCommerz and bKash, SMS gateways, and cloud storage drivers) without introducing Composer dependencies or runtime overhead to the framework core.
* **Implementation:**
  * **Installer (`System\Package\Installer`):** Pure-PHP catalog resolution, downloading/extraction, SHA-256 integrity calculation and verification, path traversal prevention (`..`, absolute paths, symlinks), and safe publishing with application override priority.
  * **Update & Remove Safety Net:** Detects local file edits inside `packages/<name>/` via SHA-256 hash comparison against `packages/.lock.json`. Stops updates and removal unless `--backup` (saves to `storage/package-backups/<name>-<timestamp>/`) or `--force` is provided.
  * **Runtime Loader (`System\Package\PackageManager`):** Zero-cost boot hook (`PackageManager::boot()`) costing a single `is_file()` check on `packages/.lock.json`. Automatically registers view namespaces (`name::view`), translation namespaces (`name::key`), and discovers package console commands.
  * **Application Overrides:** `resources/views/vendor/<name>/` and `lang/vendor/<name>/` take precedence over package assets, surviving updates.
  * **CLI Commands:** `add`, `package:install`, `package:update`, `package:remove`, `package:list`, `package:publish`, `package:diff`, and `package:test`.
  * **Storage Extensibility & Bootstrapping:** Package `bootstrap.php` runner on framework boot; `Storage::extend()`, `Storage::set()`, and `Storage::temporaryUrl()`.
  * **Official Packages Suite (`Solayman/bongo-php-packages`):** 20 production packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`) with 174 passing package tests (706 assertions).
  * **Testing:** 11 unit tests in `tests/Unit/PackageTest.php` (43 assertions), 6 tests in `tests/Unit/StorageTest.php` (31 assertions). Full core test suite: **371 tests passing (1888 assertions) across MySQL, PostgreSQL 18, and SQLite**.

### M. Demo Store Blog & TinyMCE 6 / File Manager Integration
* **Problem Solved:** Demonstrating end-to-end rich content authoring in the application layer utilizing Bongo's official `file-manager` package with WYSIWYG modal and popup pickers.
* **Implementation:**
  * **Admin Posts Controller (`App\Http\Controllers\PostController`):** Full post CRUD lifecycle with slug generation, status management (`published`/`draft`), and TinyMCE 6 rich text integration.
  * **Public Blog Controller (`App\Http\Controllers\BlogController`):** Public frontend blog feed at `/blog` and post reader at `/blog/{slug}` with draft protection.
  * **TinyMCE Connector (`bongoTinyMCEPicker()`):** Seamless file manager popup callback integration returning selected asset URLs into TinyMCE image and media dialogs.
  * **Migrator Fix (`System\Database\Migrator::make()`):** Added support for array migration paths to safely generate new migrations into the primary application directory when package migration directories are loaded.
### N. HTML Sanitization, Rich-Text Stored XSS Prevention, N+1 Query Resolution & WebSocket Origin Restrictions (PR #117)
* **Problem Solved:** User-submitted rich text in blog posts and CMS features risked Stored Cross-Site Scripting (XSS) if raw HTML was rendered, while third-party HTML purifiers add heavyweight dependencies. In addition, blog post feeds suffered from N+1 user queries, and the WebSocket server lacked command-line origin restrictions.
* **Implementation:**
  * **HTML Sanitizer (`System\Security\HtmlSanitizer`):** Zero-dependency pure-PHP sanitizer using native `DOMDocument` wrapped in a UTF-8 envelope so non-Latin/Bengali Indic scripts preserve ligatures and characters without entity corruption. Recursively strips dangerous elements (`<script>`, `<iframe>`, `<style>`, `<svg>`, `<math>`, `<object>`, `<embed>`, `<applet>`), all inline event handlers (`onload`, `onerror`, `onclick`, etc.), invalid protocols (`javascript:`, `vbscript:`, dangerous `data:`), and unsafe CSS constructs. Auto-enforces `rel="noopener noreferrer"` on `target="_blank"` links.
  * **Global Helper (`clean_html()`):** Added in `app/Helpers/commonHelper.php` for seamless template and controller sanitization.
  * **Blog Stored XSS Elimination:** Integrated in `App\Http\Controllers\PostController` (`store()` and `update()`) and template view `resources/views/blog/show.php` via `{!! clean_html($post->content) !!}`.
  * **N+1 Query Elimination:** Eager loaded user relation via `Post::query()->with('user')` in `PostController::index()` and `BlogController::index()`.
  * **WebSocket Allowed Origins CLI Flag:** Added `--origins=` / `--allowed-origins=` option to `php bongo websocket:serve`.
  * **Testing:** 8 unit tests in `tests/Unit/HtmlSanitizerTest.php` (31 assertions) and integration tests in `tests/Feature/BlogTest.php`. Total test suite: **380 tests passing (1,932 assertions)** across MySQL, PostgreSQL 18, and SQLite.

---

## 3. Strict Git & Branching Rules

When interacting with this codebase, you **must adhere to these rules**:

| Branch | Rule & Status |
|---|---|
| **`master`** | Production-ready code. Pull requests target this branch. |
| **`solayman`** | Active development branch. All commits and pushes must go to `solayman`. |
| **`release`** | Clean starter skeleton branch for end-users to clone. Built via `php bongo release:branch` and pushed via `git push origin release`. Never commit development code directly to it. |

### Multi-Repository Architecture & PR/MR Workflow:
* **Repository Landscape (`D:\laravel\framework\`):**
  * `bongo-php`: Core framework repository (`solayman` branch for development, `master` for production).
  * `bongo-php-packages`: Official package suite repository (all 20 packages).
  * `bongo-package-and-feature-test`: Official feature development and package integration testbed. **RULE: Always implement and test new application features here.**
  * `BongoMvc`: Junction link to `bongo-php` for package test runners.

* **Full MR & Merge Access Policy:**
  * The AI agent is authorized to make Pull Requests / Merge Requests (MRs) and merge them.
  * **Always make MR with `solayman` branch and merge the request.**
  * **GitHub Token:** Check `.env` across all repos for `GITHUB_TOKEN` (Fine-grained PAT) for API-based PR creation and merges.

* **Core Framework Workflow (`Solayman/bongo-php`)**:
  1. Make changes on the local `solayman` branch.
  2. Push to `origin/solayman`.
  3. Open a Pull Request targeting `master` from `solayman` (via GitHub API or `gh pr create`).
  4. Merge the Pull Request immediately (`merge_method: merge`).
  6. **ALWAYS Update the `release` branch immediately** after every merge to `master`: run `php bongo release:branch` and `git push origin refs/heads/release:release` (the `release` branch is the fresh starter skeleton that end-users clone for clean projects).
  7. Synchronize `bongo-package-and-feature-test` with `git pull origin release`.

* **Official Packages Workflow (`Solayman/bongo-php-packages`)**:
  - **RULE: If any package needs an MR, go with `bongo-php-packages`.**
  - Always follow Pull Requests in the package repository: never commit/push directly to `master`.
  1. Create a dedicated feature or working branch (e.g. `solayman` or `feat/<package-name>`).
  2. Commit and push to origin (`git push origin <branch>`).
  3. Open a Pull Request targeting `master`: `gh pr create --repo Solayman/bongo-php-packages --base master --head <branch>` (or via GitHub REST API).
  4. Merge the Pull Request immediately.
  5. Synchronize local `master` and working branches.


---

## 4. Documentation & Memory Policy (CRITICAL - HIGH PRIORITY)

Whenever ANY code change, feature, optimization, or bug fix is made to Bongo:
- **Updating documentation is MANDATORY AND HIGH PRIORITY.**
- All relevant documentation and environment files must be updated in tandem with code changes:
  - `.env.example`: Comprehensive environment template with comments for every driver, setting, and feature.
  - `DOCUMENTATION.md`: Full technical reference, configuration, and syntax examples.
  - `README.md`: Feature matrices, quick start guide, performance benchmarks, and system requirements.
  - `WHY_BONGO.md`: Architectural boundaries, comparison tables, and design principles.
  - `CHANGELOG.md`: Unreleased records following Keep a Changelog / SemVer.
  - `CLAUDE.md`: Peer-agent briefing and architectural review notes.
  - `GEMINI.md`: Core system instructions and agent memory.

---

## 5. Important CLI Commands

```bash
# Production Optimization Commands
php bongo config:cache     # Compiles config/ and .env into storage/cache/config.php
php bongo route:cache      # Compiles routes into storage/cache/routes.php
php bongo view:cache       # Precompiles and lints all template views
php bongo benchmark        # Measures request time/memory, OpCache off vs on

# Cache Clearing
php bongo config:clear     # Clears config cache
php bongo route:clear      # Clears route cache
php bongo view:clear       # Clears compiled views

# Development & Operations
php bongo serve            # Runs local dev server on http://127.0.0.1:8000
php bongo migrate          # Runs database migrations
php bongo db:seed          # Populates database seeds
php bongo queue:work       # Runs queue worker (QUEUE_DRIVER=database or redis)
php bongo websocket:serve [--origins=...] # Standalone WebSocket server on port 6001 with origin filter
php bongo security:check   # Runs pre-launch security & permission checks
php bongo test             # Runs test runner on isolated test database
php bongo make:command <X> # Generates custom CLI command in app/Commands/

# Package Subsystem Commands
php bongo add <name>[@ver]     # Enables in config/packages.php and installs package
php bongo package:install      # Installs package(s) from config or argument
php bongo package:update       # Updates package(s) with local diff safety net
php bongo package:remove <x>   # Removes package files and comments in config
php bongo package:list         # Lists installed and available packages
php bongo package:publish <x>  # Publishes config, views, or lang to app
php bongo package:diff <x>     # Shows local modifications in packages/<name>/
php bongo package:test <x>     # Runs test suite of installed package
```

---

## 6. Development Guidelines for AI Agents

1. **Maintain Zero-Dependency Architecture:** Never install or introduce mandatory third-party Composer packages or npm dependencies.
2. **Minimal Size Principle:** Keep `system/` file count low (~127 files) and footprint ~616 KB (~15,000 lines). Do not create excessive abstraction layers.
3. **PHP 8.0+ Compatibility:** Use strict types, constructor property promotion, union types, and match expressions. Avoid PHP 8.1+ features that break PHP 8.0 support (e.g., `never` return type).
4. **Update Documentation:** Always update `DOCUMENTATION.md`, `README.md`, `WHY_BONGO.md`, and `GEMINI.md` when introducing new features or commands.
5. **PSR-12:** run `php phpcs.phar` (rules in `phpcs.xml`); every PR must have 0 errors.
6. **Testing Stage Memory:** Maintain `CLAUDE.md` up-to-date across all development cycles during the pre-release testing phase.

---

## 7. Verified Review Status (Claude, 2026-10-04): no re-check needed

Claude reviewed **every feature from PRs #78–#94** line by line and ran the full test suite on **real servers**: MySQL, **PostgreSQL 18** (first real-server run) and SQLite, plus a real Redis server for the Redis tests. All findings are fixed and merged. Agents **do not need to re-verify** the items below; build on them.

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
| Code style | ✅ **PSR-12, 0 errors** (#97) | Rules in `phpcs.xml`. Check with `php phpcs.phar` (see CONTRIBUTING). |
| Docs (#78–#94) | ✅ Corrected (#99) | Real numbers only, no "Blade" wording, correct paths, unique section numbers. |
| Package Subsystem & Official Suite (Phases A, B, C, D) | ✅ **Complete, 20 packages, 180 package tests** | Pure-PHP manager (`System\Package`), SHA-256 verification, update diff safety, app overrides, `bootstrap.php` runner, storage extensibility (`Storage::extend()`, `Storage::temporaryUrl()`), and 20 official packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`). |
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

**Test results at review end:** 357 tests pass on MySQL, PostgreSQL 18 and SQLite (1,606 assertions). 232 package tests pass across all 29 packages in bongo-php-packages (980 assertions). 42 feature tests pass in bongo-package-and-feature-test (449 assertions).

### Rules learned from this review (apply to all future work)
1. **Test on every database a change touches:** `DB_CONNECTION=pgsql DB_PORT=5432 DB_USERNAME=postgres DB_PASSWORD=... php -d extension=pdo_pgsql bongo test`, plus SQLite (`DB_CONNECTION=sqlite php -d extension=pdo_sqlite bongo test`) and MySQL. Grammar-only unit tests are not enough.
2. **PSR-12 with 0 errors** (`php phpcs.phar`) before every PR.
3. **Only measured numbers in docs.** No "100%", "<15ms" or "pixel-perfect" without a reproducible measurement. Recount files, lines and tests after changes. Current: 128 files, ~17,900 lines, ~640 KB in `system/`; 357 tests.
4. **Never describe Bongo with Laravel or Blade terms** ("Blade-compatible" etc.). Comparison tables that *name* other frameworks are fine.
5. **Security defaults for new code:**
   - escape or whitelist everything that reaches HTML/CSS/shell/headers;
   - delete temp files;
   - never `unserialize` data that isn't signed or generated locally;
   - mask secrets in logs.
6. After a batch of work is merged, **update the `release` branch** (`php bongo release:branch` and `git push origin release`, with no tag).
