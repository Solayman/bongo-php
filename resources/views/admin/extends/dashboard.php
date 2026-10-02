@extends('admin.master')
@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('clearViewCache')">👀 Clear View Cache</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('clearConfigCache')">⚙️ Clear Config Cache</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('clearLogs')">📝 Clear Logs</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('clearSessions')">🔐 Clear Sessions</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('clearDebugbar')">🔧 Clear Debugbar</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-warning w-100 py-3" onclick="run('clearAll')">🚀 Clear ALL</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-success w-100 py-3" onclick="run('optimize')">⚡ Optimize</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-info w-100 py-3" onclick="run('showSystemInfo')">🖥️ System Info</button>
    </div>
</div>



@endsection


@set('dashboard_menu_title', 'OC Cleanup Manager Dashboard')

@section('scripts')
<script>
    const AJAX_URL = '/cleanup/ajax';
</script>
<script src="<?= asset('asset/js/common.js') ?>"></script>
@endsection
