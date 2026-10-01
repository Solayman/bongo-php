# BongoMVC Manual Testing Guide

This is a checklist for checking each framework feature **by hand, in the browser**. Tick the box when you see the expected result. The automated tests (`php bongo test`) check the same features; this guide lets a person see them working.

**Where to watch:** most checks end with "see it in the log". Open **System status → Recent log entries** (`/system#logs`) and refresh. Newest entries are at the top.

## 0. Setup

```bash
php bongo key:generate          # once; refuses if APP_KEY already exists
php bongo migrate:fresh --seed  # type the database name to confirm
php bongo serve                 # http://127.0.0.1:8000
```

- [ ] `php bongo migrate:status` lists every migration as **Ran**, with batch `1`.
- [ ] http://127.0.0.1:8000 opens the **login page**, not an error.

Login: **admin@example.com / password**

---

## 1. Authentication and login protection

| # | Do this | You should see |
| --- | --- | --- |
| 1.1 | Open `/products` while signed out | You are sent to `/login`, with "Please sign in to continue." |
| 1.2 | Sign in as admin | You land on `/products` (the page you wanted) with "Welcome back, Admin!" |
| 1.3 | While signed in, open `/login` | You are sent back to the dashboard |
| 1.4 | Click **Sign out** | Back on `/login` with "You have been signed out." Opening `/` asks you to sign in again. |
| 1.5 | Sign in with a wrong password | "These credentials do not match our records." The email stays filled in. |
| 1.6 | Sign in with an email that doesn't exist | **The same** message as 1.5, so it doesn't reveal which emails exist |
| 1.7 | Sign in as a customer: `rahim1@example.com` / `password` | "Only administrators can sign in to the dashboard." |
| 1.8 | Submit the login form empty | "The email field is required." and "The password field is required." |
| 1.9 | Enter a wrong password **5 times** for one email, then the right one | "Too many login attempts. Try again in 15 minute(s)." Even the right password is refused. |
| 1.10 | Check the log | `WARNING Failed login for …`, then `WARNING Login locked out …`, and `INFO User 1 logged in` / `logged out`. **No password appears anywhere in the log.** |

> To unlock after 1.9, wait 15 minutes, or run `php bongo migrate:fresh --seed` on a development database.

## 2. CSRF protection

| # | Do this | You should see |
| --- | --- | --- |
| 2.1 | In the browser dev tools, delete the hidden `_token` field from any form (Elements tab), then submit | **419 · Page Expired** |
| 2.2 | Check the log | `WARNING HTTP 419 on POST /…` |

## 3. Routing and error pages

| # | Do this | You should see |
| --- | --- | --- |
| 3.1 | Open `/does-not-exist` | **404 · Page Not Found** page |
| 3.2 | Open `/products/999999/edit` | 404 with "Product not found" |
| 3.3 | Type `/products/1` in the address bar (a GET request to a PUT/DELETE-only URL) | **405 · Method Not Allowed**, and nothing changes |
| 3.4 | Open `/orders/1` | Order detail. The `{id}` route parameter works. |

## 3b. Routes, API, CORS, headers

| # | Do this | You should see |
| --- | --- | --- |
| 3b.1 | `php bongo route:list` | Every route with method, path, name, action and middleware |
| 3b.2 | Open `/api/health` | JSON: `{"status":"ok", ...}` |
| 3b.3 | Open `/api/nope` | JSON error `{"error":{"status":404,...}}`, not an HTML page |
| 3b.4 | Dev tools → Network → any page → Response headers | `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`; **no** `X-Powered-By` |
| 3b.5 | `curl -i -X OPTIONS -H "Origin: https://x.com" http://127.0.0.1:8000/api/health` | `204` with `Access-Control-Allow-Origin` and `Access-Control-Allow-Methods` |
| 3b.6 | Delete a product from the list | Works. In dev tools the form posts `_method=DELETE` to `/products/{id}`. |

## 4. Forms, validation, flash messages

| # | Do this | You should see |
| --- | --- | --- |
| 4.1 | **Products → New product**: leave Name empty, SKU `BG-1000`, Price `abc`, Stock `-1`, then save | Red fields: "The name field is required.", "The sku has already been taken.", "The price must be a number.", "The stock must be at least 0." |
| 4.2 | Look at the form again | What you typed (e.g. `BG-1000`) is still in the fields (old input) |
| 4.3 | Fill it in correctly and save | Back on the list with a green "Product "…" created." The message disappears after one refresh (flash). |
| 4.4 | Edit that product and change the price | "Product … updated." and the new price shows in the list |
| 4.5 | Delete it (confirm the dialog) | "Product … deleted." and it is gone from the list |
| 4.6 | Type `<b>bold</b>` as a product name and save | The list shows the text `<b>bold</b>` literally, not bold (output escaping) |
| 4.7 | Type `{{ phpversion() }}` as a product name and save | The text shows literally; no PHP version number appears (template code is never executed) |

## 4b. Template engine

| # | Do this | You should see |
| --- | --- | --- |
| 4b.1 | Open a few pages, then look in `storage/cache/views` | One compiled `.php` file per template used |
| 4b.2 | Edit any view (add a word), then reload | The change shows at once (automatic recompile) |
| 4b.3 | `php bongo view:clear` | "Removed N compiled view(s)", and pages still work |
| 4b.4 | In a view, add `@foreach([1, 2, 3] as $i) {{ $i }} @endforeach` | `1 2 3` |
| 4b.5 | Add `{{-- secret note --}}` and view the page source | The note is not in the HTML |

