<?php
/**
 * Inspector sheet shell for the debug bar.
 *
 * Renders the dialog overlay with header facts, the 210px inspector
 * navigation, the content stage (skeleton, error and empty states) and the
 * command palette dialog. Panel bodies are the untouched panel elements
 * wrapped in `data-dk-inspector-panel` sections so the vanilla shell parts
 * can toggle them with `hidden`. All styling uses `dk-*` component classes
 * scoped under `#debugbar`.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var \Crustum\DebugBar\Model\Entity\Request $toolbar Request row with panels
 * @var array $summary Request summary with `inspectors` entries
 * @var array $recent Recent request rows for the switchers
 * @var array $inspectors Inspector entries with `key`, `label`, `description`
 */
use function Cake\Core\h;

$requestId = (string)($summary['id'] ?? '');
$method = (string)($summary['method'] ?? 'GET');
$url = (string)($summary['url'] ?? '/');
$statusCode = (int)($summary['status_code'] ?? 0);
$contentType = (string)($summary['content_type'] ?? '');
$durationLabel = (string)($summary['duration_label'] ?? '—');
$peakLabel = (string)($summary['peak_memory_label'] ?? '—');
$queryCount = (int)($summary['query_count'] ?? 0);
$warningTone = !empty($summary['warning']) ? 'warn' : 'good';
$responseSize = isset($summary['response_size']) && is_string($summary['response_size'])
    ? $summary['response_size']
    : null;
