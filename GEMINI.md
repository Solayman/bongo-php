# Project Instructions & Memory

## Multi-Repository Workspace Structure (`D:\laravel\framework\`)
- **`bongo-php`**: Core framework repository (`solayman` branch for active development, `master` for production).
- **`bongo-php-packages`**: Official package suite repository containing all 20 packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`) with 174 passing package tests.
- **`bongo-package-and-feature-test`**: Official testbed and application sandbox. **RULE: Always implement and test new features in this repository.** (`bongo-test` junction links here).
- **`BongoMvc`**: Directory junction linking to `bongo-php` for seamless package test runner (`run_tests.php`) execution.

## Git, Branching & Merge Request (MR) Policy
- **Full MR & Merge Access**: The AI agent has full authority to make Pull Requests / Merge Requests (MRs) and merge them.
- **Always Use `solayman` Branch**: All development code, package additions, and framework updates must be committed to the `solayman` branch.
- **Always Make MR with `solayman` & Merge**:
  1. Commit code to `solayman`.
  2. Push to `origin/solayman`.
  3. Create PR/MR targeting `master` with `head: solayman` (via GitHub REST API or `gh pr create`).
  4. Merge the PR/MR into `master` immediately (`merge_method: merge`).
  5. Sync local `master` and `solayman` branches.
  6. **ALWAYS Update the `release` Branch Immediately**: Run `php bongo release:branch` and `git push origin refs/heads/release:release`.
  7. **Sync Feature Testbed**: Pull the fresh starter skeleton into `bongo-package-and-feature-test` (`git pull origin release`).
- **ALWAYS Update the Release Branch (`release`) (CRITICAL - MANDATORY)**:
  - The `release` branch is the **clean starter skeleton** (~800 KB, 183 files, zero demo store, zero test fixtures) that end-users and developers clone (`git clone -b release`) to start fresh projects with Bongo PHP.
  - **RULE**: Every time a batch of work or a PR is merged into `master`, the `release` branch MUST be built and pushed immediately. Never let `release` drift behind `master`.
- **Package MRs (`Solayman/bongo-php-packages`)**:
  - **RULE: If any package needs an MR, go with `bongo-php-packages`.**
  - Commit to working/feature branch or `solayman`, push to origin, create PR targeting `master`, and merge.

## Environment & Credential Policy
- **Check `.env` for `GITHUB_TOKEN`**: Every repository maintains a `.env` file containing a dedicated `GITHUB_TOKEN` (Fine-grained PAT) for API operations, package catalog lookups, PR creation, and merges:
  - `bongo-php/.env`: `GITHUB_TOKEN`
  - `bongo-package-and-feature-test/.env`: `GITHUB_TOKEN`
  - `bongo-php-packages/.env`: `GITHUB_TOKEN`
- Never remove, overwrite, or expose credentials. Use them for authenticated GitHub API operations.

## Core Architecture & Principles
- **Zero Required Dependencies**: The framework must remain self-contained (no mandatory Composer packages, no Node.js runtime, no build step).
- **Minimal Size & Overhead**: Keep `system/` file count low (~127 files) and footprint ~616 KB (~15,000 lines). Fresh starter skeleton on `release` branch is ~800 KB (183 files). Never introduce bloated dependencies or excessive abstractions.
- **Production Optimizations**:
  - `php bongo config:cache`: Compiles `config/*.php` and `.env` into `storage/cache/config.php`.
  - `php bongo route:cache`: Compiles registered routes into `storage/cache/routes.php`.
  - `php bongo view:cache`: Precompiles and lints templates in `storage/cache/views/`.
- **Supported Queue Drivers**:
  - `sync`: Executes jobs immediately inline.
  - `database`: Uses `jobs` table with row locking.
  - `redis`: High-throughput Redis lists and delayed sorted sets (`QUEUE_DRIVER=redis`).
- **Supported Database Drivers & Multi-Database Architecture**:
  - `mysql`: Default enterprise MySQL/MariaDB driver.
  - `sqlite`: Zero-configuration file database (`database.sqlite`).
  - `pgsql`: Native PostgreSQL driver with `PostgresGrammar` DDL, `BIGSERIAL`, `JSONB`, `BOOLEAN`, schema search paths, double-quote identifier escaping, atomic `RETURNING "id"`, and `ON CONFLICT` upsert.
  - **Simultaneous Multi-Database & Multi-Tenancy**: Full support for concurrent querying of multiple named connections in the same request via `DB::connection('name')`, isolated per-connection transaction lifecycles and savepoints (`$conn->transaction(fn() => ...)`), model routing (`protected static ?string $connection = 'name';` and `Model::on('name')`), cross-database relationships, connection-scoped schemas (`Schema::connection('name')`), dynamic runtime connection registration (`Connection::addConnection()`), and migration CLI targeting (`php bongo migrate --database=name`).
- **Storage Subsystem**:
  - Multi-disk isolation (`config/storage.php`): `'public'` (`public/storage`), private `'local'` (`storage/app`), and cloud `'s3'` (via official `s3` package).
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
  - **Official Packages Suite (`Solayman/bongo-php-packages`)**: 20 production packages (`sslcommerz`, `bkash`, `sms`, `s3`, `social-login`, `nagad`, `stripe`, `two-factor`, `image`, `backup`, `permission`, `zip`, `ftp`, `paypal`, `api-tools`, `settings`, `seo`, `activity-log`, `health`, `file-manager`) with 174 passing package tests.

## Review & Documentation Tracking Policy
- **Testing & Pre-Release Tracking**: During the active testing stage prior to public production release, `CLAUDE.md` is tracked in the repository to maintain memory continuity across AI agents and internal developers.

## Documentation & Configuration Policy (CRITICAL - HIGH PRIORITY)
- **Always Synchronize Docs**: Whenever ANY code change, feature, optimization, or bug fix is made, updating all relevant documentation files (`DOCUMENTATION.md`, `README.md`, `WHY_BONGO.md`, `CHANGELOG.md`, `CLAUDE.md`, and `GEMINI.md`) is **MANDATORY AND HIGH PRIORITY**.
- **Always Maintain `.env.example`**: Whenever any configuration option, environment variable, driver, or system setting is introduced or updated, `.env.example` MUST be updated immediately with clear comments and sensible defaults so users and developers understand how to configure every framework capability.
- **Cross-Agent Memory Continuity**: Keeping markdown memory files synchronized ensures other AI agents and human developers immediately inherit accurate architecture, rules, and implementation status.

## Verified Review Status (Claude & Gemini, 2026-10-05): no re-check needed
- Claude verified **all features from PRs #78–#94** on real MySQL, **PostgreSQL 18**, SQLite and Redis servers. The bugs found are fixed in #95 (database), #96 (security), #97 (PSR-12) and #98 (Redis queue), and the docs are corrected in #99. **Do not re-verify these; build on them.** The full table is in `CLAUDE.md` § 7.
- Current state: **371 tests pass on MySQL, PostgreSQL and SQLite (1,888 assertions); `system/` has 127 files, 14,988 lines (~15,000 lines) and ~616 KB.**
- **Demo Store Blog Feature**: Full Blog lifecycle integrated into the demo application (`PostController`, `BlogController`, `Post` model, `2026_10_04_235716_create_posts_table.php`, TinyMCE 6 rich text editor, Bongo File Manager modal/popup connectors `bongoTinyMCEPicker()`, and automated integration tests in `BlogTest.php`).
- **Multi-Directory Migration Generation**: `Migrator::make()` safely handles array migration paths, directing newly generated migrations to the application directory when package migration directories are active.
- **Local Multi-Repo Testing Architecture** (`D:\laravel\framework\`):
  - `bongo-php`: Core framework repository (`solayman` branch for development, `master` for production).
  - `bongo-php-packages`: Official package suite repository containing all 20 packages with 174 passing package tests (706 assertions).
  - `BongoMvc`: Junction link to `bongo-php` for seamless package test runner (`run_tests.php`) execution.
  - `bongo-package-and-feature-test`: Official feature development and package integration testbed repository (also linked via `bongo-test`).
- Performance tools (Claude, 2026-10-04, verified, see `CLAUDE.md` § 2 K): `php bongo benchmark`, `system/preload.php`, `DB_PERSISTENT`, N+1 warning, `cache_for()`. Measure every performance claim with `php bongo benchmark`.
- Rules from the review:
  - test every database a change touches (not only grammar unit tests);
  - PSR-12 with 0 errors before each PR;
  - only measured numbers in docs (no "100%", "pixel-perfect" or "<15ms");
  - no Laravel/Blade wording to describe Bongo;
  - escape or whitelist everything that reaches HTML/shell/headers;
  - delete temp files;
  - never unserialize unsigned data;
  - update the `release` branch after a batch of merged work.


