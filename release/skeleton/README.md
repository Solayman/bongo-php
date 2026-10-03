# My Bongo app

Built with [Bongo](https://github.com/Solayman/bongo-php): a lightweight PHP MVC framework with zero required dependencies.

## Start

```bash
cp .env.example .env            # set APP_URL and DB_* (Windows: copy .env.example .env)
php bongo key:generate
php bongo migrate
php bongo serve                 # http://127.0.0.1:8000
```

Requires PHP 8.0+ with `pdo_mysql` and `openssl`, plus MySQL or MariaDB. No Composer or Node needed.

## Useful commands

| Command | |
|---|---|
| `php bongo make:controller PostController` | A controller in `app/Http/Controllers` |
| `php bongo make:model Post --migration` | A model and its migration |
| `php bongo make:request StorePostRequest` | A form request (rules + authorize) |
| `php bongo migrate` | Run new migrations |
| `php bongo test` | Run the tests in `tests/` |
| `php bongo about` | Version, environment and drivers |
| `php bongo help` | Every command |

Read `DOCUMENTATION.md` for the full guide.