$entries = array_values((array)$inspectors);
$switcherRequests = [];
foreach ((array)$recent as $row) {
    if (!is_array($row) || ($row['id'] ?? '') === '') {
        continue;
    }
    $switcherRequests[] = $row;
}
$firstKey = $entries[0]['key'] ?? 'request';
$firstLabel = $entries[0]['label'] ?? 'Request';
?>
<div data-dk-inspector role="presentation" hidden>
    <div
        data-dk-backdrop
        class="dk-backdrop"
        aria-hidden="true"
    ></div>

    <aside
        id="debugbar-inspector"
        role="dialog"
        aria-modal="true"
        aria-label="Request inspector"
        tabindex="-1"
        data-dk-placement="bottom"
        class="dk-inspector-panel"
    >
        <header class="dk-inspector-header">
            <div data-dk-header-mobile-toolbar class="dk-header-mobile">
                <?= $this->element('Crustum/DebugBar.request_switcher', [
                    'scope' => 'header-mobile',
                    'switcherRequests' => $switcherRequests,
                    'requestId' => $requestId,
                    'method' => $method,
                    'url' => $url,
                    'statusCode' => $statusCode,
                    'statusTone' => 'good',
                    'responseSize' => $responseSize,
                ]) ?>
                <div
                    data-dk-header-mobile-control="actions"
                    class="dk-popover-anchor"
                >
                    <button
                        type="button"
                        data-dk-mobile-trigger="header-actions"
                        aria-expanded="false"
                        aria-controls="debugbar-header-mobile-actions"
                        aria-label="Show inspector actions"
                        class="dk-icon-button"
                    >
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'ellipsis']) ?>
                    </button>
                    <div
                        id="debugbar-header-mobile-actions"
                        data-dk-mobile-popover="header-actions"
                        role="menu"
                        aria-label="Inspector actions"
                        class="dk-popover"
                        hidden
                    >
                        <button type="button" data-dk-open-palette role="menuitem" class="dk-menu-item">Command palette</button>
                        <button type="button" data-dk-window="shrink" role="menuitem" class="dk-menu-item">Close inspector</button>
                    </div>
                </div>
            </div>

            <div data-dk-header-toolbar class="dk-header-desktop">
                <?= $this->element('Crustum/DebugBar.request_switcher', [
                    'scope' => 'header',
                    'switcherRequests' => $switcherRequests,
                    'requestId' => $requestId,
                    'method' => $method,
                    'url' => $url,
                    'statusCode' => $statusCode,
                    'statusTone' => 'good',
                    'responseSize' => $responseSize,
                ]) ?>

                <div
                    data-dk-header-facts
                    class="dk-scrollbar dk-header-facts"
                >
                    <button
                        type="button"
                        data-dk-header-fact="duration"
                        data-dk-open-inspector="request"
                        class="dk-fact"
                    >
                        <span data-dk-warning-dot data-dk-status-tone="<?= h($warningTone) ?>" class="dk-status-dot"></span>
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'clock', 'size' => '3.5']) ?>
                        <span class="dk-fact-body">
                            <span class="dk-fact-label">Duration</span>
                            <span data-dk-header-status class="dk-fact-value dk-tabular"><?= h($durationLabel) ?></span>
                        </span>
                    </button>
                    <div
                        data-dk-header-fact="memory"
                        class="dk-fact"
                    >
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'memory', 'size' => '3.5']) ?>
                        <span class="dk-fact-body">
                            <span class="dk-fact-label">Peak</span>
                            <span data-dk-header-memory class="dk-fact-value dk-tabular"><?= h($peakLabel) ?></span>
                        </span>
                    </div>
                    <button
                        type="button"
                        data-dk-header-fact="queries"
                        data-dk-open-inspector="queries"
                        class="dk-fact"
                    >
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'database', 'size' => '3.5']) ?>
                        <span class="dk-fact-body">
                            <span class="dk-fact-label">Queries</span>
                            <span data-dk-header-query-count class="dk-fact-value dk-tabular"><?= $queryCount ?></span>
                        </span>
                    </button>
                </div>

                <div data-dk-inspector-actions class="dk-toolbar-actions">
                    <div data-dk-inspector-utility-actions role="group" aria-label="Tools" class="dk-button-group">
                        <button
                            type="button"
                            data-dk-inspector-action="palette"
                            data-dk-open-palette
                            aria-label="Open command palette"
                            class="dk-icon-button"
                        >
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search']) ?>
                        </button>
                        <button
                            type="button"
                            data-dk-copy
                            data-dk-copy-request-id
                            data-dk-copy-value="<?= h($requestId) ?>"
                            aria-label="Copy request id"
                            title="Copy request id for MCP analysis"
                            class="dk-icon-button"
                        >
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'copy', 'size' => '4']) ?>
                            <span data-dk-copy-label class="dk:sr-only">Copy request id</span>
                        </button>
                        <div data-dk-theme-menu="header" class="dk-popover-anchor">
                            <button
                                type="button"
                                data-dk-theme-trigger="header"
                                aria-haspopup="true"
                                aria-expanded="false"
                                aria-label="Theme"
                                class="dk-icon-button"
                            >
                                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'sun']) ?>
                            </button>
                            <div
                                data-dk-theme-options="header"
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
                    <div data-dk-window-controls="expanded" role="group" aria-label="Window" class="dk-button-group">
                        <button type="button" data-dk-window="shrink" aria-label="Close inspector" class="dk-icon-button">
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'shrink']) ?>
                        </button>
                        <button type="button" data-dk-window="close" aria-label="Hide toolbar" class="dk-icon-button">
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'close']) ?>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div
            data-dk-background-activity-error
            role="status"
            aria-live="polite"
            class="dk-activity-error"
            hidden
        >
            <span data-dk-background-activity-message class="dk-activity-message"></span>
            <button
                type="button"
                data-dk-activity-retry
                class="dk-button"
            >Retry now</button>
        </div>

        <div class="dk-inspector-body">
            <nav
                id="debugbar-inspector-navigation"
                data-dk-inspector-navigation
                aria-label="Debug inspectors"
                class="dk-inspector-nav"
            >
                <?= $this->element('Crustum/DebugBar.inspector_navigation', [
                    'entries' => $entries,
                    'firstKey' => $firstKey,
                ]) ?>
            </nav>

            <div
                data-dk-inspector-content
                class="dk-scrollbar dk-inspector-content"
            >
                <div data-dk-inspector-heading-block class="dk-inspector-heading-block">
                    <h2
                        data-dk-inspector-heading
                        tabindex="-1"
                        class="dk-inspector-heading"
                    ><?= h((string)$firstLabel) ?></h2>
                    <p
                        id="debugbar-inspector-description"
                        data-dk-inspector-description
                        class="dk-inspector-description"
                        <?= $firstKey === 'request' ? 'hidden' : '' ?>
                    ></p>
                </div>

                <div
                    data-dk-inspector-stage
                    aria-busy="false"
                    class="dk-inspector-stage"
                >
                    <div
                        data-dk-inspector-loading
                        role="status"
                        aria-live="polite"
                        aria-atomic="true"
                        class="dk-inspector-loading"
                        hidden
                    >
                        <div class="dk-loading-card">
                            <span class="dk-loading-pulse dk-loading-icon"><?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'activity', 'size' => '4']) ?></span>
                            <span class="dk-loading-text"
                                >Loading <span data-dk-inspector-loading-label><?= h(mb_strtolower((string)$firstLabel)) ?></span>...</span>
                        </div>
                    </div>

                    <div
                        data-dk-inspector-body
                        class="dk-inspector-details"
                    >
                        <div
                            data-dk-inspector-error
                            role="alert"
                            class="dk-inspector-error"
                            hidden
                        >
                            <p class="dk-error-title">
                                Collector details could not be loaded.
                            </p>
                            <p class="dk-error-text">
                                The request summary is still available. Retry or reload the page to capture a new
                                request.
                            </p>
                            <div class="dk-error-actions">
                                <button
                                    type="button"
                                    data-dk-inspector-retry
                                    class="dk-button dk-button-danger"
                                >
                                    Retry inspector</button>
                                <button
                                    type="button"
                                    data-dk-inspector-reload
                                    class="dk-button dk-button-outline"
                                >
                                    Reload page
                                </button>
                            </div>
                        </div>

                        <?php if ($entries === []) : ?>
                        <div
                            data-dk-inspector-empty
                            class="dk-inspector-empty"
                        >
                            No panels were captured for this request.
                        </div>
                        <?php endif; ?>
                        <?php foreach ($toolbar->panels as $panel) :
                            $panelKey = '';
                            foreach ($entries as $entry) {
                                if (($entry['panel_id'] ?? null) === $panel->id) {
                                    $panelKey = (string)$entry['key'];
                                    break;
                                }
                            }
                            if ($panelKey === '') {
                                continue;
                            }
                            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
                            $stored = @unserialize($panel->content);
                            $panelData = is_array($stored) ? $stored : [];
                            $panelData += [
                                'panel' => $panel,
                                'sort' => false,
                                'requestMethod' => $method,
                                'requestUrl' => $url,
                                'requestStatus' => $statusCode,
                                'requestContentType' => $contentType,
                                'panelId' => $panel->id,
                            ];
                            ?>
                        <section
                            data-dk-inspector-panel="<?= h($panelKey) ?>"
                            aria-label="<?= h((string)$panel->title) ?>"
                            class="dk-panel-section"
                            <?= $panelKey === $firstKey ? '' : 'hidden' ?>
                        >
                            <?= $this->element($panel->element, $panelData) ?>
                        </section>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</div>
<div
    data-dk-palette
    class="dk-palette-backdrop"
    hidden
>
    <div
        id="debugbar-palette"
        role="dialog"
        aria-modal="true"
        aria-label="Command palette"
        class="dk-palette-dialog"
    >
        <div class="dk-palette-search-row">
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search']) ?>
            <input
                data-dk-palette-search
                type="search"
                placeholder="Jump to an inspector or change a setting..."
                aria-label="Search commands"
                autocomplete="off"
                class="dk-palette-search"
            />
            <kbd class="dk-kbd">ESC</kbd>
        </div>
        <div data-dk-palette-list role="listbox" aria-label="Commands" class="dk-scrollbar dk-palette-list"></div>
        <p
            data-dk-palette-empty
            class="dk-palette-empty"
            hidden
        >
            No matching commands.
        </p>
        <div class="dk-palette-footer">
            <span>Navigate</span><span>Select</span><span class="dk-palette-shortcut">Ctrl Alt P</span>
        </div>
    </div>
</div>
