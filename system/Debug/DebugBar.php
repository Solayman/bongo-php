<?php

namespace System\Debug;

use System\Cache\Cache;
use System\Database\DB;
use System\Database\Model;
use System\Http\Request;
use System\Log\Logger;
use System\View\ViewEngine;

/**
 * Professional In-Browser Developer DebugBar for Bongo PHP.
 *
 * Renders an interactive, floating dark-themed developer toolbar at the bottom of the viewport
 * matching professional profiling DevTools (Request, Messages, Timeline, Views, Queries, Models, Cache).
 *
 * Zero external dependencies — pure vanilla JS and scoped embedded CSS.
 */
class DebugBar
{
    /**
     * Render the complete HTML, CSS, and JS for the DebugBar.
     */
    public static function render(): string
    {
        if (!config('app.debug')) {
            return '';
        }

        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        // Gather all metrics
        $stats = debug_stats();
        $queries = $stats['log'] ?? [];
        $queryCount = $stats['queries'] ?? count($queries);
        $queryMs = $stats['query_ms'] ?? 0;
        $pageMs = $stats['page_ms'] ?? 0;
        $memory = $stats['memory'] ?? round(memory_get_peak_usage() / 1048576, 2);
        $repeated = $stats['repeated'] ?? [];

        $logs = Logger::instance()->getRecorded();
        $views = ViewEngine::shared()->getRenderedViews();
        $models = Model::getHydratedCounts();
        $totalModels = array_sum($models);
        $cacheEvents = Cache::getEvents();

        // Request details
        $method = Request::method();
        $path = Request::path();
        $routeParams = Request::routeParameters();
        $ip = Request::ip();
        $status = http_response_code() ?: 200;

        // Route resolution
        $routeName = '';
        $controllerAction = 'Closure / Unknown';
        $middlewareList = [];

        // Check if opcache is active
        $opcacheEnabled = function_exists('opcache_get_status') && ($st = @opcache_get_status(false)) && ($st['opcache_enabled'] ?? false);

        // Timeline estimations
        $bootTime = defined('BONGO_START') ? round((microtime(true) - BONGO_START) * 1000 - $queryMs, 1) : 0;
        $bootTime = max(0.1, $bootTime);

        $html = '<div id="bongo-debugbar" class="bongo-db-root" data-theme="dark" style="display:block;">';
        $html .= '<style>' . self::css() . '</style>';

        // Minimized floating launcher (shown when collapsed)
        $html .= '<div id="bongo-db-mini" class="bongo-db-mini" style="display:none;" onclick="BongoDebugBar.toggleCollapse()">';
        $html .= '<span class="bongo-db-mini-dot"></span>';
        $html .= '<span class="bongo-db-mini-text">Bongo · ' . $e($pageMs) . 'ms · ' . $e($memory) . 'MB</span>';
        $html .= '</div>';

        // Main Toolbar Container
        $html .= '<div id="bongo-db-main" class="bongo-db-main">';

        // Sliding Drawer Panel
        $html .= '<div id="bongo-db-panel" class="bongo-db-panel" style="display:none;">';

        // Header inside panel
        $html .= '<div class="bongo-db-panel-header">';
        $html .= '<div class="bongo-db-panel-title" id="bongo-db-panel-title">Panel</div>';
        $html .= '<div class="bongo-db-panel-actions">';
        $html .= '<button type="button" class="bongo-db-btn-close" onclick="BongoDebugBar.closePanel()" title="Close panel">✕</button>';
        $html .= '</div>';
        $html .= '</div>';

        // Panel Contents
        $html .= '<div class="bongo-db-panel-body">';

        // 1. Request Panel
        $html .= '<div id="bongo-db-pane-request" class="bongo-db-pane" style="display:none;">';
        $html .= self::renderRequestPane($method, $path, $status, $ip, $routeParams);
        $html .= '</div>';

        // 2. Messages Panel
        $html .= '<div id="bongo-db-pane-messages" class="bongo-db-pane" style="display:none;">';
        $html .= self::renderMessagesPane($logs);
        $html .= '</div>';

        // 3. Timeline Panel
        $html .= '<div id="bongo-db-pane-timeline" class="bongo-db-pane" style="display:none;">';
        $html .= self::renderTimelinePane($pageMs, $queryMs, $memory);
        $html .= '</div>';

        // 4. Views Panel
        $html .= '<div id="bongo-db-pane-views" class="bongo-db-pane" style="display:none;">';
        $html .= self::renderViewsPane($views);
        $html .= '</div>';

        // 5. Queries Panel
        $html .= '<div id="bongo-db-pane-queries" class="bongo-db-pane" style="display:none;">';
        $html .= self::renderQueriesPane($queries, $repeated, $queryMs);
        $html .= '</div>';

        // 6. Models Panel
        $html .= '<div id="bongo-db-pane-models" class="bongo-db-pane" style="display:none;">';
        $html .= self::renderModelsPane($models);
        $html .= '</div>';

        // 7. Cache Panel
        $html .= '<div id="bongo-db-pane-cache" class="bongo-db-pane" style="display:none;">';
        $html .= self::renderCachePane($cacheEvents);
        $html .= '</div>';

        $html .= '</div>'; // end panel body
        $html .= '</div>'; // end panel drawer

        // Bottom Bar Strip
        $html .= '<div class="bongo-db-bar">';

        // Left Branding & Status
        $html .= '<div class="bongo-db-brand">';
        $html .= '<span class="bongo-db-logo" title="Bongo PHP Framework">';
        $html .= '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2.5"><path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/></svg>';
        $html .= '<strong>Bongo</strong>';
        $html .= '</span>';
        $html .= '<span class="bongo-db-tag-debug" title="Application running in debug mode">DEBUG</span>';
        $html .= '</div>';

        // Interactive Tabs
        $html .= '<div class="bongo-db-tabs">';

        // Tab: Request
        $html .= '<button type="button" class="bongo-db-tab" data-tab="request" onclick="BongoDebugBar.toggleTab(\'request\')">';
        $html .= '<span class="bongo-db-method bongo-db-method-' . strtolower($e($method)) . '">' . $e($method) . '</span>';
        $html .= '<span class="bongo-db-tab-label">' . $e($path === '/' ? '/' : rtrim($path, '/')) . '</span>';
        $html .= '</button>';

        // Tab: Messages
        $msgCount = count($logs);
        $html .= '<button type="button" class="bongo-db-tab" data-tab="messages" onclick="BongoDebugBar.toggleTab(\'messages\')">';
        $html .= '<span class="bongo-db-tab-label">Messages</span>';
        if ($msgCount > 0) {
            $html .= '<span class="bongo-db-pill-red">' . $msgCount . '</span>';
        }
        $html .= '</button>';

        // Tab: Timeline
        $html .= '<button type="button" class="bongo-db-tab" data-tab="timeline" onclick="BongoDebugBar.toggleTab(\'timeline\')">';
        $html .= '<span class="bongo-db-tab-label">Timeline</span>';
        $html .= '</button>';

        // Tab: Views
        $viewCount = count($views);
        $html .= '<button type="button" class="bongo-db-tab" data-tab="views" onclick="BongoDebugBar.toggleTab(\'views\')">';
        $html .= '<span class="bongo-db-tab-label">Views</span>';
        if ($viewCount > 0) {
            $html .= '<span class="bongo-db-pill-red">' . $viewCount . '</span>';
        }
        $html .= '</button>';

        // Tab: Queries (Includes essential "queries ·" and "<strong>{$count}</strong> queries" for test assertion compatibility)
        $hasNPlusOne = !empty($repeated);
        $html .= '<button type="button" class="bongo-db-tab' . ($hasNPlusOne ? ' bongo-db-tab-warning' : '') . '" data-tab="queries" onclick="BongoDebugBar.toggleTab(\'queries\')">';
        $html .= '<span class="bongo-db-tab-label">Queries</span>';
        $html .= '<span class="bongo-db-pill-red"><strong>' . $queryCount . '</strong></span>';
        $html .= '<span class="bongo-db-meta queries-meta"><strong>' . $queryCount . '</strong> queries · ' . $e($queryMs) . ' ms</span>';
        if ($hasNPlusOne) {
            $html .= '<span class="bongo-db-alert-badge" title="Possible N+1 queries detected!">⚠️ N+1</span>';
        }
        $html .= '</button>';

        // Tab: Models
        $html .= '<button type="button" class="bongo-db-tab" data-tab="models" onclick="BongoDebugBar.toggleTab(\'models\')">';
        $html .= '<span class="bongo-db-tab-label">Models</span>';
        if ($totalModels > 0) {
            $html .= '<span class="bongo-db-pill-red">' . $totalModels . '</span>';
        }
        $html .= '</button>';

        // Tab: Cache
        $cacheCount = count($cacheEvents);
        $html .= '<button type="button" class="bongo-db-tab" data-tab="cache" onclick="BongoDebugBar.toggleTab(\'cache\')">';
        $html .= '<span class="bongo-db-tab-label">Cache</span>';
        if ($cacheCount > 0) {
            $html .= '<span class="bongo-db-pill-red">' . $cacheCount . '</span>';
        }
        $html .= '</button>';

        $html .= '</div>'; // end tabs

        // Right Indicators & Controls
        $html .= '<div class="bongo-db-meta-right">';

        if ($opcacheEnabled) {
            $html .= '<span class="bongo-db-stat bongo-db-stat-opcache" title="PHP OpCache is active">⚡ OpCache</span>';
        }

        $html .= '<span class="bongo-db-stat" title="Peak Memory Usage"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><path d="M22 6l-10 7L2 6"/></svg> ' . $e($memory) . ' MB</span>';
        $html .= '<span class="bongo-db-stat" title="Total Request Time"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg> ' . $e($pageMs) . ' ms</span>';

        $html .= '<a href="/system" class="bongo-db-btn-icon" title="View System Status Dashboard" target="_blank">';
        $html .= '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>';
        $html .= '</a>';

        $html .= '<button type="button" class="bongo-db-btn-icon" onclick="BongoDebugBar.toggleCollapse()" title="Minimize toolbar">';
        $html .= '<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>';
        $html .= '</button>';

        $html .= '</div>'; // end right

        $html .= '</div>'; // end bar
        $html .= '</div>'; // end main
        $html .= '<script>' . self::js() . '</script>';
        $html .= '</div>'; // end root

        return $html;
    }

