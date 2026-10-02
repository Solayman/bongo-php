<?php
// Expects $page = paginate() result. Keeps the current query string.
$query = $_GET;
$link = function (int $n) use ($query) {
    $query['page'] = $n;
    return '?' . http_build_query($query);
};
?>
@if($page['last_page'] > 1)
<nav class="d-flex justify-content-between align-items-center mt-3" aria-label="Pagination">
    <span class="small text-secondary">
        Page {{ $page['current_page'] }} of {{ $page['last_page'] }} · {{ $page['total'] }} results
    </span>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page['current_page'] <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($link($page['current_page'] - 1)) ?>">Previous</a>
        </li>
        <?php for ($n = 1; $n <= $page['last_page']; $n++): ?>
            <li class="page-item <?= $n === $page['current_page'] ? 'active' : '' ?>">
                <a class="page-link" href="<?= e($link($n)) ?>"><?= $n ?></a>
            </li>
        <?php endfor; ?>
        <li class="page-item <?= $page['current_page'] >= $page['last_page'] ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($link($page['current_page'] + 1)) ?>">Next</a>
        </li>
    </ul>
</nav>
@endif
