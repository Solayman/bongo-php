@extends('layouts.app')

@set('title', 'System status')

@section('content')
<h1 class="h4 mb-3">System status</h1>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">Database</div>
            <?php if ($db['connected']): ?>
                <div class="value"><i class="bi bi-check-circle-fill text-success fs-5"></i> Connected</div>
            <?php else: ?>
                <div class="value"><i class="bi bi-x-circle-fill text-danger fs-5"></i> Not connected</div>
            <?php endif; ?>
            <div class="sub">{{ $connection['username'] }}@{{ $connection['host'] }}:{{ $connection['port'] }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">Schema</div>
            <div class="value">{{ $connection['database'] }}</div>
            <div class="sub">MySQL {{ $db['version'] ?? 'n/a' }} · PHP {{ PHP_VERSION }}</div>
        </div></div>
    </div>
    <div class="col-md-4">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">Tables</div>
            <div class="value">{{ count($db['tables']) }}</div>
            <div class="sub">Environment: {{ config('app.env') }}</div>
        </div></div>
    </div>
</div>

<?php if ($db['error']): ?>
    <div class="alert alert-danger"><strong>Database error:</strong> {{ $db['error'] }}</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Framework self-check</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($checks as $name => $ok): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $name }}</span>
                        <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $ok ? 'OK' : (str_contains($name, 'optional') ? 'Not installed' : 'FAIL') ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Tables in {{ $connection['database'] }}</div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light"><tr><th>Table</th><th class="num">≈ Rows</th><th class="num">Size (KB)</th><th>Engine</th></tr></thead>
                    <tbody>
                    <?php foreach ($db['tables'] as $table): ?>
                        <tr>
                            <td>{{ $table->TABLE_NAME }}</td>
                            <td class="num">{{ number_format((int) $table->TABLE_ROWS) }}</td>
                            <td class="num">{{ number_format(((int) $table->DATA_LENGTH + (int) $table->INDEX_LENGTH) / 1024) }}</td>
                            <td>{{ $table->ENGINE ?? '' }}</td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
