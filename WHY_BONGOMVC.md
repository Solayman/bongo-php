# Why BongoMVC?

BongoMVC is a complete PHP MVC framework that fits in **about 480 KB of readable code with zero dependencies**. You get routing, an ORM, migrations, authentication, validation, templates, mail, notifications, queues, a scheduler, live updates over WebSockets, a test runner and security hardening. You don't need Composer, `vendor/`, Node or a build step: copy the folder, point the web server at `public/`, and it runs.

This page is for developers and teams deciding whether BongoMVC fits their project.

---

## At a glance

| | |
|---|---|
| **Framework code** (`system/`) | **115 PHP files · 12,954 lines · 483 KB** (see the current numbers with `php bongo about`) |
| A fresh project, without docs, as `.tar.gz` | about **170 KB** |
| Required packages | **0**: no Composer, no `vendor/`, no Node, no build step |
| Requirements | PHP 8.0+ with `openssl`, plus MySQL / MariaDB (`pdo_mysql`) **or** SQLite (`pdo_sqlite`) |
| Optional | Redis (`redis` extension) for shared cache and sessions |
| Memory per request | **0.45 MB** (JSON API) · **0.53 MB** (login page) · **0.65 MB** (shop page with database queries) |
| Files loaded per request | 39 (JSON API) to 56 (full shop page); framework files: 20 to 28 |
| Time per request | 8.5–9.4 ms (JSON API, login page), 18.9 ms (shop page with queries). Measured **without opcache**, the worst case. |
| Tests | 310 automated tests, run with `php bongo test` |
| Languages | English and Bangla (বাংলা) built in |

