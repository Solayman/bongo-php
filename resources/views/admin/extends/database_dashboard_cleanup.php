@extends('admin.master')
@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('resetYapiTokens')">👀  Reset Yapi Tokens</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('resetTmpYapiReports')">⚙️ Reset Tmp Yapi Reports </button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('resetWallets')">📝 Reset Wallets</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-custom w-100 py-3" onclick="run('resetMerchants')">🔐 Reset Merchants</button>
    </div>
    <div class="col-md-4">
        <button class="btn btn-info w-100 py-3" onclick="run('runFullCleanup')">🔐 Full Cleanup</button>
    </div>
</div>

@endsection

@set('dashboard_menu_title', 'Database Manager')

@section('scripts')
<script>
    const AJAX_URL = '/database/clean';
</script>
<script src="<?= asset('asset/js/common.js') ?>"></script>
@endsection
