<?php
/**
 * Cache panel element.
 *
 * Faithful port of the upstream `cache` inspector (`cache-workspace` +
 * `cache-controls/header/list-item/detail` + `cache-overview-*`): summary
 * line with visible count and total time, hit-rate block (amber on high
 * miss), attention banner, search (from 5 operations) + All/Reads/Writes/
 * Deletes/Failed select with nonzero counts, operation/key/store/result/
 * duration grid rows with pressed states, and detail (Result/Runtime/Store
 * facts plus the raw spy line as evidence with copy). Interactive states are
 * server-rendered (`aria-pressed`, `hidden`); the boot script in
 * `Requests/view` owns search/filter/selection over the `data-dk-*` hooks.
 * Styling rides the shared `dk:` Tailwind utilities.
 *
 * CakePHP notes (no fake data): the spy lines carry no per-row outcome, so
 * ok rows leave the result cell empty while unparseable lines report Failed;
 * values, TTLs and callsites are not captured, so the detail shows the raw
 * line instead of value evidence and omits lifetime/source sections.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $metrics Per-engine counters (`get hit`, `get miss`, `set`, `delete`, …)
 * @var array $logs Spy log lines
 */
use Crustum\DebugBar\Cache\Engine\DebugEngine;
use Crustum\DebugBar\Service\QueryAnalyzer;
use function Cake\Core\h;

$metrics = (array)($metrics ?? []);
$logs = array_values((array)($logs ?? []));
$csrfToken = (string)($this->getRequest()->getAttribute('csrfToken') ?? '');

$classify = static function (string $operation): string {
    $operation = strtolower($operation);
    if (str_contains($operation, 'delete') || str_contains($operation, 'clear')) {
        return 'deletes';
    }
    if (
        str_contains($operation, 'set') || str_contains($operation, 'write')
        || str_contains($operation, 'add') || str_contains($operation, 'increment')
        || str_contains($operation, 'decrement')
    ) {
        return 'writes';
    }

    return 'reads';
};

$parsed = [];
foreach ($logs as $index => $line) {
    $row = DebugEngine::parseLine((string)$line);
    $failed = $row['operation'] === '';
    $operation = $failed ? 'read' : $row['operation'];
    $durationMs = is_numeric($row['duration']) ? (float)$row['duration'] : 0.0;
    $category = $failed ? 'failed' : $classify($operation);
    $parsed[] = [
        'key' => 'cache-' . $index,
        'execution' => $index + 1,
        'engine' => $row['engine'],
        'operation' => $operation,
        'operation_label' => $failed ? 'Unknown' : ucfirst($operation),
        'name' => $row['key'] !== '' ? $row['key'] : ($failed ? 'Unreadable log line' : ucfirst($operation)),
        'key_label' => $row['key'],
        'duration_ms' => $durationMs,
        'duration_label' => $row['duration'] !== '' ? QueryAnalyzer::formatDuration($durationMs) : '—',
        'line' => (string)$line,
        'category' => $category,
        'failed' => $failed,
        'search' => strtolower($operation . ' ' . $row['key'] . ' ' . $row['engine']),
    ];
}

$hits = 0;
$misses = 0;
$writes = 0;
$deletes = 0;
foreach ($metrics as $counters) {
    $counters = (array)$counters;
    $hits += (int)($counters['get hit'] ?? 0);
    $misses += (int)($counters['get miss'] ?? 0);
    $writes += (int)($counters['set'] ?? 0);
    $deletes += (int)($counters['delete'] ?? 0);
}
$reads = $hits + $misses;
$hitRate = $reads > 0 ? round($hits / $reads * 100, 1) : null;
$highMiss = $reads >= 5 && $hitRate !== null && $hitRate <= 20;
$totalMs = 0.0;
foreach ($parsed as $row) {
    $totalMs += $row['duration_ms'];
}

