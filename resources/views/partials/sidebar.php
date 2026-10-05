<nav class="sidebar d-flex flex-column p-3">
    <a href="<?= url('/') ?>" class="brand text-decoration-none mb-4">
        <i class="bi bi-bag-check-fill me-2"></i><span>{{ config('app.name') }}</span>
    </a>

    <div class="nav-section">{{ __('Shop') }}</div>
    <a class="nav-link <?= is_active('/') ?: is_active('/dashboard') ?>" href="<?= url('/') ?>"><i class="bi bi-speedometer2"></i><span>{{ __('Dashboard') }}</span></a>
    <a class="nav-link <?= is_active('/orders') ?>" href="<?= url('/orders') ?>"><i class="bi bi-receipt"></i><span>{{ __('Orders') }}</span></a>
    <a class="nav-link <?= is_active('/products') ?>" href="<?= url('/products') ?>"><i class="bi bi-box-seam"></i><span>{{ __('Products') }}</span></a>
    <a class="nav-link <?= is_active('/categories') ?>" href="<?= url('/categories') ?>"><i class="bi bi-tags"></i><span>{{ __('Categories') }}</span></a>
    <a class="nav-link <?= is_active('/posts') ?>" href="<?= url('/posts') ?>"><i class="bi bi-journal-richtext"></i><span>{{ __('Blog Posts') }}</span></a>
    <a class="nav-link <?= is_active('/customers') ?>" href="<?= url('/customers') ?>"><i class="bi bi-people"></i><span>{{ __('Customers') }}</span></a>

    <div class="nav-section mt-3">{{ __('Content & Files') }}</div>
    <a class="nav-link <?= is_active('/filemanager') ?>" href="<?= url('/filemanager') ?>" target="_blank"><i class="bi bi-folder2-open"></i><span>{{ __('File Manager') }}</span></a>

    <div class="nav-section mt-3">{{ __('Account') }}</div>
    <a class="nav-link <?= is_active('/profile') ?>" href="<?= url('/profile') ?>"><i class="bi bi-person-gear"></i><span>{{ __('Profile') }}</span></a>
    @can('manage-system')
    <a class="nav-link <?= is_active('/system') ?>" href="<?= url('/system') ?>"><i class="bi bi-cpu"></i><span>{{ __('System status') }}</span></a>
    @endcan
</nav>
