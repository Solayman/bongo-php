# CLAUDE.md — Architecture & Review Guide for Bongo PHP Framework

This document is prepared for **Claude AI** (and other peer AI/human reviewers) to understand the framework's architecture, recent feature implementations, strict operational constraints, and technical roadmap.

---

## 1. Project Overview & Core Philosophy

**Bongo PHP Framework** is a lightweight, high-performance PHP MVC framework built from first principles with **zero required external dependencies**.

* **Size & Footprint:** ~13,000 lines across 114 modular files in `system/` (~480 KB total footprint).
* **Resource Utilization:** Peak memory usage is **0.45 MB – 0.65 MB per request** with **<15ms execution latency** (even with OpCache disabled).
* **Zero Dependencies:** No mandatory Composer packages, no Node.js runtime, no asset compilation steps. Pure PHP 8.0+.
* **Auditable Architecture:** Written in readable, single-responsibility PHP modules averaging 200–600 lines each.
* **Batteries Included:** Routing, Blade-compatible templates, Active Record ORM (MySQL & SQLite), Multi-guard Auth (Session & JWT), Queues, In-process Scheduler, pure-PHP WebSockets, SMTP Mailer & Mailbox UI, Notifications, and automated security auditing.

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
  * **Unit Testing:** Verified via `tests/Unit/PostgresSchemaTest.php` (100% test suite passing with 319 tests and 1,628 assertions).

---

## 3. Strict Git & Branching Rules

When interacting with this codebase, you **must adhere to these rules**:

| Branch | Rule & Status |
|---|---|
| **`master`** | Production-ready code. Pull requests target this branch. |
| **`solayman`** | Active development branch. All commits and pushes must go to `solayman`. |
| **`release`** | **STRICTLY PROTECTED (DO NOT TOUCH):** Never modify, checkout, rebase, or push to `release`. |

### Development & PR Workflow:
1. Make changes on the local `solayman` branch.
2. Push to `origin/solayman`.
3. Open a Pull Request targeting `master`: `gh pr create --base master --head solayman`.
4. Merge the Pull Request: `gh pr merge <number> --merge`.
5. Synchronize local `master` and `solayman` branches.

---

## 4. Codebase Navigation

```
d:\Laravel\BongoMvc\
├── app/
│   ├── Controllers/       # Web & API controllers
│   ├── Models/            # Active Record models with relations
│   ├── Http/Requests/     # Form request validation classes
│   ├── Jobs/              # Queue job classes
│   ├── Notifications/     # Notification channels (Mail, SMS, Slack)
│   └── Policies/          # Authorization gates & policies
├── config/
│   ├── bootstrap.php      # Framework bootstrap & class autoloader
│   ├── app.php            # Middleware, routing, locale settings
│   ├── database.php       # MySQL, SQLite, and Redis connection settings
│   ├── queue.php          # Queue driver settings (sync, database, redis)
│   └── ...
├── routes/
│   ├── web.php            # Web routes with CSRF & session middleware
│   └── api.php            # Stateless REST API routes with CORS
├── system/                # Core Framework (~114 files, zero dependencies)
│   ├── Auth/              # Session and JWT guards, hashing
│   ├── Broadcasting/      # Pure-PHP WebSocket daemon
│   ├── Cache/             # File and Redis stores, rate limiter
│   ├── Database/          # Query builder, migrations, ORM
│   ├── Http/              # Request, response, client, uploaded files
│   ├── Queue/             # Queue engine (sync, database, redis), workers
│   ├── Redis/             # Pure-PHP Redis connection wrapper
│   ├── Routing/           # Router, RouteDefinition, Route cache
│   ├── Security/          # Crypt, Hash (Argon2id + pepper), Signed URLs
│   ├── Testing/           # Native test runner, HTTP assertions, fakes
│   └── View/              # Blade-compatible template engine
├── storage/
│   ├── cache/             # config.php, routes.php, views/
│   └── logs/              # Daily rotated application logs
└── bongo                  # CLI tool (php bongo <command>)
```

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
```

---

## 6. Development Guidelines for AI Agents

1. **Maintain Zero-Dependency Architecture:** Never install or introduce mandatory third-party Composer packages or npm dependencies.
2. **Minimal Size Principle:** Keep `system/` file count low (~114 files) and footprint ~480 KB. Do not create excessive abstraction layers.
3. **PHP 8.0+ Compatibility:** Use strict types, constructor property promotion, union types, and match expressions. Avoid PHP 8.1+ features that break PHP 8.0 support (e.g., `never` return type).
4. **Update Documentation:** Always update `DOCUMENTATION.md`, `README.md`, `WHY_BONGO.md`, and `GEMINI.md` when introducing new features or commands.

---

## 7. Recommended Next Architectural Priorities

If looking for next improvements to discuss or implement:
1. **PostgreSQL Driver (`pdo_pgsql`)**: Grammar and connection support for Supabase/Neon/RDS deployments.
2. **API Resources Layer (`JsonResource`)**: Declarative JSON data transformation and sanitization for API endpoints.
3. **Authenticated User Rate Limiting**: Enhancing `throttle:60,1` to support rate-limiting by authenticated JWT user ID.
4. **Private Storage & Streaming Downloads**: Secure storage disk rooted outside `public/` for sensitive files with signed download URLs.
5. **Pure-PHP 2FA / TOTP**: RFC 6238 time-based one-time password generation/verification via native HMAC-SHA1.
