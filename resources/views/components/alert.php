{{-- <x-alert type="success" dismissible>Saved!</x-alert> · type: success, danger, warning, info --}}
@php($icons = ['success' => 'bi-check-circle', 'danger' => 'bi-exclamation-triangle', 'warning' => 'bi-exclamation-triangle', 'info' => 'bi-info-circle'])
<div class="alert alert-{{ $type ?? 'info' }}@if(!empty($dismissible)) alert-dismissible fade show @endif" role="alert">
    <i class="bi {{ $icons[$type ?? 'info'] ?? 'bi-info-circle' }} me-1"></i>{{ $slot }}
    @if(!empty($dismissible))
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
