# Changelog

Every notable change to the Bongo PHP Framework. Versions follow [semantic versioning](https://semver.org): while in 0.x, a minor version (0.10 → 0.11) may contain breaking changes, and [UPGRADE.md](UPGRADE.md) explains them.

## Unreleased

### Added
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
- Query values are bound with their real types (int, null, bool).

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