$count = count($parsed);
$readCount = 0;
$writeCount = 0;
$deleteCount = 0;
$failedCount = 0;
foreach ($parsed as $row) {
    if ($row['failed']) {
        $failedCount++;
    }
    if ($row['category'] === 'reads') {
        $readCount++;
    } elseif ($row['category'] === 'writes') {
        $writeCount++;
    } elseif ($row['category'] === 'deletes') {
        $deleteCount++;
    }
}
$filters = ['all' => ['All', $count]];
if ($readCount > 0) {
    $filters['reads'] = ['Reads', $readCount];
}
if ($writeCount > 0) {
    $filters['writes'] = ['Writes', $writeCount];
}
if ($deleteCount > 0) {
    $filters['deletes'] = ['Deletes', $deleteCount];
}
if ($failedCount > 0) {
    $filters['failed'] = ['Failed', $failedCount];
}
$attentionParts = [];
if ($failedCount > 0) {
    $attentionParts[] = number_format($failedCount) . ' failed ' . ($failedCount === 1 ? 'operation' : 'operations');
}
if ($highMiss) {
    $attentionParts[] = 'high miss rate (' . h((string)$hitRate) . '% over ' . $reads . ' reads)';
}
$copyActionClass = 'dk:inline-flex dk:h-auto dk:min-h-9 dk:shrink-0 dk:items-center dk:gap-2 dk:rounded-lg dk:px-2 dk:text-xs dk:font-medium dk:text-zinc-500 dk:transition-colors dk:hover:bg-zinc-100 dk:hover:text-zinc-900 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800 dk:dark:hover:text-zinc-100';
$codeBlockClass = 'dk-code dk-scrollbar dk:m-0 dk:min-w-0 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100 dk:p-4 dk:font-mono dk:text-sm dk:leading-6 dk:text-zinc-700 dk:dark:bg-zinc-950 dk:dark:text-zinc-200 dk:whitespace-pre-wrap dk:break-words';
?>
<div data-dk-cache-panel data-dk-active-filter="all" class="dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if ($metrics !== []) : ?>
    <details class="dk:group/disclosure dk:min-w-0 dk:border-b dk:border-zinc-200/90 dk:dark:border-zinc-800">
        <summary class="dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden">
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
            <span class="dk:min-w-0 dk:flex-1">Cache stores</span>
            <span class="dk:shrink-0 dk:text-xs dk:font-normal dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= number_format(count($metrics)) ?> <?= count($metrics) === 1 ? 'store' : 'stores' ?></span>
        </summary>
        <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
            <ul class="dk:m-0 dk:min-w-0 dk:list-none dk:divide-y dk:divide-zinc-200/90 dk:p-0 dk:dark:divide-zinc-800">
                <li class="dk:flex dk:min-w-0 dk:items-center dk:gap-3 dk:py-2">
                    <span class="dk:min-w-0 dk:flex-1 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100">All</span>
                    <span data-dk-cache-clear-result hidden class="dk:min-w-0 dk:shrink-0 dk:truncate dk:text-right dk:text-xs dk:italic dk:text-zinc-500 dk:dark:text-zinc-400"></span>
                    <button
                        type="button"
                        data-dk-cache-clear-all
                        data-dk-cache-clear-all-url="<?= h((string)$this->Url->build([
                            'plugin' => 'Crustum/DebugBar',
                            'controller' => 'Toolbar',
                            'action' => 'clearAllCaches',
                        ])) ?>"
                        <?php if ($csrfToken !== '') : ?>
                        data-dk-csrf-token="<?= h($csrfToken) ?>"
                        <?php endif; ?>
                        class="<?= h($copyActionClass) ?>"
                    >
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'database', 'size' => '3.5']) ?>
                        <span data-dk-cache-clear-label>Clear data</span>
                    </button>
                </li>
                <?php foreach ($metrics as $engineName => $counters) : ?>
                <li class="dk:flex dk:min-w-0 dk:items-center dk:gap-3 dk:py-2">
                    <span class="dk:min-w-0 dk:flex-1 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h((string)$engineName) ?></span>
                    <span data-dk-cache-clear-result hidden class="dk:min-w-0 dk:shrink-0 dk:truncate dk:text-right dk:text-xs dk:italic dk:text-zinc-500 dk:dark:text-zinc-400"></span>
                    <button
                        type="button"
                        data-dk-cache-clear
                        data-dk-cache-clear-name="<?= h((string)$engineName) ?>"
                        data-dk-cache-clear-url="<?= h((string)$this->Url->build([
                            'plugin' => 'Crustum/DebugBar',
                            'controller' => 'Toolbar',
                            'action' => 'clearCache',
                        ])) ?>"
                        <?php if ($csrfToken !== '') : ?>
                        data-dk-csrf-token="<?= h($csrfToken) ?>"
                        <?php endif; ?>
                        class="<?= h($copyActionClass) ?>"
                    >
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'database', 'size' => '3.5']) ?>
                        <span data-dk-cache-clear-label>Clear data</span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </details>
    <?php endif; ?>
    <?php if ($parsed === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'There were no cache operations in this request.',
        'success' => true,
    ]) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <div data-dk-cache-summary class="dk:flex dk:items-start dk:justify-between dk:gap-3 dk:sm:gap-4">
        <div class="dk:min-w-0">
            <p class="dk:text-xs dk:font-bold dk:text-zinc-700 dk:dark:text-zinc-200">
                <span data-dk-cache-summary-count><?= number_format($count) ?> <?= $count === 1 ? 'operation' : 'operations' ?></span>
                <span
                    data-dk-cache-visible-count
                    data-dk-visible-count
                    hidden
                    class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
                ></span>
            </p>
            <p class="dk:mt-0.5 dk:text-xs dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400">
                <?= h(QueryAnalyzer::formatDuration($totalMs)) ?> total
            </p>
        </div>
        <?php if ($reads > 0) : ?>
        <div class="dk:shrink-0 dk:text-right">
            <p class="dk:text-xs dk:font-bold dk:tabular-nums<?= $highMiss ? ' dk:text-amber-700 dk:dark:text-amber-300' : ' dk:text-zinc-700 dk:dark:text-zinc-200' ?>">
                <?= h((string)$hitRate) ?>% hit rate
            </p>
            <p class="dk:mt-0.5 dk:text-xs dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400">
                <?= number_format($hits) ?> <?= $hits === 1 ? 'hit' : 'hits' ?>, <?= number_format($misses) ?> <?= $misses === 1 ? 'miss' : 'misses' ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($attentionParts !== []) : ?>
    <p
        data-dk-cache-attention
        role="status"
        class="dk:flex dk:items-start dk:gap-2 dk:text-xs dk:font-medium dk:leading-4<?= $failedCount > 0 ? ' dk:text-red-700 dk:dark:text-red-300' : ' dk:text-amber-700 dk:dark:text-amber-300' ?>"
    >
        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'warning', 'size' => '3.5', 'class' => 'dk:mt-px']) ?>
        <span>
            <strong class="dk:font-bold">Cache needs attention.</strong>
            <?= ucfirst(implode(', ', $attentionParts)) ?>.
        </span>
    </p>
    <?php endif; ?>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3<?= $count >= 5 ? ' dk:grid-cols-[minmax(0,1fr)_8.75rem]' : ' dk:grid-cols-1' ?>"
    >
        <?php if ($count >= 5) : ?>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search cache operations</span>
                <input
                    data-dk-search="cache"
                    type="search"
                    placeholder="Search keys or stores"
                    aria-label="Search cache operations"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <?php endif; ?>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter cache operations</span>
                <select
                    data-dk-select="cache-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <?php foreach ($filters as $value => [$label, $filterCount]) : ?>
                    <option value="<?= h($value) ?>"><?= h($label) ?> (<?= number_format($filterCount) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-cache-list>
        <?php foreach ($parsed as $index => $row) :
            $pressed = $index === 0;
            ?>
        <button
            type="button"
            data-dk-cache-entry
            data-dk-cache-key="<?= h($row['key']) ?>"
            data-dk-cache-execution="<?= $row['execution'] ?>"
            data-dk-cache-category="<?= h($row['category'] === 'failed' ? 'failed' : $row['category']) ?>"
            data-dk-cache-failed="<?= $row['failed'] ? 'true' : 'false' ?>"
            data-dk-cache-search-text="<?= h($row['search']) ?>"
            data-dk-filter-group="<?= h($row['category']) ?>"
            data-dk-filter-attention="<?= $row['failed'] ? 'true' : 'false' ?>"
            data-dk-search-text="<?= h($row['search']) ?>"
            title="<?= h($row['key_label'] !== '' ? $row['key_label'] : $row['name']) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[4rem_minmax(0,1fr)_4.75rem] dk:items-center dk:gap-x-2 dk:gap-y-0.5 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-start-1 dk:row-span-2 dk:row-start-1 dk:flex dk:min-w-0 dk:self-center">
                <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', [
                    'operation' => $row['operation_label'],
                ]) ?>
            </span>
            <span
                data-dk-cache-list-key
                title="<?= h($row['key_label'] !== '' ? $row['key_label'] : $row['name']) ?>"
                class="dk:col-start-2 dk:row-start-1 dk:min-w-0 dk:truncate dk:text-xs dk:font-semibold dk:text-zinc-800 dk:dark:text-zinc-200"
            ><?= h($row['name']) ?></span>
            <?php if ($row['failed']) : ?>
            <span
                data-dk-cache-list-result
                class="dk:col-start-3 dk:row-start-1 dk:w-full dk:text-right dk:text-xs dk:font-bold dk:text-red-600 dk:dark:text-red-300"
            >Failed</span>
            <?php else : ?>
            <span data-dk-cache-list-result class="dk:col-start-3 dk:row-start-1"></span>
            <?php endif; ?>
            <span class="dk:col-start-2 dk:row-start-2 dk:min-w-0 dk:truncate dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($row['engine'] !== '' ? $row['engine'] : '—') ?></span>
            <span
                data-dk-cache-list-duration
                class="dk:col-start-3 dk:row-start-2 dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"
            ><?= h($row['duration_label']) ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No cache operations match these controls.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty class="dk:min-w-0 dk:p-3 dk:sm:p-4" hidden>Choose an operation to inspect its evidence.</div>
        <?php foreach ($parsed as $index => $row) : ?>
        <section
            data-dk-cache-detail="<?= h($row['key']) ?>"
            aria-label="Selected cache operation details"
            <?= $index === 0 ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header data-dk-cache-header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <h3 class="dk:flex dk:min-w-0 dk:flex-nowrap dk:items-center dk:gap-2 dk:overflow-hidden">
                        <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', [
                            'operation' => $row['operation_label'],
                        ]) ?>
                        <span
                            data-dk-cache-detail-key
                            title="<?= h($row['key_label'] !== '' ? $row['key_label'] : $row['name']) ?>"
                            class="dk:min-w-0 dk:truncate dk:text-xs dk:font-bold dk:leading-5 dk:text-zinc-700 dk:dark:text-zinc-200"
                        ><?= h($row['name']) ?></span>
                    </h3>
                </header>
                <div data-dk-cache-detail-content class="dk:p-3 dk:sm:p-4">
                    <dl data-dk-cache-metadata class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2 dk:@2xl:grid-cols-4">
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Result</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold<?= $row['failed'] ? ' dk:text-red-700 dk:dark:text-red-300' : '' ?>"><?= $row['failed'] ? 'Failed' : '—' ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Runtime</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold dk:tabular-nums"><?= h($row['duration_label']) ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Store</dt>
                            <dd title="<?= h($row['engine']) ?>" class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= h($row['engine'] !== '' ? $row['engine'] : '—') ?></dd>
                        </div>
                    </dl>
                    <section class="dk:min-w-0 dk:mt-4">
                        <div class="dk:mb-2 dk:flex dk:items-center dk:justify-between dk:gap-3">
                            <h4 class="dk:text-sm dk:font-semibold">Raw log line</h4>
                            <div class="dk:shrink-0">
                                <button
                                    type="button"
                                    data-dk-copy
                                    data-dk-copy-value="<?= h($row['line']) ?>"
                                    title="Copy log line"
                                    aria-label="Copy log line"
                                    class="<?= h($copyActionClass) ?>"
                                >
                                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'copy', 'size' => '3.5']) ?>
                                    <span data-dk-copy-label>Copy log line</span>
                                </button>
                            </div>
                        </div>
                        <pre class="<?= h($codeBlockClass) ?>"><code><?= h($row['line']) ?></code></pre>
                    </section>
                </div>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
    <?php
    $detailBody = (string)ob_get_clean();
    ?>
    <?= $this->element('Crustum/DebugBar.ui/inspector_workspace', [
        'mode' => 'split',
        'frame' => 'top',
        'list' => $this->element('Crustum/DebugBar.ui/inspector_list_panel', [
            'controls' => $controls,
            'body' => $listBody,
        ]),
        'detail' => $detailBody,
    ]) ?>
    <?php endif; ?>
</div>