Size and test numbers are from 2026-10-03; per-request and per-module numbers were measured on 2026-10-02, before the latest features. See [How these numbers were measured](#how-these-numbers-were-measured).

---

## Why choose it

### 1. You can read the whole framework

The largest module, the database layer with its query builder, ORM, migrations and schema builder, is about 2,000 lines. Most modules are 200–600 lines. When something behaves unexpectedly, you can open the file and read it in minutes. There are no layers of packages to dig through.

| Module | Files | Lines | What it does |
|---|---:|---:|---|
| Database | 14 | 2,012 | Query builder, ORM models and relations, eager loading, migrations, schema builder, transactions |
| Http | 11 | 881 | Requests, form requests, uploads, sessions, middleware, error pages |
| Auth | 7 | 641 | Session and JWT guards, multiple guards, login throttling, password reset |
| View | 6 | 627 | Template engine (`@if`, `@foreach`, `@extends`, …) with a compiled cache |
| Routing | 4 | 530 | Routes, groups, named routes, middleware, route model binding |
| Testing | 5 | 492 | Test runner, HTTP test client, assertions |
| Debug | 2 | 398 | Error page with source and stack trace, `dump()`/`dd()`, query log |
| Security | 6 | 365 | Encryption, password hashing, JWT, CSRF, security headers, `security:check` |
| Mail | 4 | 354 | SMTP, log and file drivers, queued mail |
| Validation | 1 | 345 | About 35 rules, translated messages, file validation |
| Scheduling | 4 | 307 | Cron expressions, scheduled tasks, built-in maintenance |
| Support | 1 | 302 | Helpers (`url()`, `route()`, `old()`, `__()`, …) |
| Queue | 3 | 284 | Background jobs: retries, backoff, failed jobs, crash recovery |
| Cache | 3 | 252 | File cache, rate limiter |
| Container | 3 | 248 | Dependency injection |
| Log | 2 | 227 | Daily log files with levels |
| Others | 5 | 536 | Console generators, events, translation, storage |

### 2. Nothing to install, nothing to break

- **No dependency updates.** Packages can't break your app, and supply-chain attacks through a dependency don't apply.
- **Any host works.** Shared hosting, a VPS or a laptop: if it runs PHP 8 and MySQL, it runs BongoMVC.
- **Composer is optional.** If you want a package, `vendor/autoload.php` is loaded when it exists. The framework never needs it.

### 3. Secure by default

These protections are on from the first request, with no setup:

- **Passwords:** Argon2id, plus a pepper from `APP_KEY`.
- **Login attacks:** login throttling and account lockout, plus rate limiting (`throttle` middleware).
- **Forms:** CSRF protection on every form.
- **Sessions:** strict session cookies with idle and absolute timeouts.
- **Templates:** output is escaped by default (`{{ }}`). JSON in templates is safe inside `<script>`, and section values such as page titles are escaped.
- **Uploads:** checked by their real content, not their extension, so a renamed `.php` file is rejected. They're stored with random names.
- **Encryption:** AES-256-GCM, plus HS256 JWT for APIs, with token revocation.
- **Headers and proxies:** security headers, optional CSP, CORS for APIs, and trusted-proxy handling so client IPs can't be faked.
- **`php bongo security:check`:** audits your setup before you go live (debug mode, HTTPS, keys, demo passwords, pending migrations).

### 4. Errors tell you how to fix them

- The debug error page shows the source line, the stack trace and the queries that ran.
- A missing table while migrations haven't run says **"Run: php bongo migrate"** in the log, on the error page and in `serve`.
- A broken template names its file. `php bongo view:cache` checks every template before you deploy.
- Every error is written to `storage/logs` with its request method and path.

### 5. Everything a real app needs is built in

| Area | Included |
|---|---|
| Routing | Groups, prefixes, names, middleware with parameters, route model binding, `route:list` |
| Controllers | Dependency injection, form request classes, `return $this->view(...)`, JSON for arrays |
| Database | MySQL or SQLite: query builder, models, relations (incl. many-to-many), soft deletes, eager loading, `withCount`, pagination, transactions, migrations, seeders |
| Real-time and notifications | WebSocket server with public and private channels; notifications by mail, SMS and Slack; HTTP client |
| Scaling out | Optional Redis cache and sessions shared by several servers |
| Auth | Several guards at once (admin session, customer session, JWT API), password reset by email |
| Views | Layouts, sections, partials, stacks, `$loop`, `@auth('guard')`, `@error`, `@csrf`, custom directives |
| Background work | Queues (`queue:work`), a scheduler (`schedule:run`), queued mail |
| Mail | SMTP, a log driver for development, a file driver for tests |
| i18n | `__()`, plurals, Bangla translations, a language switcher |
| Testing | `php bongo test`: a separate test database and a real HTTP test server |
| CLI | `serve`, `migrate`, `make:controller/model/request/middleware/test/migration`, `queue:*`, `schedule:*`, `view:cache`, `security:check`, `tinker` |

### 6. Familiar to PHP developers

The routes, controllers, models, migrations and `@if` / `@foreach` templates follow patterns most PHP developers already know, so a new team member is productive on day one. The code itself is written from scratch for BongoMVC.

---

## When BongoMVC is a good fit

- Business apps, admin panels, shops, dashboards and internal tools.
- JSON APIs for mobile or front-end apps.
- Shared hosting, or servers where you can't, or don't want to, run Composer or Node.
- Teams that want to understand, and own, every line their app runs on.
- Teaching: students can read the framework itself to learn how MVC, routing, ORMs and security really work.

## When to choose something else

Be honest about the limits:

| Limit | Today |
|---|---|
| Database | MySQL / MariaDB and SQLite. **PostgreSQL and SQL Server are not supported yet.** |
| Queue backend | Database queue only. There's no Redis or SQS queue driver yet. |
| Ecosystem | No package marketplace. Features you'd install from a marketplace elsewhere (payments, admin panels, OAuth logins), you write yourself or add with Composer. |
| Community | New and small: few tutorials, Stack Overflow answers or ready-made developers who already know it. |
| Real-time | The built-in WebSocket server suits dashboards and notifications. For tens of thousands of open connections, use a dedicated real-time service. |
| Scale | Fine for typical business traffic on one or a few servers. Very large, multi-region systems with dedicated platform teams are better served by a framework with a large ecosystem. |

---

## Compared with other PHP frameworks

Every framework makes trade-offs. This table shows where BongoMVC stands next to well-known PHP frameworks, so you can pick the right tool. The others are mature, popular projects; the facts about them are general and can change between versions, so check their own documentation for details.

| | **BongoMVC** | **Laravel** | **Symfony** | **CodeIgniter 4** | **Slim** |
|---|---|---|---|---|---|
| Kind | Full-stack, minimal | Full-stack | Full-stack, component-based | Full-stack, light | Micro-framework |
| Install | Copy the folder | Composer | Composer | Composer or zip download | Composer |
| Third-party packages needed | **None** | Many (in `vendor/`) | Many (in `vendor/`) | Few | Several |
| Size of a fresh project | **≈ 170 KB zipped** | Tens of MB with `vendor/` | Tens of MB with `vendor/` | A few MB | A few MB with `vendor/` |
| Read the whole framework yourself | **Yes, ~13,000 lines** | Not realistic | Not realistic | Possible but large | Yes (but much less built in) |
| ORM and migrations | Built in | Built in | Built in (Doctrine) | Built in | Add a package |
| Auth, validation, mail, queues, scheduler | Built in | Built in | Built in or packages | Partly built in | Add packages |
| WebSockets / live updates | Built in (pure PHP) | Separate package or service | Separate package or service | Not built in | Not built in |
| Security audit command | `php bongo security:check` | Community packages | Community packages | Not built in | Not built in |
| Bangla built in | **Yes** | Via language packs | Via translations | Via language packs | No |
| Ecosystem, jobs, tutorials | Small, new | **Very large** | **Very large** | Large | Medium |
| Long-term support, big teams | Early (0.x) | Mature | Mature, LTS versions | Mature | Mature |

**In short:**
- **Choose BongoMVC** when you want a complete framework you can fully read, own and deploy anywhere by copying files, especially on shared hosting, for teaching, or for teams that want no dependency churn.
- **Choose Laravel or Symfony** when you need a huge ecosystem, many ready-made packages, easy hiring and long-term support for large teams.
- **Choose CodeIgniter** when you want a light, mature framework with a large community.
- **Choose Slim** when you only need a small API router and will pick every other part yourself.

BongoMVC is written from scratch. It shares common MVC ideas (routes, controllers, models, migrations) with these frameworks, but no code.

---

## How these numbers were measured

Measured on this repository with PHP 8.3 on Windows, on 2026-10-02.

**Size and files** (framework only, the demo shop excluded):

```bash
find system -name "*.php" | wc -l                     # files
find system -name "*.php" -exec cat {} + | wc -l      # lines
du -sk system                                         # size on disk (KB)
tar -czf core.tgz system bongo public/index.php config && du -k core.tgz   # zipped
```

**Per request:** `public/index.php` ran once per request in a fresh PHP process, in production mode (`APP_ENV=production`, `APP_DEBUG=false`) with precompiled views (`php bongo view:cache`). Memory is `memory_get_peak_usage()`, and files is `get_included_files()`. Times are the median of 6 runs, **with opcache turned off**. With opcache on, which is the normal setup on a live server, PHP doesn't recompile the files on each request, so real responses are faster.

| Route | What it does | Files | Memory | Time |
|---|---|---:|---:|---:|
| `GET /api/health` | JSON, no database | 39 | 0.45 MB | 8.5 ms |
| `GET /login` | HTML page with a layout and the CSRF token | 47 | 0.53 MB | 9.1 ms |
| `GET /api/products` | API with JWT check (401 without a token) | 40 | 0.45 MB | 9.4 ms |
| `GET /shop` | Product grid: database queries, layout, partials, translations | 56 | 0.65 MB | 18.9 ms |

---

## Get started

```bash
cp .env.example .env
php bongo key:generate
php bongo migrate
php bongo serve          # http://127.0.0.1:8000
```

Read [DOCUMENTATION.md](DOCUMENTATION.md) for the full guide, and [TESTING_GUIDE.md](TESTING_GUIDE.md) to check every feature by hand.
