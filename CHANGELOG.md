# Changelog

Every notable change to the Bongo PHP Framework. Versions follow [semantic versioning](https://semver.org): while in 0.x, a minor version (0.10 → 0.11) may contain breaking changes, and [UPGRADE.md](UPGRADE.md) explains them.

## 1.0.1 — 2026-10-31

### Fixed & Optimized
- **MySQL Composite Unique Key Detection in QueryBuilder::upsert()**:
  - Enhanced QueryBuilder::upsert() for MySQL to automatically inspect table index metadata (SHOW INDEX FROM <table> WHERE Non_unique = 0) to detect composite unique indexes matching the uniqueBy columns.
  - Automatically generates compound ON DUPLICATE KEY UPDATE statements, preventing duplicate key collision errors on complex multi-column constraints.

## 1.0.0 — 2026-10-30

### Security Hardening & Audit Remediations
- **File Manager Authorization & RCE Mitigation (`packages/file-manager`)**:
  - Enforced `['web', 'auth']` middleware by default across all file manager routes, preventing unauthenticated access.
  - Hardened code editor save flow and upload validation in `PathSecurity` and `FileManagerService`: banned executable extensions (`php`, `php[0-9]`, `phtml`, `phar`, `pht`, `shtml`), blocked multi-extension execution bypasses (`.php.jpg`), restricted editable file types, and added `X-Content-Type-Options: nosniff` with strict MIME checking on thumbnail endpoints.
- **ORM & Database Protection**:
  - `QueryBuilder::delete()`: Fixed condition handling so non-empty numeric strings properly scope deletion to `WHERE id = ?`, eliminating table-wide deletion hazards.
  - `Model` Mass-Assignment: Implemented attribute key normalization stripping quotes, backticks, brackets, and table aliases with case-insensitive checks against `$guarded` and `$fillable`.
  - Relationship Safety: Prevented destructive internal model methods (`delete()`, `save()`, `forceDelete()`) from execution via `with()` or `withCount()`.
  - Pluck Injection Prevention: Sanitized column identifiers via `QueryBuilder::column()`.
- **Authentication & Session Hardening**:
  - Host Header Poisoning: Strict RFC hostname verification on incoming `Host` headers and restricted dynamic password-reset URL generation to verified local addresses or configured `app.url`.
  - Guard Isolation for Remember Cookies: Verified user roles against the target guard context to prevent privilege escalation via cookie tampering.
  - Credential Revocation: Cleared `remember_token` on password change and reset to log out all other active sessions immediately.
  - Hardened JWT Logout Revocation: Expanded revocation TTL window to cover `max(exp, windowEnds)` where `windowEnds = iat + refresh_ttl`, preventing expired session reuse.
- **HTTP Layer, Storage & WebSockets**:
  - Validation Traversal: Prioritized nested dot-path resolution over flat dotted keys to prevent validation shadows and ensure verified input integrity.
  - CORS Credential Reflection: Stopped wildcard `*` from reflecting arbitrary request origins when `supports_credentials = true`.
  - Storage Traversal Prevention: Blocked directory escape sequences (`..`) in FTP and SFTP filesystem drivers with `InvalidArgumentException`.
  - WebSocket Server Resiliency: Added payload length bounds validation in `Frame::decode()` and set non-blocking stream writes to disconnect stalled clients cleanly without hanging the broadcast server.
  - Environment Security: Configured `APP_DEBUG` default to true strictly when `APP_ENV` is `local` or `testing`. Added demo account checks to `SecurityCheck`.

### Added
- **Internal Professional DevTools DebugBar (`System\Debug\DebugBar`) & `@debugbar` Template Directive**: Zero-dependency, dark-themed floating developer toolbar built directly into the framework core for real-time runtime inspection when `APP_DEBUG=true`. Features 7 inspection tabs: **Request** (method, path, action, headers, cookies, input), **Messages** (`Logger::getRecorded()` with severity pills), **Timeline** (boot duration, database query time, view render time, peak memory), **Views** (rendered templates hierarchy and passed variables), **Queries** (syntax-highlighted SQL, execution duration, copy query, and automated **N+1 repetition counters** flagging queries executed 3+ times), **Models** (hydrated model counts), and **Cache** (hits, misses, writes). Right-side status widgets include `⚡ OpCache` status, `🗄️ {RAM} MB`, `⏱️ {Time} ms`, and link to `/system`.
- **Official Packages Suite Expansion to 24 Packages (`Solayman/bongo-php-packages`)**:
  - `captcha` (1.0.0): Universal anti-bot human verification suite (Cloudflare Turnstile, Google reCAPTCHA v2/v3, hCaptcha, pure-PHP offline math & GD image challenges with replay protection and `@captcha` / `@captchascript` directives).
  - `aamarpay` (1.0.0): AamarPay payment gateway for Bangladesh with sandbox/live modes, MFS support (bKash, Nagad, Rocket, Upay), card processing, `TrxCheck` server validation, pre-built views, and `php bongo aamarpay:check` CLI command.
  - `telegram` (1.0.0): Real-time Telegram Bot notifications, rich system alerts with emojis and JSON details, channel broadcasts, `Telegram::fake()` unit testing, and `php bongo telegram:send` / `telegram:test` CLI utilities.
  - `bangla-tools` (1.0.0): Authentic Bengali linguistic toolkit (English <-> Bengali digit conversion, number-to-words engine with কোটি/লাখ/হাজার/শত, South Asian Taka currency notation, Bongabdo calendar converter according to Bangla Academy standards, Unicode cleaner, and SEO slug generator).
  - Package test suite expanded to **208 tests passing (853 assertions)** across all 24 packages with 0 errors.
- **Decoupled Framework Core & Sandbox Migration**: Decoupled full e-commerce store demo application, customer portal, and storefront feature tests to the official sandbox repository [`Solayman/bongo-package-and-feature-test`](https://github.com/Solayman/bongo-package-and-feature-test), leaving `bongo-php` clean with starter authentication, dashboard, and system health status. Core framework tests: **353 tests passing (1,600 assertions)**.
- **HTML Sanitization Subsystem (`System\Security\HtmlSanitizer`) & `clean_html()` Global Helper**: Pure-PHP, zero-dependency HTML sanitizer powered by native `DOMDocument` with UTF-8 envelope preservation for multilingual/Bengali scripts. Eliminates Stored Cross-Site Scripting (XSS) by stripping dangerous tags (`<script>`, `<iframe>`, `<style>`, `<svg>`, `<math>`, `<object>`, `<embed>`, `<applet>`), inline event attributes (`onload`, `onerror`, `onclick`), dangerous protocols (`javascript:`, `vbscript:`, unsafe `data:`), and CSS injection hacks (`expression`, `url()`), while preserving safe formatting, media (`img`), tables, and auto-enforcing `rel="noopener noreferrer"` on `target="_blank"` links.
- **WebSocket Allowed Origins CLI Parameter**: Added `--origins=` / `--allowed-origins=` option to `php bongo websocket:serve` for deterministic command-line origin filtering across all operating systems.

### Fixed & Optimized
- **Blog Rich-Text XSS Vulnerability Fix**: Sanitized blog post contents in `App\Http\Controllers\PostController` (`store()` and `update()`) and rendered securely via `{!! clean_html($post->content) !!}` in `resources/views/blog/show.php`.
- **N+1 Query Resolution**: Optimized blog post queries in `PostController::index()` and `BlogController::index()` with eager loading (`Post::query()->with('user')`), preventing redundant user lookup queries during post listing.
- **Official Package Subsystem** (`System\Package`): pure-PHP, zero-dependency package manager and runtime loader (`Installer` and `PackageManager`) supporting official catalog installation, lockfile tracking (`packages/.lock.json`), SHA-256 integrity checks, update safety net (`--backup` to `storage/package-backups/`, `--force`), namespaced views and translations with priority application overrides (`resources/views/vendor/<name>/`, `resources/lang/vendor/<name>/`), package routes integration with route caching support, multi-directory package migration discovery in `php bongo migrate` / `migrate:status` / `migrate:rollback`, package CLI command auto-discovery, package test runner integration, and CLI commands:
  - `php bongo add <name>[@version]`
  - `php bongo package:install [<name>[@version]]`
  - `php bongo package:update [name] [--backup] [--force]`
  - `php bongo package:remove <name> [--force]`
  - `php bongo package:list`
  - `php bongo package:publish <name> [--config] [--views] [--lang]`
  - `php bongo package:diff <name>`
  - `php bongo package:test <name>`
- **Official Bongo Packages Suite** (`Solayman/bongo-php-packages`):
  - `sslcommerz` (1.0.0): SSLCommerz payment gateway for Bangladesh with token validation, sandbox/live modes, IPN handling, checkout views, translations, and `php bongo sslcommerz:check`.
  - `bkash` (1.0.0): bKash Tokenized Checkout payment gateway with token caching, payment execution, query, refund, checkout views, translations, and `php bongo bkash:check`.
  - `sms` (1.0.0): Multi-driver SMS gateway (BulkSMSBD, SSL Wireless, Alpha SMS, and log driver) with 6-digit cryptographic phone OTP generation, attempt throttling, verification, views, and natural Bengali localization.
  - `s3` (1.0.0): Pure-PHP AWS Signature Version 4 (SigV4) object storage disk for Amazon S3, DigitalOcean Spaces, Cloudflare R2, and MinIO with presigned temporary URLs and `php bongo s3:check`.
  - `social-login` (1.0.0): Google, GitHub, and Facebook OAuth 2.0 social login with CSRF state protection, private email resolution, user model auto-creation, SVG button partials, and natural Bengali localization.
  - `nagad` (1.0.0): Direct Nagad Mobile Financial Service (MFS) payment gateway with native OpenSSL RSA-2048 encryption, SHA256withRSA signatures, two-step checkout flow, verification, refund, CLI key generator (`php bongo nagad:keys --save`), and `php bongo nagad:check`.
  - `stripe` (1.0.0): Zero-dependency Stripe international payment gateway with Checkout Sessions, Payment Intents, full/partial refunds, HMAC-SHA256 webhook signature verification, UI components, and `php bongo stripe:check`.
  - `two-factor` (1.0.0): RFC 6238 TOTP two-factor authentication with pure-PHP SVG QR code matrix generator (using Galois Field $GF(2^8)$ and Reed-Solomon polynomial error correction), single-use hashed recovery backup codes, middleware, bilingual views, and `php bongo 2fa:generate`.
  - `image` (1.0.0): Pure-PHP image manipulation, smart cropping, thumbnailing, watermarking with alpha blending, visual filters, and WebP compression engine powered by PHP `ext-gd` with zero third-party dependencies, plus `php bongo image:optimize`.
  - `backup` (1.0.0): Zero-dependency multi-database (SQLite, MySQL, PostgreSQL) and application asset backup engine with `ZipArchive` packaging, automatic retention cleanup, multi-disk routing (local and cloud S3), and CLI suite (`php bongo backup:run`, `backup:list`, `backup:clean`).
  - `permission` (1.0.0): Role-Based Access Control (RBAC) and permissions management subsystem with `HasRoles` trait, super-admin bypass, `RoleMiddleware`, `PermissionMiddleware`, multi-tenant permission migrations, and CLI suite (`role:create`, `permission:create`, `role:assign`).
  - `zip` (1.0.0): Pure-PHP ZIP compression and extraction engine with fluent `ZipBuilder`, in-place `ZipReader` inspection, Zip-Slip path traversal protection, streaming HTTP downloads, and CLI commands (`zip:make`, `zip:extract`, `zip:list`).
  - `ftp` (1.0.0): Zero-dependency FTP and FTPS filesystem driver extending Bongo's `Storage` facade via `Storage::disk('ftp')`, pure-PHP stream socket transport fallback, and `php bongo ftp:check`.
  - `paypal` (1.0.0): PayPal REST API v2 Checkout payment gateway with hosted redirect approvals, payment capture, authorization holds, full/partial refunds, cryptographically verified webhooks, responsive views, and `php bongo paypal:check`.
  - `api-tools` (1.0.0): API development suite featuring Eloquent-style `JsonResource` and `ResourceCollection` transformers with conditional inclusion, standardized `ApiResponse` formatting, automated OpenAPI 3.0 (Swagger UI & Redoc) documentation generation, sliding window API rate limiting middleware (`ApiThrottleMiddleware`), and CLI suite (`api:routes`, `api:docs`, `make:resource`).
  - `settings` (1.0.0): Dynamic application & store settings in database with type casting (`boolean`, `integer`, `float`, `json`, `string`), in-memory and persistent caching, helper `setting()`, `@setting` directive, and CLI suite (`setting:get`, `setting:set`, `setting:list`, `setting:clear`).
  - `seo` (1.0.0): Comprehensive Search Engine Optimization suite with fluent metadata builder, OpenGraph, Twitter Cards, Schema.org JSON-LD structured data, dynamic XML Sitemap generator adhering to `sitemaps.org` 0.9, `@seo` directive, and `sitemap:generate`.
  - `activity-log` (1.0.0): Enterprise-ready audit trail and model activity logger with `LogsActivity` trait, automated event recording and old vs new attribute diff tracking, client telemetry (IP and User-Agent), and `php bongo activity:clean`.
  - `health` (1.0.0): Production uptime monitoring and health check suite with built-in checks for Database latency, Storage write/read/delete, Disk Space %, Cache responsiveness, and Redis, HTTP `/health` JSON endpoint with secret token masking, and CLI `php bongo health:check`.
  - `file-manager` (1.0.0): Zero-dependency web file manager and embedded professional code editor compatible with UniSharp File Manager API. Features dark slate header, directory tree sidebar, dual Thumbnails/List views, speed dial floating action buttons (FAB), drag-and-drop multi-file uploads, embedded CodeMirror 5 code editor with syntax highlighting for 10+ languages (PHP, JS, CSS, HTML, SQL, etc.), Dracula/Eclipse themes, `Ctrl+S` auto-saving, high-resolution image preview lightbox with zoom and metadata, standalone WYSIWYG connector (`bongoFileManager()`), and path traversal sanitization.
- **Demo Store Blog & TinyMCE 6 / File Manager Integration**: Added full Blog section to the demo application with post CRUD (`App\Http\Controllers\PostController`), slug generation, status workflow (`published` / `draft`), TinyMCE 6 rich text editor integration, Bongo File Manager popup/modal picker integration (`bongoTinyMCEPicker()`), public frontend blog feed and post view (`App\Http\Controllers\BlogController`), and automated integration test suite (`tests/Feature/BlogTest.php`). Total core tests increased to **371 tests passing** (1,888 assertions).
- **Multi-Directory Migration Generation Support**: Updated `System\Database\Migrator::make()` to handle array migration paths safely, creating new migrations in the primary application directory when package migration directories are registered.
- **Storage Driver Extensibility & Presigned URLs**:
  - `Storage::extend($driver, $callback)`: register custom filesystem disk drivers at runtime.
  - `Storage::set($name, $disk)`: inject or mock disk instances in unit testing.
  - `Storage::temporaryUrl($path, $expiration)`: generate presigned temporary download URLs directly via the `Storage` facade.
  - Package runtime boot executes `packages/<name>/bootstrap.php` automatically on framework startup.
- **Performance tools** (measured, see DOCUMENTATION.md "Performance"):
  - `php bongo benchmark [--url=/x] [--requests=200] [--opcache=both|on|off]`: median, p95, requests per second, memory and HTTP status per URL, OpCache off vs on.
  - `system/preload.php` for OpCache preloading on Linux/macOS servers (PHP has no preloading on Windows).
  - `DB_PERSISTENT=true`: persistent MySQL/PostgreSQL connections; an unfinished transaction left on a reused connection is rolled back. `/shop` on PostgreSQL (Windows): 85.7 ms → 13.4 ms.
  - **N+1 warning** in debug mode: the same query 3+ times shows "Possible N+1" in the debug footer and, for JSON/API pages, in the log. `debug_stats()['repeated']`.
  - `cache_for($seconds, etag: ..., public: false)`: `Cache-Control` and ETag headers; an unchanged page is answered with `304 Not Modified`.
- **Simultaneous multi-database & multi-tenancy engine**: query multiple databases concurrently in a single request via `DB::connection('name')`, isolated per-connection transaction lifecycles and savepoints (`$conn->transaction(fn() => ...)`), model connection routing (`protected static ?string $connection = 'tenant';` and `Model::on('tenant')`), cross-database relationships, schema management (`Schema::connection('name')`), dynamic runtime connection registration (`Connection::addConnection()`), and migration CLI support (`php bongo migrate --database=name`).
- **Unified spreadsheet engine** (`System\Data\Spreadsheet`): frictionless single-API import and export for both CSV and genuine OpenXML Excel (`.xlsx`) files (`Spreadsheet::read()`, `Spreadsheet::download()`, `Spreadsheet::store()`) with UTF-8 BOM injection for clean multilingual Excel display and zero third-party dependencies.
- **Printable documents & vector PDF engine** (`System\Data\Printable`): responsive, print-optimized document generator with `@media print` CSS, one-click browser print/PDF trigger, and automatic headless browser PDF compilation bridge (Chrome, Edge, Chromium, wkhtmltopdf) so Bengali and other Indic scripts keep their ligatures and vowel signs (a real browser engine shapes the text).
- **Multi-disk storage and streaming file downloads**: `Storage::disk('public')` (rooted at `public/storage`) vs `Storage::disk('local')` (private files at `storage/app`) and `Storage::disk('s3')`, `Storage::download($path, $filename)` with safe streaming headers, directory operations (`allFiles`, `deleteDirectory`), and disk targeting on uploads (`$file->store('folder', 'local')`).
- **Custom console commands engine**: `System\Console\Command` base class with formatted output helpers (`info`, `warn`, `error`, `line`), `php bongo make:command <Name>` generator, and automatic CLI discovery and execution from `app/Commands/`.
- **Structured JSON logging** (`LOG_FORMAT=json`): output single-line JSON log events with context sanitization/masking, structured exception objects (class, message, code, file, line, trace), and dual-format parsing in `Logger::recent()`.
- **Native PostgreSQL support** (`DB_CONNECTION=pgsql`): pure-PHP `PostgresGrammar` DDL, `BIGSERIAL PRIMARY KEY`, `BOOLEAN`, `JSONB`, enum `CHECK` constraints, column comments, automatic `useCurrentOnUpdate` triggers, atomic `RETURNING "id"` inserts, `ON CONFLICT` upserts, and `FOR SHARE` locks.
- **Route caching** (`php bongo route:cache` / `route:clear`): compiles registered routes into `storage/cache/routes.php`, with static route fast-path matching to skip regex overhead for static URLs.
- **Redis queue driver** (`QUEUE_DRIVER=redis`): high-performance queues via Redis lists and delayed sorted sets with unified database `failed_jobs` error tracking.
- **SQLite support** (`DB_CONNECTION=sqlite`): the same migrations, models and queries; no database server needed.
- **Redis cache and sessions** (`CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`), optional, for apps on several servers.
- **Live updates over WebSockets**: `php bongo websocket:serve`, `Broadcast::to('orders')->send(...)`, private channels, and the `bongo-socket.js` browser client.
- **Maintenance mode**: `php bongo down --secret=... --retry=60` and `php bongo up`.
- `php bongo schedule:work`: runs the scheduler every minute without cron.
- **HTTP client**: `Http::withToken()->retry(3)->post(...)`, with `Http::fake()` for tests.
- **Notifications** by mail, SMS and Slack: `Notify::send($user, new OrderShipped($order))`.
- `withCount('relation')` and `upsert()` on the query builder.
- Generators: `make:policy`, `make:job`, `make:component`, `make:seeder`, `make:notification`, `make:mail`.
- Relations `hasOne` and `belongsToMany` (`attach`, `detach`, `sync`), soft deletes, model events and query scopes.
- `php bongo release:branch` builds the clean `release` branch.
- Open source files: LICENSE (MIT), CONTRIBUTING, SECURITY, CODE_OF_CONDUCT, and issue and pull request templates.

### Changed
- **Query builder:** `insert()` takes a list of rows and writes them in one statement. Column names in `where*()`, `orderBy()`, `groupBy()`, `insert()`, `update()`, `increment()` and `upsert()` must be plain identifiers (`name` / `table.name`), otherwise `InvalidArgumentException`: a column name built from user input (e.g. `orderBy($request->sort)`) can no longer inject SQL.
- **Responses that send themselves:** a controller may return any object with a `send()` method; the router calls it, so its status code and headers are kept. Before, only `DownloadResponse` did this and other objects (e.g. api-tools' `JsonResponse` from `ApiResponse::error(..., 422)`) were only JSON-encoded and went out as HTTP 200.
- **Translations moved from `lang/` to `resources/lang/`** (next to `resources/views/`). `php bongo package:publish <name> --lang` now copies to `resources/lang/vendor/<name>/`. A project that still has only a root `lang/` folder keeps working unchanged (`Lang::folder()` falls back to it); see UPGRADE.md.
- PSR-12 formatting fixes in `Router.php` and `helpers.php` (back to 0 errors).
- `BroadcastTest` no longer leaves `websocket:serve` processes running on Windows: it started them through `cmd.exe`, so stopping the test only stopped `cmd.exe`. The leftover servers also kept piped test output (`php bongo test | grep`) from ever finishing.
- The framework is now called **Bongo PHP Framework**, made by Nobotech (formerly BongoMVC). The repository is `Solayman/bongo-php`.
- Starter skeleton metrics and documentation synchronized: updated fresh starter skeleton documentation to reflect accurate footprint (~800 KB, 183 files) and documented direct end-user distribution via the `release` branch (`git clone -b release`).
- Query values are bound with their real types (int, null, bool with native `\PDO::PARAM_BOOL` for pgsql).
- Framework autoloader optimized with direct prefix checks (`strncmp`) and `is_file()` lookups.
- **PSR-12 coding standard** for the whole codebase (formatting only, #97). Rules in `phpcs.xml`; check with `php phpcs.phar` (0 errors required, see CONTRIBUTING). Enforced across all 20 packages with `declare(strict_types=1);` in 100% of source files.
- **Package code optimizations & N+1 elimination**:
  - `permission`: Eliminated N+1 queries in `HasRoles::hasPermissionViaRole()` and `getAllPermissions()` using a single batched O(1) query (`WHERE rhp.role_id IN (...)`) and model resolution (`whereIn`). Extracted shared `HasPermissions` trait, removing 100+ lines of duplicate code between `Role` and `HasRoles`.
  - `settings`: Eliminated N+1 queries in `SettingsManager::set(array)` by batch-fetching existing records via `Setting::whereIn()`. Added `SettingsManager::load(array $keys)` for single-query cache pre-loading.
  - `activity-log`: Extracted polymorphic helper `ActivityLogger::resolveMorph()`, eliminating duplicate morph extraction across models and loggers.
  - Package test suite updated to 174 passing tests (741 assertions, 0 failures).
- `php bongo test` now runs on **PostgreSQL** too: it creates the test database and loads `pdo_pgsql` in the test server (#95).
- Docs corrected (#99): measured numbers only (122 files, ~16,000 lines, ~570 KB, 349 tests), no "Blade" wording, correct controller path, unique section numbers in DOCUMENTATION.md, `php bongo help` instead of a non-existent `list`. CLAUDE.md and GEMINI.md record the verified review status.

- Performance numbers in README.md, WHY_BONGO.md and CLAUDE.md re-measured with `php bongo benchmark`: the old "0.45–0.65 MB, 8–19 ms" could not be reproduced; measured now 0.67–0.83 MB and 7–13 ms per page with OpCache (Windows, built-in server). System size: 124 files, ~16,200 lines, ~583 KB; 353 tests.

### Fixed
- `php bongo serve` and the test server started PHP's built-in server without `-t public` while using `server.php`, so every static file (CSS, JS, images) was a 404 in development (files were looked up in the project root). Both pass `-t public` again; test added.
- The rate limiter on API routes no longer asks the session guard for a user when no session is started (skips a pointless lookup and a "remember me" cookie database query on stateless routes).

### Fixed (review of #78–#94, verified on MySQL, PostgreSQL 18 and SQLite)
- **Database (#95):**
  - Schema quoting followed the *default* connection, so `migrate:fresh --database=pg` sent MySQL backticks to PostgreSQL. Each blueprint now uses its own driver.
  - PostgreSQL `upsert()` guessed its conflict columns and failed. It now uses the table's matching unique key, or asks for the third argument with a clear error.
  - `Model::setConnectionName()` moved **every** model to the new connection. It now applies per class.
  - Dropping all tables on PostgreSQL needed superuser rights. It now uses `DROP ... CASCADE`.
  - `Connection` now has one driver fallback instead of two that disagreed.
- **Redis queue (#98):**
  - A job was lost if a worker crashed mid-job. Jobs are now reserved atomically (a Lua script) in a `:reserved` set and come back after `retry_after`.
  - Entries are signed with `APP_KEY`, so forged or foreign entries are dropped, never unserialized.
  - `stats()` now reports reserved jobs and per-queue failed jobs.

### Security (#96)
- **CSV export:** cells starting with `= + - @` are exported as text, so they can't run as Excel formulas (CSV formula injection).
- **xlsx:**
  - control characters and invalid UTF-8 no longer corrupt the file;
  - import has a 50 MB per-part limit against zip bombs, and loads XML with `LIBXML_NONET`.
- **Printable:**
  - the badge colour, table widths/aligns and labels can't inject HTML into the PDF browser;
  - temp files with customer data are deleted after download.
- **DownloadResponse:**
  - safe `Content-Disposition` (UTF-8 filenames, no header injection), `nosniff`;
  - new `deleteAfterSend` option.
- **`Disk::deleteDirectory`:** removes links, never their targets outside the disk.
- **Logger:**
  - masks `api_key`, `access_token`, `client_secret`, `new_password`, `X-Api-Key` and other secret-like keys;
  - a message containing a newline can't fake a separate log entry.
- **`bongo` CLI:** one broken `app/Commands` file no longer breaks every command, and a custom command named like a built-in is reported.

## 0.9.0 — 2026-10-03

The first versioned release.

- Routing (groups, names, middleware, model binding), controllers with `Request $request`, form requests and validation.
- Database: query builder, models, relations, eager loading, pagination, transactions and migrations.
- Template engine with `@` directives, layouts and `<x-component>` tags.
- Authentication: session and JWT guards, remember me, email verification, password reset, signed URLs.
- Mail (SMTP, log and file drivers, attachments, `Mail::fake()`), queues, a scheduler, a file cache and rate limiting.
- Security: Argon2id with a pepper, CSRF, security headers, upload checks, encryption, `php bongo security:check`.
- Debugging: an error page with fix hints, a compact `dd()` page, a query log.
- English and Bangla; a built-in test runner (`php bongo test`); `php bongo about`, `release:build`, `upgrade`.
