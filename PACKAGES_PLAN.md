# Bongo PHP Packages — Plan

**Status:** Approved for implementation (2026-10-04). Planned by Claude with Solayman (Nobotech).
**For:** any agent (Gemini, Claude) building the package system. Follow this plan exactly; ask before changing a decision marked **Decided**.

---

## 1. Goal

Keep the Bongo core small and fast. Features that only some projects need become **optional packages**. A developer enables a package by uncommenting one line and running one command. Nothing a developer does not enable is downloaded.

**Non-negotiable rules (from CLAUDE.md / GEMINI.md):**
- Zero required dependencies. No Composer, no Node needed to install or run packages.
- PHP 8.0+ (no `never`, no 8.1+ only features).
- PSR-12 with 0 errors (`php phpcs.phar`).
- Never describe Bongo with Laravel/Blade terms.
- Security defaults: verify downloads, escape output, never log secrets, never `unserialize` untrusted data.

---

## 2. Repositories — **Decided**

| Repo | Contents | Branches |
|---|---|---|
| `Solayman/bongo-php` (existing) | Core + **package installer** only | `solayman` → PR → `master`; `release` via `php bongo release:branch` |
| `Solayman/bongo-php-packages` (new, **private** for now) | All official packages, one folder each | Same pattern: `solayman` → PR → `master` |

Packages are **not** built in a branch of the core repo.

```
bongo-php-packages/
├── index.json              # list of packages: name, versions, min core version, sha256 per version
├── sslcommerz/
│   ├── package.json        # name, version, description, requires.bongo, provides (config, migrations, views, routes, commands)
│   ├── src/                # namespace Bongo\Packages\Sslcommerz\
│   ├── config/sslcommerz.php
│   ├── database/migrations/
│   ├── views/
│   ├── lang/               # en.json required; bn.json etc. optional (see 3.5)
│   ├── routes.php          # optional
│   ├── tests/              # REQUIRED — no tests, no merge
│   └── README.md           # setup steps, .env keys, events fired
└── ...
```

Local development: clone next to the core, e.g. `D:\Laravel\bongo-php-packages`. Setting `BONGO_PACKAGES_PATH=../bongo-php-packages` makes the installer read from that folder instead of GitHub.

Private repo access: `BONGO_PACKAGES_TOKEN` (same idea as `UPGRADE_TOKEN`). Never print or log it.

---

## 3. Core work (in `bongo-php`)

### 3.1 `config/packages.php`
```php
return [
    // 'sslcommerz',     // SSLCommerz payment gateway
    // 'bkash',          // bKash payment
    // 'sms',            // Bangladeshi SMS gateways + OTP
    // 's3',             // S3 / Spaces / R2 storage disk
    // 'social-login',   // Google / Facebook / GitHub login
];
```
An entry may pin a version: `'sslcommerz' => '^1.2'`.

### 3.2 Commands
| Command | Does |
|---|---|
| `php bongo package:install` | Install/sync everything uncommented in `config/packages.php` |
| `php bongo add <name>[@version]` | Uncomment/add the line and install it |
| `php bongo package:update [name]` | Update to newest allowed version |
| `php bongo package:install <name>@<version>` | Install a specific version (also downgrade) |
| `php bongo package:remove <name>` | Roll back its migrations (asks first), delete files, comment the line |
| `php bongo package:list` | Installed packages, versions, available updates |
| `php bongo package:publish <name> [--config] [--views] [--lang]` | Copy config/views/translations into the app for customizing |
| `php bongo package:diff <name>` | Show files the developer changed inside `packages/<name>` |
| `php bongo package:test <name>` | Run the package's test checklist (section 5) |

Keep the installer small: target ≤ 2 files in `system/Package/` (e.g. `Installer.php`, `PackageManager.php`), ~600 lines total.

### 3.3 Install flow
1. Read `index.json` (GitHub raw via token, or `BONGO_PACKAGES_PATH`).
2. Check `requires.bongo` against `system/VERSION`; refuse with a clear message if incompatible.
3. Download the package archive; **verify SHA-256 from `index.json`**; refuse on mismatch.
4. Extract into `packages/<name>/` with path-traversal checks (no `..`, no absolute paths, no symlinks).
5. Write `packages/.lock.json`: name, version, sha256 of every file.
6. Copy config to `config/<name>.php` only if it does not exist.
7. Print the `.env` keys from the package README / package.json; never write `.env`.
8. Ask before running the package's migrations (`--migrate` to skip the question).

