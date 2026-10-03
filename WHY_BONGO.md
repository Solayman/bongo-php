<div align="center">

# 🥁 Bongo PHP Framework

### Everything you need. Nothing you don't.

**A complete PHP framework in ~480 KB of code you can actually read.**<br>
No Composer. No `vendor/`. No Node. No build step. Copy the folder, and it runs.

![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)
![Dependencies: 0](https://img.shields.io/badge/dependencies-0-brightgreen)
![Tests: 312](https://img.shields.io/badge/tests-312%20passing-brightgreen)
![Size: 170 KB](https://img.shields.io/badge/fresh%20project-~170%20KB-blue)
![License: MIT](https://img.shields.io/badge/license-MIT-green)
![বাংলা](https://img.shields.io/badge/বাংলা-built%20in-orange)

[**Get started in 60 seconds**](#-get-started-in-60-seconds) · [**See the code**](#-see-how-it-feels) · [**Compare**](#-bongo-vs-the-others) · [**Docs**](DOCUMENTATION.md)

</div>

---

## 🤔 The problem

You start a new PHP project. Before writing one line of your own code:

- ⏳ you install **dozens of packages** you never chose;
- 📦 `vendor/` grows to **tens of megabytes**;
- 🧩 something breaks, and the bug is **five layers deep in code you've never seen**;
- 🔁 a dependency update breaks your app on a Friday afternoon.

**Bongo takes a different path.** Everything a real web app needs is built in, written from scratch, in one small folder you can read from top to bottom.

---

## ⚡ Get started in 60 seconds

```bash
git clone https://github.com/Solayman/bongo-php.git my-app && cd my-app
cp .env.example .env        # set your database (MySQL or SQLite)
php bongo key:generate
php bongo migrate
php bongo serve             # → http://127.0.0.1:8000
```

That's it. There's no `composer install` and no `npm install`, so there's nothing to wait for.

---

## 👀 See how it feels

If you've written PHP before, you already know how to use Bongo.

**Routes** (`routes/web.php`)
```php
Route::get('/products', [ProductController::class, 'index'])->name('products.index');
Route::post('/products', [ProductController::class, 'store'])->middleware('auth');
```

**Controller**
```php
public function store(Request $request)
{
    $data = $request->validate([
        'name'  => 'required|string|max:150',
        'price' => 'required|numeric|min:0',
    ]);

    Product::create($data);

    flash('success', 'Product created.');
    redirect(route('products.index'));
}
```

**Model**
```php
class Category extends Model
{
    protected array $fillable = ['name', 'slug'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

$categories = Category::withCount('products')->orderBy('name')->get();   // $category->products_count
```

**View** (`resources/views/categories/index.php`)
```html
@extends('layouts.admin')

@section('content')
    @foreach($categories as $category)
        <p>{{ $category->name }} ({{ $category->products_count }} products)</p>
    @endforeach
@endsection
```

**Live updates** with no Node and no Pusher:
```php
Broadcast::to('orders')->send('order.created', ['id' => $order->id]);
```
```js
BongoSocket.connect('{{ websocket_url() }}').on('orders', 'order.created', o => alert('New order #' + o.id));
```

**Tests**, built in:
```php
$this->get('/products')->assertStatus(200)->assertSee('Yoga Mat');
```
```bash
php bongo test        # → Tests: 312 passed
```

---

## 🏆 8 reasons developers choose Bongo

### 1. 📖 You can read the whole framework
About **13,000 lines** in total, and most modules are 200–600 lines. When something surprises you, open the file and understand it in minutes. No magic, no package maze.

### 2. 🪶 Zero dependencies, zero breakage
No `vendor/`, so there are no supply-chain attacks through dependencies and no surprise breaking updates. It runs on **shared hosting, a VPS or a laptop**. Composer stays optional: add packages when *you* want them.

### 3. 🚀 Fast and light
| | |
|---|---|
| Memory per request | **0.45–0.65 MB** |
| Response time (no opcache, the worst case) | **8.5 ms** (JSON API) to **18.9 ms** (full shop page with queries) |
| Framework files loaded per request | **20–28** |
| `php bongo config:cache` | Startup **36% faster** (17.3 → 11.1 ms) |

### 4. 🔒 Secure by default, from the first request
- **Passwords and logins:** Argon2id plus a pepper, login throttling and account lockout.
- **Forms and sessions:** CSRF on every form, strict session cookies.
- **Output:** escaped by default.
- **Uploads:** checked by their real content, so a renamed `.php` file is rejected.
- **Encryption and APIs:** AES-256-GCM, JWT with revocation, signed URLs.
- **Network:** security headers, CORS and trusted proxies.
- **`php bongo security:check`** audits your server before you go live.

### 5. 🩺 Errors that tell you how to fix them
A beautiful debug page with the source line, the stack trace, the queries that ran and a **"How to fix"** hint:
> *Table `products` doesn't exist → Run: `php bongo migrate`*

`dd()` shows clean, collapsible output, even for lists of 1,000+ rows.

### 6. 🧰 Everything a real app needs, built in
| | |
|---|---|
| **Web** | Routing, middleware, form requests, validation (~35 rules), file uploads, view components `<x-alert>` |
| **Data** | MySQL or SQLite, models, relations (incl. many-to-many), soft deletes, eager loading, `withCount`, migrations, seeders |
| **Users** | Session and JWT login, several guards at once, remember me, email verification, password reset, policies |
| **Background** | Queues, a scheduler (no cron needed while developing), queued mail |
| **Talking out** | Mail (SMTP), notifications (mail, SMS, Slack), an HTTP client with retries |
| **Real-time** | A WebSocket server with public and private channels, in pure PHP |
| **Scale** | Optional Redis cache and sessions, `config:cache`, `view:cache` |
| **Ops** | Maintenance mode (`down` / `up`), `php bongo upgrade`, daily logs |

### 7. 🇧🇩 Bangla, built in
Bongo ships with **বাংলা built in**, which is rare among PHP frameworks: translations, a language switcher and translated validation messages. English works the same way, and you can add any other language with one JSON file.

### 8. 🧪 Tested, and testing is easy
**312 automated tests** cover the framework. Your own tests get a separate database, a real HTTP test server, and `Mail::fake()`, `Http::fake()`, `Notify::fake()` and `Broadcast::fake()`, all with **no PHPUnit to install**.

---

## 🥊 Bongo vs the others

Every framework makes trade-offs. Here's an honest look. The others are excellent, mature projects; check their own docs for details.

| | **🥁 Bongo** | Laravel | Symfony | CodeIgniter 4 | Slim |
|---|---|---|---|---|---|
| Kind | **Full-stack, minimal** | Full-stack | Full-stack, component-based | Full-stack, light | Micro-framework |
| Install | **Copy the folder** | Composer | Composer | Composer or zip | Composer |
| Packages needed | **0** | Many | Many | Few | Several |
| Fresh project size | **≈ 170 KB** | Tens of MB | Tens of MB | A few MB | A few MB |
| Readable end to end | **✅ ~13k lines** | ❌ | ❌ | ⚠️ Large | ✅ (but little built in) |
| ORM, auth, queues, mail | ✅ Built in | ✅ Built in | ✅ Built in or packages | ⚠️ Partly | ❌ Add packages |
| WebSockets | **✅ Built in** | Separate package | Separate package | ❌ | ❌ |
| Security audit command | **✅** | Community package | Community package | ❌ | ❌ |
| Bangla built in | **✅** | Language pack | Translations | Language pack | ❌ |
| Ecosystem and jobs | 🌱 New | 🌳 Huge | 🌳 Huge | 🌲 Large | 🌿 Medium |
| Maturity | 🌱 0.x | Mature | Mature, LTS | Mature | Mature |

**Pick Bongo** when you want a complete framework you fully understand, own and can deploy anywhere.<br>
**Pick Laravel or Symfony** when you need a huge ecosystem, many ready-made packages and easy hiring.<br>
**Pick Slim** when you only need a tiny router and will choose every other part yourself.

> Bongo is written from scratch. It shares common MVC ideas (routes, controllers, models, migrations) with these frameworks, but no code.

---

## 🎯 Perfect for

✅ **Business apps, admin panels, shops and dashboards**<br>
✅ **JSON APIs** for mobile and front-end apps<br>
✅ **Shared hosting**, or servers without Composer or Node<br>
✅ **Teams** that want to own every line they run<br>
✅ **Learning**: read a real framework and see how MVC, ORMs and security work inside

## ⚠️ Be honest: when to choose something else

| | Today |
|---|---|
| Databases | MySQL / MariaDB and SQLite. **PostgreSQL and SQL Server are not supported yet.** |
| Queues | Database queue only, with no Redis or SQS driver yet |
| Ecosystem | No package marketplace yet: you build features such as payments or OAuth yourself, or add them with Composer |
| Community | New and small, with few tutorials so far. You could write the first one! |
| Very large systems | Great for one or several servers. Multi-region platforms with big teams are better served by a framework with a large ecosystem. |

---

## ❓ FAQ

**Can I still use Composer packages?**<br>
Yes. If `vendor/autoload.php` exists, Bongo loads it. The framework itself never needs it.

**Is it production-ready?**<br>
It's tested by 312 automated tests and a complete e-commerce demo app, and it has a production security checklist (`php bongo security:check`). It's version 0.x, so expect fast improvements.

**How do I upgrade?**<br>
`php bongo upgrade` replaces only the framework files, checks the SHA-256, makes a backup and never touches your app. `--rollback` undoes it.

**Will it be maintained?**<br>
Bongo is developed by [Nobotech](https://nobotech.xyz) and released under the MIT license. Contributions are welcome; see [CONTRIBUTING.md](CONTRIBUTING.md).

---

<div align="center">

## 🥁 Ready to play?

```bash
git clone https://github.com/Solayman/bongo-php.git my-app
```

**[📚 Read the docs](DOCUMENTATION.md)** · **[🤝 Contribute](CONTRIBUTING.md)** · **[🐛 Report a bug](https://github.com/Solayman/bongo-php/issues)**

⭐ **If Bongo saves you time, give it a star. It helps others find it.**

<sub>Made with ❤️ by Nobotech · MIT License</sub>

</div>

---

<details>
<summary><b>📏 How the numbers were measured</b></summary>

Measured on this repository with PHP 8.3 on Windows.

**Size** (framework only, demo app excluded), on 2026-10-03:
```bash
find system -name "*.php" | wc -l                     # 114 files
find system -name "*.php" -exec cat {} + | wc -l      # 12,779 lines
```

**Per request** (on 2026-10-02): `public/index.php` ran in a fresh PHP process, in production mode with precompiled views. Memory is `memory_get_peak_usage()`; times are the median of 6 runs **with opcache off**, the worst case. With opcache on, as on a normal live server, responses are faster.

| Route | What it does | Files | Memory | Time |
|---|---|---:|---:|---:|
| `GET /api/health` | JSON, no database | 39 | 0.45 MB | 8.5 ms |
| `GET /login` | HTML page, layout, CSRF token | 47 | 0.53 MB | 9.1 ms |
| `GET /api/products` | API with JWT check | 40 | 0.45 MB | 9.4 ms |
| `GET /shop` | Product grid, queries, partials, translations | 56 | 0.65 MB | 18.9 ms |

**`config:cache`** (2026-10-03): the median of 15 runs of the bootstrap went from 17.3 ms to 11.1 ms.

</details>
