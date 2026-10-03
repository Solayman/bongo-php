<!DOCTYPE html>
<html lang="{{ locale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <style>
        :root{--bg:#f4f5f7;--card:#fff;--text:#1f2328;--muted:#57606a;--accent:#0969da;--line:#d8dee4}
        @media (prefers-color-scheme:dark){:root{--bg:#0d1117;--card:#161b22;--text:#e6edf3;--muted:#8b949e;--accent:#58a6ff;--line:#30363d}}
        body{margin:0;min-height:100vh;display:grid;place-items:center;background:var(--bg);color:var(--text);font:16px/1.6 system-ui,-apple-system,"Segoe UI",sans-serif}
        main{max-width:560px;padding:24px}
        .card{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:32px}
        h1{margin:0 0 4px;font-size:28px}.muted{color:var(--muted)}
        code{background:var(--bg);border:1px solid var(--line);border-radius:4px;padding:1px 6px;font-size:14px}
        ul{padding-left:20px}a{color:var(--accent)}
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>BongoMVC</h1>
        <p class="muted">Hello, {{ $name }}! Your new project is running.</p>
        <p>Next steps:</p>
        <ul>
            <li>Add a route in <code>routes/web.php</code></li>
            <li>Create a controller: <code>php bongo make:controller PostController</code></li>
            <li>Create a model and table: <code>php bongo make:model Post --migration</code></li>
            <li>Run the tests: <code>php bongo test</code></li>
        </ul>
        <p class="muted">Version {{ \System\Framework::version() }} · read <code>DOCUMENTATION.md</code></p>
    </div>
</main>
</body>
</html>
