# Changelog

Every notable change to the Bongo PHP Framework. Versions follow [semantic versioning](https://semver.org): while in 0.x, a minor version (0.10 → 0.11) may contain breaking changes, and [UPGRADE.md](UPGRADE.md) explains them.

## Unreleased

### Added
- **Simultaneous multi-database & multi-tenancy engine**: query multiple databases concurrently in a single request via `DB::connection('name')`, isolated per-connection transaction lifecycles and savepoints (`$conn->transaction(fn() => ...)`), model connection routing (`protected static ?string $connection = 'tenant';` and `Model::on('tenant')`), cross-database relationships, schema management (`Schema::connection('name')`), dynamic runtime connection registration (`Connection::addConnection()`), and migration CLI support (`php bongo migrate --database=name`).
- **Unified spreadsheet engine** (`System\Data\Spreadsheet`): frictionless single-API import and export for both CSV and genuine OpenXML Excel (`.xlsx`) files (`Spreadsheet::read()`, `Spreadsheet::download()`, `Spreadsheet::store()`) with UTF-8 BOM injection for clean multilingual Excel display and zero third-party dependencies.
- **Printable documents & vector PDF engine** (`System\Data\Printable`): responsive, print-optimized document generator with `@media print` CSS, one-click browser print/PDF trigger, and automatic headless browser PDF compilation bridge (Chrome, Edge, Chromium, wkhtmltopdf) with 100% pixel-perfect Bengali and Indic font ligatures and vowel rendering.
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
- Query values are bound with their real types (int, null, bool with native `\PDO::PARAM_BOOL` for pgsql).
- Framework autoloader optimized with direct prefix checks (`strncmp`) and `is_file()` lookups.

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
