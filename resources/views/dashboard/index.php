@extends('layouts.admin')

@set('title', __('Dashboard'))

@section('content')
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100">
            <div class="card-body">
                <div class="label">{{ __('Registered Users') }}</div>
                <div class="value">{{ number_format($stats['users']) }}</div>
                <div class="sub">{{ __('System accounts') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100">
            <div class="card-body">
                <div class="label">{{ __('PHP Runtime') }}</div>
                <div class="value">PHP {{ explode('-', $stats['php_version'])[0] }}</div>
                <div class="sub">{{ php_sapi_name() }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100">
            <div class="card-body">
                <div class="label">{{ __('Database') }}</div>
                <div class="value text-capitalize">{{ $stats['db_driver'] }}</div>
                <div class="sub">{{ __('Active primary connection') }}</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card stat-tile h-100">
            <div class="card-body">
                <div class="label">{{ __('Queue Driver') }}</div>
                <div class="value text-capitalize">{{ $stats['queue_driver'] }}</div>
                <div class="sub">{{ __('Environment:') }} {{ $stats['env'] }}</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span class="fw-semibold">{{ __('Welcome to Bongo PHP') }}</span>
                <span class="badge bg-success-subtle text-success">v{{ System\Framework::version() }}</span>
            </div>
            <div class="card-body">
                <h5 class="card-title fw-bold">{{ __('Admin Dashboard') }}</h5>
                <p class="text-secondary mb-4">
                    {{ __('Bongo PHP is a modern, lightweight, zero-dependency PHP framework built for speed, simplicity, and rock-solid reliability.') }}
                </p>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light">
                            <h6 class="fw-semibold mb-2"><i class="bi bi-terminal me-2"></i>{{ __('Developer Commands') }}</h6>
                            <ul class="list-unstyled small text-secondary mb-0">
                                <li><code>php bongo serve</code> - {{ __('Start local server') }}</li>
                                <li><code>php bongo migrate</code> - {{ __('Run migrations') }}</li>
                                <li><code>php bongo test</code> - {{ __('Execute test suite') }}</li>
                                <li><code>php bongo benchmark</code> - {{ __('Measure performance') }}</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 border rounded-3 bg-light">
                            <h6 class="fw-semibold mb-2"><i class="bi bi-box-seam me-2"></i>{{ __('Production Caching') }}</h6>
                            <ul class="list-unstyled small text-secondary mb-0">
                                <li><code>php bongo config:cache</code> - {{ __('Compile config') }}</li>
                                <li><code>php bongo route:cache</code> - {{ __('Compile routes') }}</li>
                                <li><code>php bongo view:cache</code> - {{ __('Precompile views') }}</li>
                                <li><code>php bongo optimize:clear</code> - {{ __('Clear all caches') }}</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">
                {{ __('Quick Links') }}
            </div>
            <div class="list-group list-group-flush">
                <a href="<?= url('/profile') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-medium">{{ __('Profile Settings') }}</div>
                        <div class="small text-secondary">{{ __('Update your name, email and password') }}</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
                @can('manage-system')
                <a href="<?= url('/system') ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-medium">{{ __('System Status') }}</div>
                        <div class="small text-secondary">{{ __('Environment check & security audit') }}</div>
                    </div>
                    <i class="bi bi-chevron-right text-muted"></i>
                </a>
                @endcan
                <a href="https://github.com/Solayman/bongo-php" target="_blank" rel="noopener" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-medium">{{ __('GitHub Repository') }}</div>
                        <div class="small text-secondary">{{ __('Source code, docs and updates') }}</div>
                    </div>
                    <i class="bi bi-box-arrow-up-right text-muted"></i>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
