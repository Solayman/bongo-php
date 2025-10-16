@extends('admin.master')
<?php $this->sections->start('content'); ?>
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



<?php $this->sections->end(); ?>


<?php $this->sections->set('dashboard_menu_title', 'OC Cleanup Manager Dashboard'); ?>

<?php $this->sections->start('scripts'); ?>
<script>
    const AJAX_URL = '/cleanup/ajax';
</script>
<script src="<?= asset('asset/js/common.js') ?>"></script>
<?php $this->sections->end(); ?>
