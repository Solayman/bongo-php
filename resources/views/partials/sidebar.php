<nav class="sidebar d-flex flex-column p-3">
    <a href="<?= url('/') ?>" class="brand text-decoration-none mb-4">
        <i class="bi bi-layers-fill me-2"></i><span>{{ config('app.name') }}</span>
    </a>

    <div class="nav-section">{{ __('Navigation') }}</div>
    <a class="nav-link <?= is_active('/dashboard') ?>" href="<?= url('/dashboard') ?>"><i class="bi bi-speedometer2"></i><span>{{ __('Dashboard') }}</span></a>

    <div class="nav-section mt-3">{{ __('Account') }}</div>
    <a class="nav-link <?= is_active('/profile') ?>" href="<?= url('/profile') ?>"><i class="bi bi-person-gear"></i><span>{{ __('Profile') }}</span></a>
    @can('manage-system')
    <a class="nav-link <?= is_active('/system') ?>" href="<?= url('/system') ?>"><i class="bi bi-cpu"></i><span>{{ __('System status') }}</span></a>
    @endcan
</nav>