### 3.4 Loading at runtime
- Autoloader: map `Bongo\Packages\<Name>\` → `packages/<name>/src/` (add to `config/bootstrap.php`, same fast prefix style as `App\`/`System\`).
- On boot, for each installed package: load its routes, register its views namespace (`@include('sslcommerz::checkout')`), register its commands.
- Read the installed list from `packages/.lock.json`, **cached** by `config:cache` so production does no extra file scans.
- If no packages are installed, the cost must be zero (one `is_file` check at most).

### 3.4a Developer-friendly — **Decided** (top priority)
The package system must feel simple to a beginner:
- **One command does everything:** `php bongo add sslcommerz` uncomments the line, downloads, verifies, copies config, asks about migrations and prints the next steps. No manual file editing needed.
- **Clear output, no jargon:** say what happened and what to do next, e.g.
  ```
  ✔ sslcommerz 1.0.0 installed
  Next: add these to .env
     SSLCOMMERZ_STORE_ID=
     SSLCOMMERZ_STORE_PASSWORD=
     SSLCOMMERZ_SANDBOX=true
  Docs: packages/sslcommerz/README.md
  ```
- **Every error explains the fix:** "Package 'sslcomerz' not found. Did you mean 'sslcommerz'?", "Needs Bongo 1.0+, you have 0.9.0 — run php bongo upgrade", "No internet / token missing — set BONGO_PACKAGES_TOKEN in .env".
- **Safe by default:** confirm before deleting tables or overwriting files; `--dry-run` shows what would happen.
- **Sensible defaults:** sandbox mode on, config works without editing except the keys.
- **Works offline after install:** nothing is downloaded at runtime.
- `php bongo package:list` shows available packages with one-line descriptions, so developers discover them without reading docs.
- Each package README starts with a 3-step quick start (add → set .env → use), then a full example.
- Errors at runtime (e.g. missing key) show the package name and the exact `.env` key, on the existing debug error page.

### 3.5 Multi-language — **Decided**
Bongo is multi-language, not Bengali-only. Packages follow the core's `lang/` system:
- Every user-facing text goes through `__()`. No hard-coded strings in views, errors or emails.
- Each package ships `lang/en.json` (required) and any other locales (`bn.json`, `ar.json`, ...) it supports.
- Keys are namespaced: `__('sslcommerz::Payment failed')`.
- App translations override package ones: `lang/vendor/<name>/<locale>.json` in the app is checked first (survives updates).
- `php bongo package:publish <name> --lang` copies the package's lang files there for editing.
- Package tests run at least once with a non-English locale.

---

## 4. Update/downgrade safety — **Decided**

**Rule:** developers never edit `packages/`. Customizations live in the app:

| Want to change | Where (survives updates) |
|---|---|
| Settings | `config/<name>.php` and `.env` |
| Views | `php bongo package:publish <name> --views` → `resources/views/vendor/<name>/` (loaded first) |
| Translations | `php bongo package:publish <name> --lang` → `lang/vendor/<name>/` (loaded first) |
| Behaviour | Events fired by the package (`Event::listen('payment.success', ...)`) in `app/` |
| Replace a class | Extend in `app/`, bind in the container |
| Extra tables | Own migrations in `database/migrations/` |

**Safety net:** before `update`, `downgrade` or `remove`, compare every file with the hashes in `packages/.lock.json`. If anything changed:
```
⚠ You changed 2 files in packages/sslcommerz: src/Gateway.php, views/checkout.php
Update stopped. Use --backup (save to storage/package-backups/<name>-<date>/ then update)
or --force (discard). Tip: move changes to app/ or publish the views.
```
- Never overwrite edited files silently.
- Downgrade: run the newer version's down migrations first (with confirmation), keep the old files in `storage/package-backups/`.
- `packages/` and `packages/.lock.json` are committed to git so the team shares versions.

---

## 5. Verifying a package works — **Decided**

A package version is only added to `index.json` when all of these pass:

- [ ] `php bongo package:test <name>`: installs into a **fresh** Bongo copy, runs package tests **plus core tests**, uninstalls and checks nothing is left behind
- [ ] Passes on **MySQL, PostgreSQL and SQLite** (commands in CLAUDE.md §7)
- [ ] PSR-12: 0 errors
- [ ] Works on the oldest supported core and the newest
- [ ] External services tested with **fakes by default** (offline) and `--live` against sandboxes:

  | Package | Live test target |
  |---|---|
  | sslcommerz | SSLCommerz sandbox |
  | bkash | bKash sandbox (test wallets) |
  | sms | `log` driver + one real SMS |
  | s3 | MinIO locally, then a real bucket |
  | social-login | Google/Facebook test apps |
- [ ] Security review: payment callbacks verified server-side (signature + amount + transaction id lookup), idempotent callbacks, no secrets in logs
- [ ] Payment packages: manual click-through in the demo shop by Solayman (pay, cancel, fail, refund)
- [ ] README with setup, `.env` keys, events
- [ ] Version tagged, sha256 in `index.json`

Later: GitHub Actions runs every package's tests on each push and on each core change.

---

## 6. Package list and order

**Phase A — package system (core repo)**
1. Installer, commands, lock file, runtime loading, update safety (sections 3–4).
2. Docs: new DOCUMENTATION.md section, README, WHY_BONGO.md, CHANGELOG, CLAUDE.md, GEMINI.md, `.env.example` (`BONGO_PACKAGES_TOKEN`, `BONGO_PACKAGES_PATH`).

**Stays built in — Decided (Solayman, 2026-10-04):** WebSockets (`system/Broadcasting`), Printable/PDF (`system/Data/Printable`) and Spreadsheet (`system/Data/Spreadsheet`) remain part of the core. Do **not** move them into packages.

**Phase B — local-market packages first (Bongo's strength), built to be global**
3. `sslcommerz` (first package — proves the whole system end to end)  4. `bkash`  5. `sms` + OTP login
   - `sms` uses **drivers**: start with BD gateways (BulkSMSBD, SSL Wireless, Alpha SMS) plus `log`; a Twilio/Vonage driver can be added later without changing app code.
   - Payment packages share one small interface (`pay`, `verify`, `refund`, events `payment.success/failed`) so later gateways (stripe, paypal) plug in the same way.

**Phase C**
6. `s3`  7. `social-login`  8. `nagad` (Direct MFS Gateway with RSA-2048 and signatures)  9. `stripe` (International payments, Checkout & Webhooks) → then release **Bongo 1.0**.

**Phase D (after 1.0)**
paypal, admin-panel, api-tools, two-factor, backup, image, docker.

### Completed Official Packages (20 Packages, 174 Tests Passing)
| Package | Description | Status |
|---|---|---|
| `sslcommerz` | SSLCommerz payment gateway | ✔ Done (v1.0.0, 4 tests) |
| `bkash` | bKash tokenized checkout | ✔ Done (v1.0.0, 6 tests) |
| `sms` | Multi-driver SMS & phone OTP | ✔ Done (v1.0.0, 8 tests) |
| `s3` | AWS S3 & SigV4 object storage | ✔ Done (v1.0.0, 9 tests) |
| `social-login` | OAuth 2.0 Google, GitHub, Facebook | ✔ Done (v1.0.0, 6 tests) |
| `nagad` | Direct Nagad MFS payment gateway | ✔ Done (v1.0.0, 9 tests) |
| `stripe` | Stripe international payments & webhooks | ✔ Done (v1.0.0, 11 tests) |
| `two-factor` | RFC 6238 TOTP 2FA + SVG QR codes & recovery | ✔ Done (v1.0.0, 9 tests) |
| `image` | Pure-PHP GD image manipulation, cropping, WebP | ✔ Done (v1.0.0, 17 tests) |
| `backup` | Zero-dependency database & asset backup engine | ✔ Done (v1.0.0, 6 tests) |
| `permission` | Role-Based Access Control (RBAC) & permissions | ✔ Done (v1.0.0, 9 tests) |
| `zip` | Pure-PHP ZIP compression, extraction & inspection | ✔ Done (v1.0.0, 7 tests) |
| `ftp` | Zero-dependency FTP & FTPS storage disk | ✔ Done (v1.0.0, 4 tests) |
| `paypal` | PayPal REST API v2 Checkout, orders, refunds & webhooks | ✔ Done (v1.0.0, 10 tests) |
| `api-tools` | API Resources, OpenAPI 3.0 docs & rate limiting | ✔ Done (v1.0.0, 11 tests) |
| `settings` | Dynamic application & store settings in database | ✔ Done (v1.0.0, 10 tests) |
| `seo` | Search Engine Optimization, OpenGraph, JSON-LD & Sitemap | ✔ Done (v1.0.0, 9 tests) |
| `activity-log` | Enterprise audit trail & model activity logger | ✔ Done (v1.0.0, 6 tests) |
| `health` | Application health checks & production uptime monitoring | ✔ Done (v1.0.0, 9 tests) |
| `file-manager` | Web file manager with tree navigation, image previews & embedded code editor | ✔ Done (v1.0.0, 13 tests) |

### Candidates for future packages
| Package | Why it waits |
|---|---|
| `admin-panel` | Large; needs a stable 1.0 API first |
| `docker` | Most target users deploy on shared hosting |

### Never packages

| Idea | Reason |
|---|---|
| Frontend frameworks (Vue/React) | Against the no-Node principle |
| A CMS | A product, not a package |
| Wrappers around heavy third-party libraries | Breaks light and fast; zero dependencies |
| WebSockets, PDF, Spreadsheet | Built into the core (Decided above) |

---

## 7. Definition of done (each phase)

- One logical change per PR (`solayman` → `master`), tests green on all three databases, PSR-12 0 errors.
- All docs updated (CLAUDE.md §4 list).
- Re-measure and correct the numbers in docs (files, lines, KB in `system/`, test count).
- Update the `release` branch after the batch is merged (`php bongo release:branch`, `git push origin release`, no tag).
- No public release or tags until Solayman asks.
- Record what was built and verified in the review table in CLAUDE.md §7 / GEMINI.md.
