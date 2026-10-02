{: layout 'layouts.admin' :}

{: block 'title', __('System status') :}

{: block 'content' :}
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-tile h-100"><div class="card-body">
            <div class="label">Database</div>
            {: if $db['connected'] :}
                <div class="value"><i class="bi bi-check-circle-fill text-success fs-5"></i> Connected</div>
            {: else :}
                <div class="value"><i class="bi bi-x-circle-fill text-danger fs-5"></i> Not connected</div>
            {: end :}
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

{: if $db['error'] :}
    <div class="alert alert-danger"><strong>Database error:</strong> {{ $db['error'] }}</div>
{: end :}

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-white fw-semibold">Framework self-check</div>
            <ul class="list-group list-group-flush">
                {: each $checks as $name => $ok :}
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ $name }}</span>
                        <span class="badge <?= $ok ? 'text-bg-success' : 'text-bg-secondary' ?>"><?= $ok ? 'OK' : (str_contains($name, 'optional') ? 'Not installed' : 'FAIL') ?></span>
                    </li>
                {: end :}
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
                    {: each $db['tables'] as $table :}
                        <tr>
                            <td>{{ $table->TABLE_NAME }}</td>
                            <td class="num">{{ number_format((int) $table->TABLE_ROWS) }}</td>
                            <td class="num">{{ number_format(((int) $table->DATA_LENGTH + (int) $table->INDEX_LENGTH) / 1024) }}</td>
                            <td>{{ $table->ENGINE ?? '' }}</td>
                        </tr>
                    {: end :}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
{: if config('app.debug') :}
<div class="card mt-3">
    <div class="card-body d-flex flex-wrap gap-2 align-items-center">
        <span class="fw-semibold me-2">Debug tools</span>
        <a class="btn btn-sm btn-outline-danger" href="<?= route('system.error-test') ?>" target="_blank">Trigger test error</a>
        <a class="btn btn-sm btn-outline-dark" href="<?= route('system.dump-test') ?>" target="_blank">Try dd()</a>
        <span class="small text-secondary">Opens in a new tab. Shown only while APP_DEBUG is on.</span>
    </div>
</div>
{: end :}

<div class="row g-3 mt-1">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header bg-white fw-semibold">Security (live check)</div>
            <ul class="list-group list-group-flush">
                {: each $security as $name => [$ok, $detail] :}
                    <li class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span>{{ $name }}</span>
                            {: if $ok :}
                                <span class="badge text-bg-success">OK</span>
                            {: elseif $name === 'HTTPS' :}
                                <span class="badge text-bg-secondary">Not in use</span>
                            {: else :}
                                <span class="badge text-bg-danger">FAIL</span>
                            {: end :}
                        </div>
                        <div class="small text-secondary">{{ $detail }}</div>
                    </li>
                {: end :}
            </ul>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between">
                <span class="fw-semibold">Migrations</span>
                <span class="small text-secondary">same as <code>php bongo migrate:status</code></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Migration</th><th>Status</th><th class="num">Batch</th><th>Ran at</th></tr></thead>
                    <tbody>
                    {: each $migrations as $m :}
                        <tr>
                            <td class="small">{{ $m['migration'] }}</td>
                            <td>
                                {: if $m['ran'] :}
                                    <span class="badge text-bg-success">Ran</span>
                                {: else :}
                                    <span class="badge text-bg-warning">Pending</span>
                                {: end :}
                            </td>
                            <td class="num">{{ $m['batch'] ?? '' }}</td>
                            <td class="small text-secondary">{{ $m['ran_at'] ?? '' }}</td>
                        </tr>
                    {: end :}
                    {: if !$migrations :}
                        <tr><td colspan="4" class="text-secondary text-center py-3">No migrations found.</td></tr>
                    {: end :}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-1" id="queue">
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between">
                <span class="fw-semibold">Queue</span>
                <span class="badge text-bg-light border">{{ $queue['driver'] }}</span>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between"><span>Waiting</span><span class="num">{{ $queue['pending'] }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Delayed</span><span class="num">{{ $queue['delayed'] }}</span></li>
                <li class="list-group-item d-flex justify-content-between"><span>Running</span><span class="num">{{ $queue['reserved'] }}</span></li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>Failed</span>
                    <span class="num {: if $queue['failed'] :} text-danger fw-semibold {: end :}">{{ $queue['failed'] }}</span>
                </li>
            </ul>
            <div class="card-footer bg-white small text-secondary">
                {: if $queue['driver'] === 'sync' :}
                    Jobs run immediately. Set <code>QUEUE_DRIVER=database</code> and run <code>php bongo queue:work</code> for background jobs.
                {: else :}
                    Worker: <code>php bongo queue:work</code> · failed: <code>php bongo queue:failed</code>
                {: end :}
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card h-100">
            <div class="card-header bg-white d-flex justify-content-between">
                <span class="fw-semibold">Scheduled tasks</span>
                <span class="small text-secondary">cron: <code>* * * * * php bongo schedule:run</code></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead class="table-light"><tr><th>Task</th><th>Cron</th><th>Next run</th></tr></thead>
                    <tbody>
                    {: each $tasks as $task :}
                        <tr><td>{{ $task['name'] }}</td><td><code>{{ $task['cron'] }}</code></td><td class="small text-secondary">{{ $task['next'] ?? '' }}</td></tr>
                    {: end :}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mt-3" id="deployment">
    <div class="card-header bg-white d-flex justify-content-between">
        <span class="fw-semibold">Deployment checklist</span>
        <span class="small text-secondary">same as <code>php bongo security:check</code></span>
    </div>
    <ul class="list-group list-group-flush">
        {: each $deployment as $item :}
            <li class="list-group-item d-flex gap-3 align-items-start">
                {: if $item['status'] === 'ok' :}
                    <span class="badge text-bg-success" style="width: 52px;">OK</span>
                {: elseif $item['status'] === 'warn' :}
                    <span class="badge text-bg-warning" style="width: 52px;">WARN</span>
                {: else :}
                    <span class="badge text-bg-danger" style="width: 52px;">FAIL</span>
                {: end :}
                <div>
                    <div class="fw-medium">{{ $item['check'] }}</div>
                    <div class="small text-secondary">{{ $item['detail'] }}</div>
                </div>
            </li>
        {: end :}
    </ul>
