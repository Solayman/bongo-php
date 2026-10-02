{: php $stats = debug_stats() :}
<footer class="border-top bg-white px-4 py-2 small text-secondary d-flex flex-wrap gap-3 align-items-center">
    <span class="badge text-bg-warning">DEBUG</span>
    <span><strong>{{ $stats['queries'] }}</strong> queries · {{ $stats['query_ms'] }} ms</span>
    <span>page {{ $stats['page_ms'] }} ms</span>
    <span>{{ $stats['memory'] }} MB</span>
    {: if $stats['queries'] :}
        <details class="w-100">
            <summary>Show queries</summary>
            <ol class="mb-0 mt-2 font-monospace" style="font-size: 12px;">
                {: each $stats['log'] as $q :}
                    <li><span class="text-body">{{ $q['sql'] }}</span>
                        {: if $q['bindings'] :} <span class="text-secondary">[{{ implode(', ', array_map(fn($b) => var_export($b, true), $q['bindings'])) }}]</span> {: end :}
                        <span class="text-secondary">{{ $q['ms'] }} ms</span></li>
                {: end :}
            </ol>
        </details>
    {: end :}
</footer>
