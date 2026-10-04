@php($stats = debug_stats())
<footer class="border-top bg-white px-4 py-2 small text-secondary d-flex flex-wrap gap-3 align-items-center">
    <span class="badge text-bg-warning">DEBUG</span>
    <span><strong>{{ $stats['queries'] }}</strong> queries · {{ $stats['query_ms'] }} ms</span>
    <span>page {{ $stats['page_ms'] }} ms</span>
    <span>{{ $stats['memory'] }} MB</span>
    @if($stats['repeated'])
        <details class="w-100 text-danger">
            <summary><strong>Possible N+1:</strong> {{ count($stats['repeated']) }} quer{{ count($stats['repeated']) === 1 ? 'y' : 'ies' }} repeated in a loop. Load the relation up front: Model::with('relation')->get()</summary>
            <ul class="mb-0 mt-2 font-monospace" style="font-size: 12px;">
                @foreach($stats['repeated'] as $sql => $times)
                    <li><strong>{{ $times }}×</strong> {{ $sql }}</li>
                @endforeach
            </ul>
        </details>
    @endif
    @if($stats['queries'])
        <details class="w-100">
            <summary>Show queries</summary>
            <ol class="mb-0 mt-2 font-monospace" style="font-size: 12px;">
                @foreach($stats['log'] as $q)
                    <li><span class="text-body">{{ $q['sql'] }}</span>
                        @if($q['bindings']) <span class="text-secondary">[{{ implode(', ', array_map(fn($b) => var_export($b, true), $q['bindings'])) }}]</span> @endif
                        <span class="text-secondary">{{ $q['ms'] }} ms</span></li>
                @endforeach
            </ol>
        </details>
    @endif
</footer>
