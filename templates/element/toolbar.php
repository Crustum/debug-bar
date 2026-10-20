<?php
/**
 * Quiet toolbar shell for the debug bar.
 *
 * Renders the centered bar and the corner pill forms with the hooks the
 * vanilla shell parts expect: request switcher options use
 * `data-dk-request-option`, theme options use `data-dk-theme-option` and
 * the draggable shell root uses `data-dk-toolbar-shell`. All styling uses
 * `dk-*` component classes scoped under `#debugbar`.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $summary Request summary with `inspectors` entries
 * @var array $recent Recent request rows for the switcher
 */
use function Cake\Core\h;

$requestId = (string)($summary['id'] ?? '');
$method = (string)($summary['method'] ?? 'GET');
$url = (string)($summary['url'] ?? '/');
$statusCode = (int)($summary['status_code'] ?? 0);
$switcherRequests = [];
foreach ((array)$recent as $row) {
    if (!is_array($row) || ($row['id'] ?? '') === '') {
        continue;
    }
    $switcherRequests[] = $row;
}
$currentSeen = false;
foreach ($switcherRequests as $row) {
    if ((string)$row['id'] === $requestId) {
        $currentSeen = true;
        break;
    }
}
if ($currentSeen === false && $requestId !== '') {
    array_unshift($switcherRequests, [
        'id' => $requestId,
        'url' => $url,
        'method' => $method,
        'status_code' => $statusCode,
        'requested_at' => $summary['requested_at'] ?? '',
        'response_size_bytes' => $summary['response_size_bytes'] ?? null,
        'response_size' => $summary['response_size'] ?? null,
    ]);
}
$responseSize = isset($summary['response_size']) && is_string($summary['response_size'])
    ? $summary['response_size']
    : null;
$statusTone = $statusCode >= 500 ? 'bad' : ($statusCode >= 400 ? 'warn' : 'good');
$durationLabel = (string)($summary['duration_label'] ?? '—');
$peakLabel = (string)($summary['peak_memory_label'] ?? '—');
$queryCount = (int)($summary['query_count'] ?? 0);
$warningTone = !empty($summary['warning']) ? 'warn' : 'good';
?>
<span id="debugbar-toolbar-drag-hint" class="dk-sr-only">
    Drag this toolbar to pin it to the top, bottom, or any corner. The command palette offers the same actions.
</span>
<div
    data-dk-toolbar-shell
    data-dk-placement="bottom"
    data-dk-preferred="bottom"
    data-dk-dragging="false"
    data-dk-drag-target="bottom"
    data-dk-rebasing="false"
    data-dk-snapping="false"
    data-dk-form="center"
    role="toolbar"
    aria-label="Debug toolbar"
    aria-describedby="debugbar-toolbar-drag-hint"
    class="dk-toolbar dk-toolbar-draggable"
    hidden
