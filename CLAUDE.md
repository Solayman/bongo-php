# CLAUDE.md — Architecture & Review Guide for Bongo PHP Framework

This document is prepared for **Claude AI** (and other internal reviewers and agents) to understand the framework's architecture, recent feature implementations, strict operational constraints, and technical roadmap during this testing stage.

---

## 1. Project Overview & Core Philosophy

**Bongo PHP Framework** is a lightweight, high-performance PHP MVC framework built from first principles with **zero required external dependencies**.

* **Size & Footprint:** ~15,000 lines across 122 modular files in `system/` (~554 KB total footprint).
* **Resource Utilization:** Peak memory usage is **0.45 MB – 0.65 MB per request** with **<15ms execution latency** (even with OpCache disabled).
* **Zero Dependencies:** No mandatory Composer packages, no Node.js runtime, no asset compilation steps. Pure PHP 8.0+.
* **Auditable Architecture:** Written in readable, single-responsibility PHP modules averaging 200–600 lines each.
* **Batteries Included:** Routing, Blade-compatible templates, Active Record ORM (MySQL, PostgreSQL & SQLite), Multi-guard Auth (Session & JWT), Multi-disk Storage, Queues (Sync, Database & Redis), In-process Scheduler, pure-PHP WebSockets, SMTP Mailer & Mailbox UI, Notifications, and automated security auditing.

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
  * **Unit Testing:** 7 unit tests in `tests/Unit/MultiDatabaseTest.php` (30 assertions). Full test suite: **340 passed (1,745 assertions)**.

---

## 3. Strict Git & Branching Rules

When interacting with this codebase, you **must adhere to these rules**:

| Branch | Rule & Status |
|---|---|
| **`master`** | Production-ready code. Pull requests target this branch. |
| **`solayman`** | Active development branch. All commits and pushes must go to `solayman`. |
| **`release`** | Clean starter skeleton branch for end-users to clone. Built via `php bongo release:branch` and pushed via `git push origin release`. Never commit development code directly to it. |

### Development & PR Workflow:
1. Make changes on the local `solayman` branch.
2. Push to `origin/solayman`.
3. Open a Pull Request targeting `master`: `gh pr create --base master --head solayman`.
4. Merge the Pull Request: `gh pr merge <number> --merge`.
5. Synchronize local `master` and `solayman` branches.
6. When updating the fresh starter skeleton for end users, build and update `release` via `php bongo release:branch` and `git push origin release`.

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

# Cache Clearing
php bongo config:clear     # Clears config cache
php bongo route:clear      # Clears route cache
php bongo view:clear       # Clears compiled views

# Development & Operations
php bongo serve            # Runs local dev server on http://127.0.0.1:8000
php bongo migrate          # Runs database migrations
php bongo db:seed          # Populates database seeds
php bongo queue:work       # Runs queue worker (QUEUE_DRIVER=database or redis)
php bongo websocket:serve  # Runs standalone WebSocket server on port 6001
php bongo security:check   # Runs pre-launch security & permission checks
php bongo test             # Runs test runner on isolated test database
php bongo make:command <X> # Generates custom CLI command in app/Commands/
```

---

## 6. Development Guidelines for AI Agents

1. **Maintain Zero-Dependency Architecture:** Never install or introduce mandatory third-party Composer packages or npm dependencies.
2. **Minimal Size Principle:** Keep `system/` file count low (~122 files) and footprint ~554 KB (~15,000 lines). Do not create excessive abstraction layers.
3. **PHP 8.0+ Compatibility:** Use strict types, constructor property promotion, union types, and match expressions. Avoid PHP 8.1+ features that break PHP 8.0 support (e.g., `never` return type).
4. **Update Documentation:** Always update `DOCUMENTATION.md`, `README.md`, `WHY_BONGO.md`, and `GEMINI.md` when introducing new features or commands.
5. **Testing Stage Memory:** Maintain `CLAUDE.md` up-to-date across all development cycles during the pre-release testing phase.
