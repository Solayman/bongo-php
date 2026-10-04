# Contributing to Bongo

Thank you for helping. Bug reports, fixes, docs and translations are all welcome.

## Before you start

- **Bugs:** open an issue with the steps to reproduce, what you expected, and what happened (PHP version, database, OS).
- **New features:** open an issue first, so we can agree on the idea before you write code.
- **Security problems:** do **not** open a public issue. See [SECURITY.md](SECURITY.md).

## The rules Bongo keeps

1. **Zero required dependencies.** The core never needs Composer, `vendor/` or Node. Use plain PHP 8.0+ (no `never` return type, no features newer than 8.0).
2. **Framework code goes in `system/`.** The demo app in `app/` only shows how to use it.
3. **Friendly for developers.** Familiar patterns, clear error messages that say how to fix the problem, and short docs with examples.
4. **Written from scratch.** Don't copy code from other frameworks. Common ideas (routes, controllers, migrations) are fine.
5. **Log through the framework logger** (`logger()`, `Log::...`), not `error_log()` or `echo`.
6. **PSR-12 code style.** The rules are in [`phpcs.xml`](phpcs.xml). PHP_CodeSniffer is not a dependency: download `phpcs.phar` / `phpcbf.phar` from [its releases](https://github.com/PHPCSStandards/PHP_CodeSniffer/releases), then run `php phpcs.phar` to check and `php phpcbf.phar` to fix. Every change must have **0 errors**.

## Making a change

```bash
git checkout -b fix/short-description
php bongo test                      # all tests must pass (MySQL)
DB_CONNECTION=sqlite php -d extension=pdo_sqlite bongo test   # and on SQLite, for database changes
DB_CONNECTION=pgsql DB_PORT=5432 DB_USERNAME=postgres DB_PASSWORD=secret php -d extension=pdo_pgsql bongo test   # and PostgreSQL
php phpcs.phar                      # PSR-12: 0 errors
```

- **One pull request per fix or feature.** Small PRs are reviewed faster.
- **Add a test** for what you changed (`php bongo make:test NameTest`, or `--unit`).
- **Update the docs:** `DOCUMENTATION.md`, and `CHANGELOG.md` under "Unreleased".
- **Write a clear PR description:** what changed, why, and how you tested it.

## Translations

Bongo ships English and Bangla. To add or fix a language, edit `lang/<code>.json` and send a PR.

By contributing, you agree that your work is released under the [MIT License](LICENSE).