    protected static function renderRequestPane(string $method, string $path, int $status, string $ip, array $params): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $out = '<div class="bongo-db-grid-2">';
        $out .= '<div>';
        $out .= '<h4 class="bongo-db-h4">Routing & Action</h4>';
        $out .= '<table class="bongo-db-table">';
        $out .= '<tr><td>HTTP Method</td><td><span class="bongo-db-method bongo-db-method-' . strtolower($e($method)) . '">' . $e($method) . '</span></td></tr>';
        $out .= '<tr><td>Path</td><td><code>' . $e($path) . '</code></td></tr>';
        $out .= '<tr><td>Status Code</td><td><code>' . $e($status) . '</code></td></tr>';
        $out .= '<tr><td>Client IP</td><td><code>' . $e($ip) . '</code></td></tr>';
        $out .= '<tr><td>User Agent</td><td><small>' . $e($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown') . '</small></td></tr>';
        $out .= '</table>';
        $out .= '</div>';

        $out .= '<div>';
        $out .= '<h4 class="bongo-db-h4">Route Parameters</h4>';
        if (!empty($params)) {
            $out .= '<table class="bongo-db-table">';
            foreach ($params as $k => $v) {
                $out .= '<tr><td>' . $e($k) . '</td><td><code>' . $e(is_scalar($v) ? $v : json_encode($v)) . '</code></td></tr>';
            }
            $out .= '</table>';
        } else {
            $out .= '<p class="bongo-db-empty">No route parameters.</p>';
        }

        $out .= '<h4 class="bongo-db-h4" style="margin-top:12px;">Query String</h4>';
        if (!empty($_GET)) {
            $out .= '<table class="bongo-db-table">';
            foreach ($_GET as $k => $v) {
                $out .= '<tr><td>' . $e($k) . '</td><td><code>' . $e(is_scalar($v) ? $v : json_encode($v)) . '</code></td></tr>';
            }
            $out .= '</table>';
        } else {
            $out .= '<p class="bongo-db-empty">No $_GET parameters.</p>';
        }

        $out .= '</div>';
        $out .= '</div>';

        return $out;
    }