>
    <div
        data-dk-center-toolbar
        class="dk-toolbar-center"
    >
        <?= $this->element('Crustum/DebugBar.request_switcher', [
            'scope' => 'toolbar',
            'switcherRequests' => $switcherRequests,
            'requestId' => $requestId,
            'method' => $method,
            'url' => $url,
            'statusCode' => $statusCode,
            'statusTone' => $statusTone,
            'responseSize' => $responseSize,
        ]) ?>

        <div
            data-dk-mobile-toolbar-control="metrics"
            class="dk-mobile-only dk-mobile-metrics"
        >
            <button
                type="button"
                data-dk-open-inspector="request"
                class="dk-mobile-metric"
            >
                <?= $statusCode ?>
            </button>
        </div>

        <div
            data-dk-toolbar-facts
            class="dk-toolbar-facts dk-desktop-only"
        >
            <button
                type="button"
                data-dk-toolbar="duration"
                data-dk-open-inspector="request"
                class="dk-fact"
            >
                <span data-dk-warning-dot data-dk-status-tone="<?= h($warningTone) ?>" class="dk-status-dot"></span>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'clock', 'size' => '3.5']) ?>
                <span class="dk-fact-body">
                    <span class="dk-fact-label">Duration</span>
                    <span class="dk-fact-value dk-tabular"><?= h($durationLabel) ?></span>
                </span>
            </button>
            <div
                data-dk-toolbar="memory"
                class="dk-fact"
            >
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'memory', 'size' => '3.5']) ?>
                <span class="dk-fact-body">
                    <span class="dk-fact-label">Peak</span>
                    <span class="dk-fact-value dk-tabular"><?= h($peakLabel) ?></span>
                </span>
            </div>
            <button
                type="button"
                data-dk-toolbar="queries"
                data-dk-open-inspector="queries"
                class="dk-fact"
            >
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'database', 'size' => '3.5']) ?>
                <span class="dk-fact-body">
                    <span class="dk-fact-label">Queries</span>
                    <span class="dk-fact-value dk-tabular"><?= $queryCount ?></span>
                </span>
            </button>
        </div>

        <div
            data-dk-mobile-toolbar-control="actions"
            class="dk-mobile-only dk-popover-anchor"
        >
            <button
                type="button"
                data-dk-mobile-trigger="actions"
                aria-expanded="false"
                aria-controls="debugbar-mobile-actions"
                aria-label="Show debug bar actions"
                class="dk-icon-button"
            >
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'ellipsis']) ?>
            </button>
            <div
                id="debugbar-mobile-actions"
                data-dk-mobile-popover="actions"
                role="menu"
                aria-label="Debug bar actions"
                class="dk-popover"
                hidden
            >
                <button type="button" data-dk-open-palette role="menuitem" class="dk-menu-item">Command palette</button>
                <button type="button" data-dk-open-inspector="request" role="menuitem" class="dk-menu-item">Open inspector</button>
            </div>
        </div>

        <div data-dk-toolbar-actions class="dk-toolbar-actions dk-desktop-only">
            <div data-dk-toolbar-utility-actions role="group" aria-label="Tools" class="dk-button-group">
                <button
                    type="button"
                    data-dk-toolbar="palette"
                    data-dk-open-palette
                    aria-label="Open command palette"
                    title="Command palette (Control + Alt + P)"
                    class="dk-icon-button"
                >
                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search']) ?>
                </button>
                <button
                    type="button"
                    data-dk-copy
                    data-dk-copy-value="<?= h($requestId) ?>"
                    aria-label="Copy request id"
                    title="Copy request id for MCP analysis"
                    class="dk-icon-button"
                >
                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'copy', 'size' => '4']) ?>
                    <span data-dk-copy-label class="dk:sr-only">Copy request id</span>
                </button>
                <div data-dk-theme-menu="toolbar" class="dk-popover-anchor">
                    <button
                        type="button"
                        data-dk-theme-trigger="toolbar"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-label="Theme"
                        class="dk-icon-button"
                    >
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'sun']) ?>
                    </button>
                    <div
                        data-dk-theme-options="toolbar"
                        role="menu"
                        aria-label="Theme"
                        class="dk-popover"
                        hidden
                    >
                        <button type="button" role="menuitemradio" data-dk-theme-option="system" class="dk-menu-item">System</button>
                        <button type="button" role="menuitemradio" data-dk-theme-option="light" class="dk-menu-item">Light</button>
                        <button type="button" role="menuitemradio" data-dk-theme-option="dark" class="dk-menu-item">Dark</button>
                    </div>
                </div>
            </div>
            <div data-dk-window-controls="compact" role="group" aria-label="Window" class="dk-button-group">
                <button type="button" data-dk-window="expand" aria-label="Open inspector" class="dk-icon-button">
                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'plus']) ?>
                </button>
                <button type="button" data-dk-window="close" aria-label="Hide toolbar" class="dk-icon-button">
                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'close']) ?>
                </button>
            </div>
        </div>
    </div>

    <div
        data-dk-corner-toolbar
        data-dk-form="corner"
        class="dk-corner-toolbar"
        hidden
    >
        <?= $this->element('Crustum/DebugBar.request_switcher', [
            'scope' => 'corner',
            'switcherRequests' => $switcherRequests,
            'requestId' => $requestId,
            'method' => $method,
            'url' => $url,
            'statusCode' => $statusCode,
            'statusTone' => $statusTone,
            'responseSize' => $responseSize,
        ]) ?>
        <button
            type="button"
            data-dk-window="expand"
            aria-label="Open inspector"
            class="dk-icon-button"
        >
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'plus']) ?>
        </button>
    </div>
</div>
