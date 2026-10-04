# Project Instructions & Memory

## Git & Branching Strategy
- **Main Branch**: `master` (production-ready code).
- **Working / Push Branch**: `solayman` (all development code must be pushed to the `solayman` branch).
- **Release Branch (`release`)**: The clean, production-ready starter skeleton branch for direct end-users to clone and start fresh projects (contains core framework, migrations, starter skeleton, no demo store, no test fixtures). Never commit development work directly to `release`. Update it using `php bongo release:branch` and push via `git push origin release` when publishing framework updates.
- **Merge Requests / Pull Requests**:
  1. Commit code to `solayman`.
  2. Push to `origin/solayman`.
  3. Create PR/MR targeting `master` (`gh pr create --base master --head solayman`).
  4. Merge PR/MR into `master` (`gh pr merge <number> --merge`).
  5. Sync local `master` and `solayman` branches.

## Core Architecture & Principles
- **Zero Required Dependencies**: The framework must remain self-contained (no mandatory Composer packages, no Node.js runtime, no build step).
- **Minimal Size & Overhead**: Keep `system/` file count low (~114 files) and footprint ~480 KB. Never introduce bloated dependencies or excessive abstractions.
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
  - Multi-disk isolation (`config/storage.php`): `'public'` (`public/storage`) and private `'local'` (`storage/app`).
  - Safe streaming downloads via `Storage::download()` and `System\Storage\DownloadResponse`.
- **Extensible CLI**:
  - Base class `System\Console\Command` with `handle(array $args, array $options): int` and styled output helpers (`info`, `warn`, `error`, `line`).
  - Auto-discovery of custom commands from `app/Commands/*.php`.
- **Structured Logging**:
  - Supports `LOG_FORMAT=text` and `LOG_FORMAT=json` with secret masking and structured exception objects.
- **Data & Document Subsystem**:
  - `System\Data\Spreadsheet`: Unified CSV and OpenXML Excel (`.xlsx`) import and export engine with UTF-8 BOM, automatic format detection, and streaming downloads.
  - `System\Data\Printable`: Responsive, print-ready document and vector PDF engine with `@media print` CSS, native browser print/PDF trigger, and headless browser PDF compilation bridge (Chrome/Edge/Chromium/wkhtmltopdf) ensuring 100% pixel-perfect Bengali and Indic font ligatures and vowel rendering.

## Review & Documentation Tracking Policy
- **Testing & Pre-Release Tracking**: During the active testing stage prior to public production release, `CLAUDE.md` is tracked in the repository to maintain memory continuity across AI agents and internal developers.

## Documentation & Configuration Policy (CRITICAL - HIGH PRIORITY)
- **Always Synchronize Docs**: Whenever ANY code change, feature, optimization, or bug fix is made, updating all relevant documentation files (`DOCUMENTATION.md`, `README.md`, `WHY_BONGO.md`, `CHANGELOG.md`, `CLAUDE.md`, and `GEMINI.md`) is **MANDATORY AND HIGH PRIORITY**.
- **Always Maintain `.env.example`**: Whenever any configuration option, environment variable, driver, or system setting is introduced or updated, `.env.example` MUST be updated immediately with clear comments and sensible defaults so users and developers understand how to configure every framework capability.
- **Cross-Agent Memory Continuity**: Keeping markdown memory files synchronized ensures other AI agents and human developers immediately inherit accurate architecture, rules, and implementation status.