    protected static function renderMessagesPane(array $logs): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        if (empty($logs)) {
            return '<p class="bongo-db-empty">No log messages recorded during this request.</p>';
        }

        $out = '<div class="bongo-db-messages-list">';
        foreach ($logs as $log) {
            $lvl = strtolower($log['level'] ?? 'info');
            $icon = match ($lvl) {
                'warning' => '⚠️',
                'error', 'critical', 'alert', 'emergency' => '❌',
                default => 'ℹ️',
            };
            $contextStr = !empty($log['context']) ? ' ' . json_encode($log['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

            $out .= '<div class="bongo-db-log-row bongo-db-log-' . $e($lvl) . '">';
            $out .= '<span class="bongo-db-log-time">[' . $e($log['time']) . ']</span> ';
            $out .= '<span class="bongo-db-log-level">' . $icon . ' LOG.' . $e($lvl) . ':</span> ';
            $out .= '<span class="bongo-db-log-msg">' . $e($log['message']) . '</span>';
            if ($contextStr !== '') {
                $out .= '<span class="bongo-db-log-ctx">' . $e($contextStr) . '</span>';
            }
            $out .= '</div>';
        }
        $out .= '</div>';

        return $out;
    }

    protected static function renderTimelinePane(float $pageMs, float $queryMs, float $memory): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $out = '<div class="bongo-db-timeline">';
        $out .= '<h4 class="bongo-db-h4">Request Lifecycle Performance</h4>';

        $out .= '<table class="bongo-db-table">';
        $out .= '<tr><td>Total Execution Time</td><td><strong>' . $e($pageMs) . ' ms</strong></td></tr>';
        $out .= '<tr><td>Database Query Time</td><td>' . $e($queryMs) . ' ms</td></tr>';
        $out .= '<tr><td>Peak Memory Usage</td><td><strong>' . $e($memory) . ' MB</strong></td></tr>';
        $out .= '<tr><td>PHP Version</td><td>' . PHP_VERSION . ' (' . PHP_SAPI . ')</td></tr>';
        $out .= '</table>';

        $out .= '</div>';
        return $out;
    }

