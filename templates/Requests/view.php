<?php
/**
 * Toolbar shell view.
 *
 * Renders the quiet toolbar and the inspector sheet into the toolbar
 * iframe. The shell boots from the embedded summary JSON with the packed
 * `dist/shell.js` bundle. Toolbar facts come from the stored panels
 * (Timer requestTime/peak, SqlLog query counts) through `RequestFacts`,
 * shared with the on-the-fly request switch so swapped requests show identical
 * header facts. The recent  payload seeds the shell switcher; ignored URL masks
 * skip what the collector ignores server-side so polling endpoints never spam unread
 * dots. JSON encodes raw on purpose: `<script>` is a raw-text element,
 * so `h()` would poison `JSON.parse` while `JSON_HEX_*` already
 * neutralizes `</script>` breakouts.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var \Crustum\DebugBar\Model\Entity\Request $toolbar
 * @var array $recent Recent request rows for the switchers
 */
use Cake\Core\Configure;
use Crustum\DebugBar\Service\MiniLauncher;
use Crustum\DebugBar\Service\RequestFacts;
use function Cake\Core\h;

$inspectorKey = [RequestFacts::class, 'inspectorKey'];

$inspectors = [];
foreach ($toolbar->panels as $panel) {
    // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
    $stored = @unserialize((string)$panel->content);
    $inspectors[] = [
        'key' => $inspectorKey((string)$panel->title),
        'label' => (string)$panel->title,
        'description' => RequestFacts::inspectorDescription((string)$panel->panel),
        'panel_id' => $panel->id,
        'active' => true,
        'attention' => false,
        'count' => is_array($stored)
        ? RequestFacts::panelCount((string)$panel->panel, $stored)
        : null,
    ];
}

$summary = [
    'id' => $toolbar->id,
    'url' => $toolbar->url,
    'method' => $toolbar->method,
    'status_code' => $toolbar->status_code,
    'content_type' => $toolbar->content_type,
    'requested_at' => (string)$toolbar->requested_at,
    'inspectors' => $inspectors,
];

$facts = RequestFacts::summarize($toolbar);
$summary['environment'] = $facts['environment'];
$summary['duration_ms'] = $facts['duration_ms'];
$summary['duration_label'] = $facts['duration_label'];
$summary['peak_memory_mb'] = $facts['peak_memory_mb'];
$summary['peak_memory_label'] = $facts['peak_memory_label'];
$summary['query_count'] = $facts['query_count'];
$summary['response_size_bytes'] = $facts['response_size_bytes'] ?? null;
$summary['response_size'] = $facts['response_size'] ?? null;
$summary['warning'] = $facts['warning'];
$summary['max_requests'] = (int)$facts['max_requests'];
$miniAction = MiniLauncher::sanitizeAction(Configure::read('DebugBar.miniAction'));
$summary['mini_action'] = $miniAction;
$summary['ignored_url_masks'] = array_values(array_filter(array_map(
    'strval',
    (array)Configure::read('DebugBar.ignored_url_masks', []),
)));
$summaryJson = (string)json_encode(
    $summary,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
);
$recentRows = [];
foreach ((array)($recent ?? []) as $row) {
    if (!is_array($row) || ($row['id'] ?? '') === '') {
        continue;
    }
    $recentRows[] = [
        'id' => (string)$row['id'],
        'url' => (string)($row['url'] ?? '/'),
        'method' => (string)($row['method'] ?? 'GET'),
        'status_code' => (int)($row['status_code'] ?? 0),
        'requested_at' => (string)($row['requested_at'] ?? ''),
        'response_size_bytes' => isset($row['response_size_bytes']) && is_int($row['response_size_bytes'])
            ? $row['response_size_bytes']
            : null,
        'response_size' => isset($row['response_size']) && is_string($row['response_size'])
            ? $row['response_size']
            : null,
    ];
}
$recentJson = (string)json_encode(
    array_values($recentRows),
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP,
);
?>
<div
    id="debugbar"
    data-dk-theme="light"
    data-dk-frame="full"
    data-dk-request-id="<?= h((string)($toolbar->id ?? '')) ?>"
    data-dk-asset-version="<?= h((string)($assetVersion ?? 0)) ?>"
    data-dk-mini-action="<?= h($miniAction) ?>"
    class="dk-shell-root"
>
    <button
        type="button"
        data-dk-mini-toggle
        aria-label="Open debug toolbar"
        title="Open debug toolbar (Control + Alt + P for the command palette)"
        class="dk-mini-toggle"
    ><span aria-hidden="true"><?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'cake', 'size' => '5']) ?></span></button>
    <?= $this->element('Crustum/DebugBar.anchor_preview') ?>
    <?= $this->element('Crustum/DebugBar.toolbar', [
        'summary' => $summary,
        'recent' => $recent ?? [],
    ]) ?>
    <?= $this->element('Crustum/DebugBar.inspector', [
        'toolbar' => $toolbar,
        'summary' => $summary,
        'recent' => $recent ?? [],
        'inspectors' => $inspectors,
    ]) ?>
    <script type="application/json" data-dk-summary><?= $summaryJson ?></script>
    <script type="application/json" data-dk-recent><?= $recentJson ?></script>
</div>
<?php
$shellUrl = (string)$this->Url->script('Crustum/DebugBar./dist/shell', ['timestamp' => 'force']);
?>
<script src="<?= h($shellUrl) ?>" type="module"></script>
