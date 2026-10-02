# BongoMVC

A lightweight PHP MVC framework with **zero required dependencies**: no Composer, no Node, no build step. Clone it, point it at a database, and run it.

```bash
git clone https://github.com/Solayman/BongoMvc.git && cd BongoMvc
cp .env.example .env            # set APP_URL and DB_* (Windows: copy .env.example .env)
php bongo key:generate
php bongo migrate
php bongo db:seed               # demo data (e-commerce test app)
php bongo serve                 # http://127.0.0.1:8000  ·  admin@example.com / password
```

Requires PHP 8.0+ with `pdo_mysql` and `openssl`, plus MySQL or MariaDB. Composer packages are optional: `vendor/autoload.php` is loaded if it exists.

## What's inside

- **Routing:** `Route::get/post/put/patch/delete`, groups, named routes, route files (`web.php`, `api.php`), middleware with parameters, method spoofing.
- **Templates:** `@if`, `@foreach`/`@forelse`, `@csrf`, `@error`, `@auth`, layouts, `@include`, `@push/@stack`, a compiled-view cache.
- **Database:** query builder, transactions, a migration system with tracked batches and rollback, models with relations and eager loading.
- **Security:**
  - CSRF protection, CORS, and security headers.
  - Argon2id passwords peppered with `APP_KEY`, plus AES-256-GCM encryption.
  - Login throttling, and rate limiting with `throttle:60,1`.
- **Auth:** session and JWT guards, so web and API authentication work side by side (`auth:api`).
- **Validation and uploads:** 35+ rules, array wildcards (`items.*.qty`), and uploads checked by their real content.
- **Developer tools:**
  - `dd()`/`dump()`, a debug error page with source and stack trace, and a debug footer with the query list.
  - A log viewer, `php bongo tinker`, and `make:*` generators.
- **Testing:** `php bongo test` runs unit tests and real HTTP tests on a separate test database.

## Learn more

- [DOCUMENTATION.md](DOCUMENTATION.md): the full guide, with examples for every feature.
- [TESTING_GUIDE.md](TESTING_GUIDE.md): a step-by-step checklist for trying each feature by hand in the browser.
- `php bongo help`: every CLI command.

BongoMVC is an independent project with its own code, not affiliated with any other framework.
