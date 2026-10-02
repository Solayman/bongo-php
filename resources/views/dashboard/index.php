@extends('layouts.app')

@set('title', 'Dashboard')

@section('content')
<h1 class="h3 mb-4">Dashboard</h1>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Database</div>
                <?php if ($db['connected']): ?>
                    <div class="fs-5 fw-semibold text-success">Connected</div>
                <?php else: ?>
                    <div class="fs-5 fw-semibold text-danger">Not connected</div>
                <?php endif; ?>
                <div class="small text-muted">{{ $connection['username'] }}@{{ $connection['host'] }}:{{ $connection['port'] }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Schema</div>
                <div class="fs-5 fw-semibold">{{ $connection['database'] }}</div>
                <div class="small text-muted">MySQL {{ $db['version'] ?? 'n/a' }} · PHP {{ PHP_VERSION }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">Tables</div>
                <div class="fs-5 fw-semibold">{{ count($db['tables']) }}</div>
                <div class="small text-muted">≈ {{ number_format($totalRows) }} rows · {{ $totalSizeMb }} MB</div>
            </div>
        </div>
    </div>
</div>

<?php if ($db['error']): ?>
    <div class="alert alert-danger">
        <strong>Database error:</strong> {{ $db['error'] }}<br>
        <span class="small">Check the <code>DB_*</code> values in <code>.env</code>.</span>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header fw-semibold">Framework self-check</div>
            <ul class="list-group list-group-flush">
                <?php foreach ($checks as $name => $ok): ?>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $name }}</span>
                        <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-danger' ?>"><?= $ok ? 'OK' : 'FAIL' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header fw-semibold">Tables in {{ $connection['database'] }}</div>
            <div class="table-responsive" style="max-height: 480px;">
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>Table</th>
                            <th class="text-end">≈ Rows</th>
                            <th class="text-end">Size (KB)</th>
                            <th>Engine</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($db['tables'] as $table): ?>
                            <tr>
                                <td>{{ $table->TABLE_NAME }}</td>
                                <td class="text-end">{{ number_format((int) $table->TABLE_ROWS) }}</td>
                                <td class="text-end">{{ number_format(((int) $table->DATA_LENGTH + (int) $table->INDEX_LENGTH) / 1024) }}</td>
                                <td>{{ $table->ENGINE ?? '' }}</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$db['tables']): ?>
                            <tr><td colspan="4" class="text-muted text-center py-3">No tables found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
