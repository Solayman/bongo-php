# Project Instructions & Memory

## Git & Branching Strategy
- **Main Branch**: `master` (production-ready code).
- **Working / Push Branch**: `solayman` (all development code must be pushed to the `solayman` branch).
- **Protected Branch (STRICT - DO NOT TOUCH)**: `release` (NEVER touch, modify, checkout, rebase, or push to the `release` branch under any circumstances).
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
