<nav class="navbar navbar-expand navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="<?= route('/') ?>">{{ config('app.name') }}</a>
        <div class="navbar-nav">
            <a class="nav-link" href="<?= route('/') ?>">Dashboard</a>
            <a class="nav-link" href="<?= route('/cleanup') ?>">Cleanup Manager</a>
        </div>
    </div>
</nav>
