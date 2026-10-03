@if($message = flash('success'))
    <x-alert type="success" dismissible>{{ $message }}</x-alert>
@endif
@if($message = flash('error'))
    <x-alert type="danger" dismissible>{{ $message }}</x-alert>
@endif
@if(errors())
    <x-alert type="danger">{{ __('Please fix the highlighted fields.') }}</x-alert>
@endif