## 5. Database layer

| # | Do this | You should see |
| --- | --- | --- |
| 5.1 | **Products**: search "Yoga" | Only Yoga Mat (`whereRaw`/`LIKE`) |
| 5.2 | Filter by category and by status | The list narrows (joins + `where`) |
| 5.3 | Go to page 2 | "Page 2 of 2", and filters stay in the URL (pagination) |
| 5.4 | **Dashboard** | Revenue, order and customer totals (`sum`/`count`), recent orders with customer names (joins), top products (raw grouped query) |
| 5.5 | **Categories**: add "Toys & Games" twice | Slugs `toys-games` and `toys-games-2` |
| 5.6 | Delete a category that has products | The products remain, now "Uncategorised" (`ON DELETE SET NULL`) |
| 5.7 | **Orders → an order → Update status** | "Order … marked as shipped." and the badge changes |

## 6. Migrations (tracked in the database)

Run these in a terminal, on a **development** database:

| # | Do this | You should see |
| --- | --- | --- |
| 6.1 | `php bongo migrate:status` | Every migration listed with **Ran**, a batch number and a time. The same table is on **System status → Migrations**. |
| 6.2 | `php bongo make:migration create_notes_table` | "Created: database/migrations/…_create_notes_table.php" with `up()`/`down()` stubs |
| 6.3 | `php bongo migrate` | "Migrated: …create_notes_table". Status shows it as **Ran** in a **new batch**. |
| 6.4 | `php bongo migrate` again | "Nothing to migrate." (a migration only runs once) |
| 6.5 | `php bongo migrate:rollback` | "Rolled back: …create_notes_table". Only that batch is undone; status shows it as **Pending**. |
| 6.6 | Check the log | `INFO Migrated …` and `INFO Rolled back …` |
| 6.7 | Delete the test migration file | `migrate:status` no longer lists it |

## 7. Security

| # | Do this | You should see |
| --- | --- | --- |
| 7.1 | **System status → Security** | `APP_KEY is set`, `Password hashing` and `Encryption round trip` are all **OK**. HTTPS shows "Not in use" locally. |
| 7.2 | Look at the `users` table in phpMyAdmin or MySQL Workbench | Passwords look like `$argon2id$v=19$…`. No plain text. Two users with the same password have different hashes. |
| 7.3 | **Profile → Change password**: wrong current password | "The current password is incorrect." |
| 7.4 | Change the password, sign out, sign in with the new one | Works. The old password no longer works. |
| 7.5 | (Optional, dev DB only) Back up `.env`, run `php bongo key:generate --force`, then try to sign in | Sign-in fails for every user, because passwords depend on `APP_KEY`. Restore the old `.env` and sign-in works again. **Never do this on a live site.** |

## 8. Logging

| # | Do this | You should see |
| --- | --- | --- |
| 8.1 | **System status → Write test entry** | Green message, and `INFO Test log entry written … by user 1` at the top of **Recent log entries** |
| 8.2 | Set the level filter to "Error and above" | The test entry disappears from the list |
| 8.3 | Open `storage/logs/bongo-<today>.log` in an editor | The same lines, one per entry: `[time] local.LEVEL: message {context}` |
| 8.4 | Add `LOG_LEVEL=warning` to `.env`, then write a test entry | It does **not** appear (below the minimum level). Remove the line afterwards. |
| 8.5 | `php bongo log:clear` | "Removed N log file(s)", and the log panel is empty |

## 8b. Debugging

| # | Do this | You should see |
| --- | --- | --- |
| 8b.1 | **System status → Debug tools → Trigger test error** | Developer page: "RuntimeException", the source around `SystemController.php:77` with the line highlighted, a stack trace, request details, and **Cookie: \*\*\*\*\*\*\*\*** |
| 8b.2 | **Try dd()** | Dark page "dd() called at app/Http/Controllers/SystemController.php:…" with collapsible values |
| 8b.3 | Add `dump($product);` at the top of `ProductController::edit()`, then open a product's edit page | The dump appears above the page, and the page still loads. Remove the line afterwards. |
| 8b.4 | `curl -H "Accept: application/json" http://127.0.0.1:8000/system/dump-test` (with a session cookie) | JSON `{"dd": [...], "at": "..."}` |
| 8b.5 | `php bongo tinker`, then `DB::table('users')->count()` | `=> 11` |
| 8b.6 | Set `APP_DEBUG=false` in `.env`, then Trigger test error | A plain "500 · Server Error" page with no code shown; the Debug tools card is hidden. Put it back afterwards. |
| 8b.7 | Check the log after 8b.1 | `ERROR Uncaught RuntimeException: Test exception …` with the trace under **Details** |

## 9. System status page

- [ ] Log times match your clock. If they don't, set `APP_TIMEZONE` in `.env` (e.g. `APP_TIMEZONE=Asia/Dhaka`).

- [ ] **Framework self-check:** Routing, Environment, Config, View engine, Session and Database are all **OK**. "Composer (optional)" shows **Not installed**, which is expected because BongoMVC needs no Composer.
- [ ] **Tables:** lists your database tables, with approximate row counts.

## 10. Automated tests

```bash
php bongo test
```

- [ ] The last line reads `Tests: N passed`, with no failures.
- [ ] The test run did **not** change your data: the dashboard totals are the same as before, because tests use the separate `<DB_DATABASE>_test` database.
