<nav class="sidebar d-flex flex-column p-3">
    <a href="<?= url('/') ?>" class="brand text-decoration-none mb-4">
        <i class="bi bi-bag-check-fill me-2"></i><span>{{ config('app.name') }}</span>
    </a>

    <div class="nav-section">Shop</div>
    <a class="nav-link <?= is_active('/') ?: is_active('/dashboard') ?>" href="<?= url('/') ?>"><i class="bi bi-speedometer2"></i><span>Dashboard</span></a>
    <a class="nav-link <?= is_active('/orders') ?>" href="<?= url('/orders') ?>"><i class="bi bi-receipt"></i><span>Orders</span></a>
    <a class="nav-link <?= is_active('/products') ?>" href="<?= url('/products') ?>"><i class="bi bi-box-seam"></i><span>Products</span></a>
    <a class="nav-link <?= is_active('/categories') ?>" href="<?= url('/categories') ?>"><i class="bi bi-tags"></i><span>Categories</span></a>
    <a class="nav-link <?= is_active('/customers') ?>" href="<?= url('/customers') ?>"><i class="bi bi-people"></i><span>Customers</span></a>

    <div class="nav-section mt-3">Account</div>
    <a class="nav-link <?= is_active('/profile') ?>" href="<?= url('/profile') ?>"><i class="bi bi-person-gear"></i><span>Profile</span></a>
    <a class="nav-link <?= is_active('/system') ?>" href="<?= url('/system') ?>"><i class="bi bi-cpu"></i><span>System status</span></a>
</nav>