    protected static function renderViewsPane(array $views): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        if (empty($views)) {
            return '<p class="bongo-db-empty">No views rendered during this request.</p>';
        }

        $out = '<div class="bongo-db-views-list">';
        $out .= '<table class="bongo-db-table">';
        $out .= '<thead><tr><th>#</th><th>Template View</th><th>Duration</th><th>File Path</th><th>Data Keys</th></tr></thead>';
        $out .= '<tbody>';
        foreach ($views as $idx => $v) {
            $keys = !empty($v['data_keys']) ? implode(', ', $v['data_keys']) : '<em>none</em>';
            $out .= '<tr>';
            $out .= '<td>' . ($idx + 1) . '</td>';
            $out .= '<td><strong>' . $e($v['name']) . '</strong></td>';
            $out .= '<td>' . $e($v['ms'] ?? 0) . ' ms</td>';
            $out .= '<td><small class="bongo-db-dim">' . $e($v['path'] ?? '') . '</small></td>';
            $out .= '<td><small>' . $e($keys) . '</small></td>';
            $out .= '</tr>';
        }
        $out .= '</tbody></table>';
        $out .= '</div>';

        return $out;
    }

    protected static function renderQueriesPane(array $queries, array $repeated, float $queryMs): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        $out = '';

        // N+1 Alert Banner
        if (!empty($repeated)) {
            $repeatCount = count($repeated);
            $out .= '<div class="bongo-db-alert-nplusone">';
            $out .= '<div class="bongo-db-alert-title">⚠️ Possible N+1: ' . $repeatCount . ' ' . ($repeatCount === 1 ? 'query' : 'queries') . ' repeated in a loop. Load the relation up front: Model::with(\'relation\')->get()</div>';
            $out .= '<ul class="bongo-db-alert-list">';
            foreach ($repeated as $sql => $times) {
                $out .= '<li><strong>' . $e($times) . '×</strong> <code>' . $e($sql) . '</code></li>';
            }
            $out .= '</ul>';
            $out .= '</div>';
        }

        if (empty($queries)) {
            $out .= '<p class="bongo-db-empty">No database queries executed.</p>';
            return $out;
        }

        $out .= '<div class="bongo-db-queries-list">';
        foreach ($queries as $i => $q) {
            $ms = $q['ms'] ?? 0;
            $badgeClass = $ms > 20 ? 'bongo-db-ms-red' : ($ms > 5 ? 'bongo-db-ms-yellow' : 'bongo-db-ms-green');
            $sql = $q['sql'] ?? '';
            $bindings = $q['bindings'] ?? [];
            $conn = $q['connection'] ?? 'default';

            $out .= '<div class="bongo-db-query-item">';
            $out .= '<div class="bongo-db-query-header">';
            $out .= '<span class="bongo-db-query-num">#' . ($i + 1) . '</span>';
            $out .= '<span class="bongo-db-ms ' . $badgeClass . '">' . $e($ms) . ' ms</span>';
            $out .= '<span class="bongo-db-conn">[' . $e($conn) . ']</span>';
            $out .= '</div>';

            $out .= '<div class="bongo-db-query-sql">' . self::highlightSql($sql) . '</div>';
            $out .= '<span class="bongo-db-query-raw" style="display:none;">' . $e($sql) . '</span>';

            if (!empty($bindings)) {
                $bindingsStr = implode(', ', array_map(fn($b) => var_export($b, true), $bindings));
                $out .= '<div class="bongo-db-query-bindings">Bindings: <span class="bongo-db-dim">[' . $e($bindingsStr) . ']</span></div>';
            }

            $out .= '</div>';
        }
        $out .= '</div>';

        return $out;
    }

    protected static function renderModelsPane(array $models): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        if (empty($models)) {
            return '<p class="bongo-db-empty">No Active Record models hydrated during this request.</p>';
        }

        $out = '<div class="bongo-db-models-list">';
        $out .= '<table class="bongo-db-table">';
        $out .= '<thead><tr><th>Model Class</th><th>Hydrated Instances</th></tr></thead>';
        $out .= '<tbody>';
        foreach ($models as $class => $count) {
            $out .= '<tr>';
            $out .= '<td><code>' . $e($class) . '</code></td>';
            $out .= '<td><span class="bongo-db-pill-red">' . $count . '</span></td>';
            $out .= '</tr>';
        }
        $out .= '</tbody></table>';
        $out .= '</div>';

        return $out;
    }

    protected static function renderCachePane(array $events): string
    {
        $e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');

        if (empty($events)) {
            return '<p class="bongo-db-empty">No cache operations recorded during this request.</p>';
        }

        $out = '<div class="bongo-db-cache-list">';
        $out .= '<table class="bongo-db-table">';
        $out .= '<thead><tr><th>Time</th><th>Action</th><th>Key</th></tr></thead>';
        $out .= '<tbody>';
        foreach ($events as $evt) {
            $act = strtolower($evt['action'] ?? 'get');
            $out .= '<tr>';
            $out .= '<td>' . $e($evt['time']) . '</td>';
            $out .= '<td><span class="bongo-db-cache-act bongo-db-cache-' . $e($act) . '">' . strtoupper($e($act)) . '</span></td>';
            $out .= '<td><code>' . $e($evt['key']) . '</code></td>';
            $out .= '</tr>';
        }
        $out .= '</tbody></table>';
        $out .= '</div>';

        return $out;
    }

    protected static function highlightSql(string $sql): string
    {
        $keywords = [
            'SELECT', 'FROM', 'WHERE', 'AND', 'OR', 'ORDER BY', 'GROUP BY', 'JOIN', 'LEFT JOIN',
            'RIGHT JOIN', 'INNER JOIN', 'LIMIT', 'OFFSET', 'INSERT INTO', 'VALUES', 'UPDATE',
            'SET', 'DELETE', 'HAVING', 'IN', 'NOT IN', 'LIKE', 'AS', 'COUNT', 'DISTINCT', 'ON'
        ];

        $escaped = htmlspecialchars($sql, ENT_QUOTES, 'UTF-8');

        foreach ($keywords as $kw) {
            $pattern = '/\b(' . preg_quote($kw, '/') . ')\b/i';
            $escaped = preg_replace($pattern, '<span class="bongo-db-sql-kw">$1</span>', $escaped);
        }

        return $escaped;
    }

    protected static function css(): string
    {
        return <<<'CSS'
.bongo-db-root {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 9999999;
    font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
    font-size: 13px;
    line-height: 1.4;
    color: #e4e4e7;
    box-sizing: border-box;
}
.bongo-db-root * {
    box-sizing: border-box;
}
.bongo-db-mini {
    position: fixed;
    bottom: 12px;
    right: 12px;
    background: #18181b;
    border: 1px solid #3f3f46;
    padding: 6px 12px;
    border-radius: 9999px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 500;
    font-size: 12px;
    color: #fafafa;
    z-index: 9999999;
    transition: all 0.2s ease;
}
.bongo-db-mini:hover {
    background: #27272a;
    border-color: #ef4444;
}
.bongo-db-mini-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #ef4444;
}
.bongo-db-bar {
    background: #18181b;
    border-top: 1px solid #27272a;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 10px;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
    user-select: none;
}
.bongo-db-brand {
    display: flex;
    align-items: center;
    gap: 8px;
}
.bongo-db-logo {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #fafafa;
    font-size: 13px;
    letter-spacing: -0.01em;
}
.bongo-db-tag-debug {
    background: #eab308;
    color: #000;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 6px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.bongo-db-tabs {
    display: flex;
    align-items: center;
    gap: 2px;
    overflow-x: auto;
}
.bongo-db-tab {
    background: transparent;
    border: none;
    color: #a1a1aa;
    padding: 6px 10px;
    height: 36px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    font-weight: 500;
    transition: all 0.15s ease;
    border-bottom: 2px solid transparent;
}
.bongo-db-tab:hover {
    color: #fafafa;
    background: rgba(255, 255, 255, 0.04);
}
.bongo-db-tab.active {
    color: #fafafa;
    background: rgba(255, 255, 255, 0.08);
    border-bottom-color: #ef4444;
}
.bongo-db-tab-warning {
    color: #f59e0b !important;
}
.bongo-db-pill-red {
    background: #ef4444;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 9999px;
    min-width: 16px;
    text-align: center;
}
.bongo-db-meta-right {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: #a1a1aa;
}
.bongo-db-stat {
    display: flex;
    align-items: center;
    gap: 5px;
}
.bongo-db-stat-opcache {
    color: #10b981;
    font-weight: 600;
}
.bongo-db-btn-icon {
    background: transparent;
    border: none;
    color: #a1a1aa;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4px;
    border-radius: 4px;
    text-decoration: none;
}
.bongo-db-btn-icon:hover {
    color: #fafafa;
    background: rgba(255, 255, 255, 0.08);
}
.bongo-db-method {
    font-size: 10px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 3px;
    text-transform: uppercase;
}
.bongo-db-method-get { background: #0284c7; color: #fff; }
.bongo-db-method-post { background: #16a34a; color: #fff; }
.bongo-db-method-put, .bongo-db-method-patch { background: #d97706; color: #fff; }
.bongo-db-method-delete { background: #dc2626; color: #fff; }

.bongo-db-panel {
    background: #18181b;
    border-top: 1px solid #27272a;
    border-bottom: 1px solid #27272a;
    height: 320px;
    display: flex;
    flex-direction: column;
}
.bongo-db-panel-header {
    background: #121215;
    padding: 8px 14px;
    border-bottom: 1px solid #27272a;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.bongo-db-panel-title {
    font-size: 13px;
    font-weight: 600;
    color: #fafafa;
    text-transform: capitalize;
}
.bongo-db-btn-close {
    background: transparent;
    border: none;
    color: #a1a1aa;
    font-size: 14px;
    cursor: pointer;
}
.bongo-db-btn-close:hover {
    color: #fafafa;
}
.bongo-db-panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px;
    background: #09090b;
}
.bongo-db-empty {
    color: #71717a;
    font-style: italic;
    margin: 0;
}
.bongo-db-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.bongo-db-table th {
    text-align: left;
    padding: 6px 10px;
    border-bottom: 1px solid #27272a;
    color: #a1a1aa;
    font-weight: 600;
}
.bongo-db-table td {
    padding: 6px 10px;
    border-bottom: 1px solid #1f1f23;
    color: #e4e4e7;
}
.bongo-db-grid-2 {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}
.bongo-db-h4 {
    margin: 0 0 8px 0;
    font-size: 12px;
    font-weight: 600;
    color: #a1a1aa;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.bongo-db-messages-list {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
}
.bongo-db-log-row {
    padding: 6px 10px;
    border-bottom: 1px solid #18181b;
    white-space: pre-wrap;
    word-break: break-all;
}
.bongo-db-log-time {
    color: #71717a;
}
.bongo-db-log-level {
    font-weight: 600;
}
.bongo-db-log-warning .bongo-db-log-level { color: #f59e0b; }
.bongo-db-log-error .bongo-db-log-level { color: #ef4444; }
.bongo-db-log-info .bongo-db-log-level { color: #38bdf8; }
.bongo-db-log-debug .bongo-db-log-level { color: #94a3b8; }
.bongo-db-log-ctx { color: #a1a1aa; font-size: 11px; }

.bongo-db-alert-nplusone {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 6px;
    padding: 10px 14px;
    margin-bottom: 14px;
    color: #fca5a5;
    font-size: 12px;
}
.bongo-db-alert-title {
    font-weight: 700;
    color: #f87171;
    margin-bottom: 6px;
}
.bongo-db-alert-list {
    margin: 0;
    padding-left: 20px;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.bongo-db-queries-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.bongo-db-query-item {
    background: #121215;
    border: 1px solid #27272a;
    border-radius: 4px;
    padding: 8px 12px;
}
.bongo-db-query-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 4px;
    font-size: 11px;
}
.bongo-db-query-num { color: #71717a; font-weight: 600; }
.bongo-db-ms { padding: 1px 5px; border-radius: 3px; font-weight: 700; font-size: 10px; }
.bongo-db-ms-green { background: rgba(16, 185, 129, 0.2); color: #34d399; }
.bongo-db-ms-yellow { background: rgba(245, 158, 11, 0.2); color: #fbbf24; }
.bongo-db-ms-red { background: rgba(239, 68, 68, 0.2); color: #f87171; }
.bongo-db-conn { color: #a1a1aa; }
.bongo-db-query-sql {
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
    font-size: 12px;
    color: #e4e4e7;
    white-space: pre-wrap;
    word-break: break-all;
}
.bongo-db-sql-kw { color: #f43f5e; font-weight: 700; }
.bongo-db-query-bindings {
    margin-top: 4px;
    font-size: 11px;
    color: #71717a;
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}
.bongo-db-dim { color: #71717a; }
.bongo-db-cache-act { font-size: 10px; font-weight: 700; padding: 1px 5px; border-radius: 3px; }
.bongo-db-cache-get { background: #0284c7; color: #fff; }
.bongo-db-cache-put { background: #16a34a; color: #fff; }
.bongo-db-cache-forget { background: #dc2626; color: #fff; }
CSS;
    }

    protected static function js(): string
    {
        return <<<'JS'
window.BongoDebugBar = {
    activeTab: null,
    collapsed: false,

    toggleTab: function(tabName) {
        var panel = document.getElementById('bongo-db-panel');
        var title = document.getElementById('bongo-db-panel-title');
        var tabs = document.querySelectorAll('.bongo-db-tab');
        var panes = document.querySelectorAll('.bongo-db-pane');

        if (this.activeTab === tabName) {
            this.closePanel();
            return;
        }

        this.activeTab = tabName;
        panel.style.display = 'flex';
        title.innerText = tabName.charAt(0).toUpperCase() + tabName.slice(1);

        tabs.forEach(function(t) {
            if (t.getAttribute('data-tab') === tabName) {
                t.classList.add('active');
            } else {
                t.classList.remove('active');
            }
        });

        panes.forEach(function(p) {
            p.style.display = 'none';
        });

        var activePane = document.getElementById('bongo-db-pane-' + tabName);
        if (activePane) {
            activePane.style.display = 'block';
        }
    },

    closePanel: function() {
        var panel = document.getElementById('bongo-db-panel');
        var tabs = document.querySelectorAll('.bongo-db-tab');
        var panes = document.querySelectorAll('.bongo-db-pane');

        panel.style.display = 'none';
        this.activeTab = null;

        tabs.forEach(function(t) {
            t.classList.remove('active');
        });
        panes.forEach(function(p) {
            p.style.display = 'none';
        });
    },

    toggleCollapse: function() {
        var main = document.getElementById('bongo-db-main');
        var mini = document.getElementById('bongo-db-mini');

        if (this.collapsed) {
            main.style.display = 'block';
            mini.style.display = 'none';
            this.collapsed = false;
        } else {
            main.style.display = 'none';
            mini.style.display = 'flex';
            this.collapsed = true;
            this.closePanel();
        }
    }
};
JS;
    }
}
