@extends('layouts.admin')

@set('title', $mail['subject'] ?: '(no subject)')

@section('content')
<p><a href="{{ route('system') }}#mailbox"><i class="bi bi-arrow-left"></i> Mailbox</a></p>

<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0 small">
            <dt class="col-sm-2">From</dt><dd class="col-sm-10">{{ implode(' ', array_filter($mail['from'] ?? [])) }}</dd>
            <dt class="col-sm-2">To</dt><dd class="col-sm-10">{{ implode(', ', array_map(fn($a) => implode(' ', array_filter($a)), $mail['to'] ?? [])) }}</dd>
            @if($mail['cc'] ?? [])
                <dt class="col-sm-2">Cc</dt><dd class="col-sm-10">{{ implode(', ', array_column($mail['cc'], 0)) }}</dd>
            @endif
            <dt class="col-sm-2">Subject</dt><dd class="col-sm-10 fw-medium">{{ $mail['subject'] }}</dd>
            @if($mail['attachments'] ?? [])
                <dt class="col-sm-2">Attachments</dt>
                <dd class="col-sm-10">
                    @foreach($mail['attachments'] as $file)
                        <span class="badge text-bg-light border me-1"><i class="bi bi-paperclip"></i> {{ $file['name'] }} · {{ $file['mime'] }} · {{ number_format(strlen(base64_decode($file['content'])) / 1024, 1) }} KB</span>
                    @endforeach
                </dd>
            @endif
        </dl>
    </div>
</div>

@if($mail['html'] ?? null)
    {{-- The email's own HTML, in a sandbox: no scripts, no access to this page --}}
    <iframe sandbox="" srcdoc="{{ $mail['html'] }}" title="Email preview" class="w-100 border rounded bg-white" style="height: 520px;"></iframe>
@endif

@if($mail['text'] ?? null)
    <details class="mt-3"><summary class="small text-secondary">Plain-text version</summary>
        <pre class="border rounded bg-white p-3 small mt-2">{{ $mail['text'] }}</pre>
    </details>
@endif
@endsection
