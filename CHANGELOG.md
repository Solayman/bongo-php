# Changelog

Every notable change to the Bongo PHP Framework. Versions follow [semantic versioning](https://semver.org): while in 0.x, a minor version (0.10 → 0.11) may contain breaking changes, and [UPGRADE.md](UPGRADE.md) explains them.

## Unreleased

### Added
- **Performance tools** (measured, see DOCUMENTATION.md "Performance"):
  - `php bongo benchmark [--url=/x] [--requests=200] [--opcache=both|on|off]`: median, p95, requests per second, memory and HTTP status per URL, OpCache off vs on.
  - `system/preload.php` for OpCache preloading on Linux/macOS servers (PHP has no preloading on Windows).
  - `DB_PERSISTENT=true`: persistent MySQL/PostgreSQL connections; an unfinished transaction left on a reused connection is rolled back. `/shop` on PostgreSQL (Windows): 85.7 ms → 13.4 ms.
  - **N+1 warning** in debug mode: the same query 3+ times shows "Possible N+1" in the debug footer and, for JSON/API pages, in the log. `debug_stats()['repeated']`.
  - `cache_for($seconds, etag: ..., public: false)`: `Cache-Control` and ETag headers; an unchanged page is answered with `304 Not Modified`.
- **Simultaneous multi-database & multi-tenancy engine**: query multiple databases concurrently in a single request via `DB::connection('name')`, isolated per-connection transaction lifecycles and savepoints (`$conn->transaction(fn() => ...)`), model connection routing (`protected static ?string $connection = 'tenant';` and `Model::on('tenant')`), cross-database relationships, schema management (`Schema::connection('name')`), dynamic runtime connection registration (`Connection::addConnection()`), and migration CLI support (`php bongo migrate --database=name`).
- **Unified spreadsheet engine** (`System\Data\Spreadsheet`): frictionless single-API import and export for both CSV and genuine OpenXML Excel (`.xlsx`) files (`Spreadsheet::read()`, `Spreadsheet::download()`, `Spreadsheet::store()`) with UTF-8 BOM injection for clean multilingual Excel display and zero third-party dependencies.
- **Printable documents & vector PDF engine** (`System\Data\Printable`): responsive, print-optimized document generator with `@media print` CSS, one-click browser print/PDF trigger, and automatic headless browser PDF compilation bridge (Chrome, Edge, Chromium, wkhtmltopdf) so Bengali and other Indic scripts keep their ligatures and vowel signs (a real browser engine shapes the text).
- **Multi-disk storage and streaming file downloads**: `Storage::disk('public')` (rooted at `public/storage`) vs `Storage::disk('local')` (private files at `storage/app`), `Storage::download($path, $filename)` with safe streaming headers, directory operations (`allFiles`, `deleteDirectory`), and disk targeting on uploads (`$file->store('folder', 'local')`).
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
- The framework is now called **Bongo PHP Framework**, made by Nobotech (formerly BongoMVC). The repository is `Solayman/bongo-php`.
- Starter skeleton metrics and documentation synchronized: updated fresh starter skeleton documentation to reflect accurate footprint (~800 KB, 183 files) and documented direct end-user distribution via the `release` branch (`git clone -b release`).
- Query values are bound with their real types (int, null, bool with native `\PDO::PARAM_BOOL` for pgsql).
- Framework autoloader optimized with direct prefix checks (`strncmp`) and `is_file()` lookups.
- **PSR-12 coding standard** for the whole codebase (formatting only, #97). Rules in `phpcs.xml`; check with `php phpcs.phar` (0 errors required, see CONTRIBUTING).
- `php bongo test` now runs on **PostgreSQL** too: it creates the test database and loads `pdo_pgsql` in the test server (#95).
- Docs corrected (#99): measured numbers only (122 files, ~16,000 lines, ~570 KB, 349 tests), no "Blade" wording, correct controller path, unique section numbers in DOCUMENTATION.md, `php bongo help` instead of a non-existent `list`. CLAUDE.md and GEMINI.md record the verified review status.

- Performance numbers in README.md, WHY_BONGO.md and CLAUDE.md re-measured with `php bongo benchmark`: the old "0.45–0.65 MB, 8–19 ms" could not be reproduced; measured now 0.67–0.83 MB and 7–13 ms per page with OpCache (Windows, built-in server). System size: 124 files, ~16,200 lines, ~583 KB; 353 tests.

### Fixed
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