</div>

<div class="card mt-3" id="logs">
    <div class="card-header bg-white d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <div>
            <span class="fw-semibold">Recent log entries</span>
            <span class="small text-secondary ms-2">{{ $logFile }} · newest first</span>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <form method="GET" action="<?= url('/system') ?>#logs" class="d-flex gap-2">
                <select name="level" class="form-select form-select-sm" aria-label="Minimum level" onchange="this.form.submit()">
                    <option value="">All levels</option>
                    {: each ['info', 'warning', 'error'] as $level :}
                        <option value="{{ $level }}" <?= $logLevel === $level ? 'selected' : '' ?>>{{ ucfirst($level) }} and above</option>
                    {: end :}
                </select>
            </form>
            <form method="POST" action="<?= url('/system/log-test') ?>" class="m-0">
                {: csrf :}
                <button class="btn btn-sm btn-outline-dark text-nowrap" type="submit">Write test entry</button>
            </form>
        </div>
    </div>
    <div class="table-responsive" style="max-height: 480px;">
        <table class="table table-sm mb-0">
            <thead class="table-light sticky-top"><tr><th style="width: 160px;">Time</th><th style="width: 100px;">Level</th><th>Message</th></tr></thead>
            <tbody>
            {: each $logs as $entry :}
                <?php $badge = match ($entry['level']) {
                    'debug' => 'text-bg-light border', 'info' => 'text-bg-info', 'notice' => 'text-bg-primary',
                    'warning' => 'text-bg-warning', default => 'text-bg-danger',
                }; ?>
                <tr>
                    <td class="small text-secondary text-nowrap">{{ $entry['time'] }}</td>
                    <td><span class="badge <?= $badge ?>">{{ strtoupper($entry['level']) }}</span></td>
                    <td class="small">
                        <code class="text-body text-wrap" style="word-break: break-word;">{{ $entry['message'] }}</code>
                        {: if $entry['details'] !== '' :}
                            <details class="mt-1"><summary class="small text-secondary">Details</summary><pre class="small mb-0">{{ $entry['details'] }}</pre></details>
                        {: end :}
                    </td>
                </tr>
            {: end :}
            {: if !$logs :}
                <tr><td colspan="3" class="text-secondary text-center py-3">No log entries yet today. Click "Write test entry" or try a wrong password on the login page.</td></tr>
            {: end :}
            </tbody>
        </table>
    </div>
</div>
{: end :}
