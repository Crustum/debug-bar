<?php
/**
 * SQL log panel element.
 *
 * Faithful port of the upstream `query-inspector` + `query-detail`
 * components: count summary with visible-count and total time, search
 * plus All/Needs attention/Reads/Writes select, Type/Query/Time grid
 * with slow/repeated tints and a tri-state Time sort, and detail
 * Overview (repeated-run picker, Duration/Query time/Type/Connection
 * facts, Full query evidence with copy, bindings note, N+1 explanation,
 * application source and stack) beside the live-only EXPLAIN tab.
 * Records come presented from `SqlLogPanel::data()`; the
 * `webroot/js/inspectors/queries.js` inspector owns search/filter/sort/
 * selection/EXPLAIN over the `data-dk-*` hooks and highlight.js
 * colorizes every `code[data-dk-language]` block after paint. Styling
 * rides the shared `dk:` Tailwind utilities.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $records Presented query records
 * @var array $summary Summary counters
 */
use Crustum\DebugBar\Service\AppPath;
use Crustum\DebugBar\Service\QueryFormatter;
use function Cake\Core\h;

$records = array_values((array)($records ?? []));
$summary = (array)($summary ?? []);
$csrfToken = (string)($this->getRequest()->getAttribute('csrfToken') ?? '');
$count = 0;
$attentionCount = 0;
$readCount = 0;
$writeCount = 0;
foreach ($records as $record) {
    $record = (array)$record;
    $recordCount = (int)($record['count'] ?? 1);
    $count += $recordCount;
    if (!empty($record['attention'])) {
        $attentionCount += $recordCount;
    }
    if (($record['query_type'] ?? '') === 'read') {
        $readCount += $recordCount;
    } else {
        $writeCount += $recordCount;
    }
}
$segmentedTabClass = 'dk:relative dk:flex dk:min-w-0 dk:items-baseline dk:justify-center dk:gap-1.5 dk:whitespace-nowrap dk:rounded-md dk:border dk:border-transparent dk:bg-transparent dk:px-3 dk:py-1.5 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:transition-[background-color,color,box-shadow] dk:hover:bg-white/60 dk:hover:text-zinc-950 dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:aria-pressed:bg-white dk:aria-pressed:text-zinc-950 dk:aria-pressed:shadow-sm dk:aria-pressed:hover:bg-white dk:aria-pressed:hover:text-zinc-950 dk:aria-selected:bg-white dk:aria-selected:text-zinc-950 dk:aria-selected:shadow-sm dk:aria-selected:hover:bg-white dk:aria-selected:hover:text-zinc-950 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800/70 dk:dark:hover:text-white dk:dark:aria-pressed:bg-zinc-800 dk:dark:aria-pressed:text-white dk:dark:aria-selected:bg-zinc-800 dk:dark:aria-selected:text-white';
$copyActionClass = 'dk:inline-flex dk:h-auto dk:min-h-9 dk:shrink-0 dk:items-center dk:gap-2 dk:rounded-lg dk:px-2 dk:text-xs dk:font-medium dk:text-zinc-500 dk:transition-colors dk:hover:bg-zinc-100 dk:hover:text-zinc-900 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800 dk:dark:hover:text-zinc-100';
$codeBlockClass = 'dk-code dk-scrollbar dk:m-0 dk:min-w-0 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100 dk:p-4 dk:font-mono dk:text-sm dk:leading-6 dk:text-zinc-700 dk:dark:bg-zinc-950 dk:dark:text-zinc-200 dk:whitespace-pre-wrap dk:break-words';
?>
<div data-dk-queries
    <?php if ($csrfToken !== '') : ?>
    data-dk-csrf-token="<?= h($csrfToken) ?>"
    <?php endif; ?>
    class="dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if ($records === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No database queries were captured for this request.']) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-query-summary
        aria-live="polite"
        aria-atomic="true"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-700 dk:dark:text-zinc-200"
    >
        <span data-dk-query-summary-count><?= number_format($count) ?> <?= $count === 1 ? 'query' : 'queries' ?></span>
        <span data-dk-visible-count hidden class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"></span>
        <span
            data-dk-query-total-time
            class="dk:mt-0.5 dk:block dk:text-xs dk:font-medium dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"
        >
            <?= h((string)($summary['duration_label'] ?? '')) ?> total
        </span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search queries</span>
                <input
                    data-dk-search="queries"
                    type="search"
                    placeholder="Search SQL, bindings, or source"
                    aria-label="Search queries"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter queries</span>
                <select
                    data-dk-select="queries-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <option value="all">All (<?= number_format($count) ?>)</option>
                    <option value="attention">Needs attention (<?= number_format($attentionCount) ?>)</option>
                    <option value="read">Reads (<?= number_format($readCount) ?>)</option>
                    <option value="write">Writes (<?= number_format($writeCount) ?>)</option>
                </select>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
    ?>
    <div
        data-dk-query-list-heading
        class="dk:sticky dk:top-0 dk:z-10 dk:grid dk:grid-cols-[3.5rem_minmax(0,1fr)_4.75rem] dk:items-center dk:gap-3 dk:border-b dk:border-zinc-200/90 dk:bg-white/95 dk:px-3 dk:py-2 dk:text-xs dk:font-semibold dk:text-zinc-400 dk:backdrop-blur-sm dk:dark:border-zinc-800 dk:dark:bg-zinc-950/95"
    >
        <span>Type</span>
        <span>Query</span>
        <span class="dk:flex dk:justify-end">
            <button
                type="button"
                aria-label="Sort by time"
                aria-pressed="false"
                data-dk-sort="queries:duration"
                class="dk:inline-flex dk:min-h-5 dk:items-center dk:justify-end dk:gap-1 dk:transition-colors dk:focus-visible:rounded dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:text-zinc-400 dk:hover:text-zinc-600 dk:dark:hover:text-zinc-300"
            >
                <span aria-hidden="true" data-dk-sort-indicator class="dk:relative dk:size-3 dk:shrink-0">
                    <span data-dk-sort-icon hidden class="dk:absolute dk:inset-0 dk:transition-transform"><?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3']) ?></span>
                </span>
                <span aria-hidden="true">Time</span>
                <span class="dk:sr-only" data-dk-sort-label>Time, not sorted</span>
            </button>
        </span>
    </div>
    <div data-dk-query-list>
        <?php foreach ($records as $record) :
            $record = (array)$record;
            $key = (string)($record['key'] ?? '');
            $queryType = (string)($record['query_type'] ?? 'write');
            $isSlow = !empty($record['slow']);
            $isRepeated = !empty($record['repeated']);
            ?>
        <button
            type="button"
            data-dk-query-item="<?= h($key) ?>"
            data-dk-query-key="<?= h($key) ?>"
            data-dk-execution="<?= (int)($record['execution'] ?? 0) ?>"
            data-dk-duration="<?= h((string)($record['duration_ms'] ?? 0)) ?>"
            data-dk-query-type="<?= h($queryType) ?>"
            data-dk-attention="<?= !empty($record['attention']) ? 'true' : 'false' ?>"
            data-dk-slow="<?= $isSlow ? 'true' : 'false' ?>"
            data-dk-repeated="<?= $isRepeated ? 'true' : 'false' ?>"
            data-dk-query-execution-count="<?= (int)($record['count'] ?? 1) ?>"
            data-dk-search-text="<?= h((string)($record['search'] ?? '')) ?>"
            aria-pressed="false"
            aria-controls="dk-query-detail-<?= h($key) ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[3.5rem_minmax(0,1fr)_4.75rem] dk:items-center dk:gap-3 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60"
        >
            <span class="dk:flex dk:min-w-0 dk:items-center">
                <span class="dk:box-border dk:flex dk:h-auto dk:shrink-0 dk:items-center dk:justify-center dk:rounded-md dk:bg-zinc-100/70 dk:[font-family:inherit] dk:font-bold dk:uppercase dk:tracking-wide dk:text-zinc-600 dk:dark:bg-white/10 dk:dark:text-zinc-200 dk:w-14 dk:px-1.5 dk:py-px dk:text-xs dk:leading-4"><?= h(ucfirst($queryType)) ?></span>
                <?php if ($isSlow) : ?>
                <span class="dk:sr-only"><?= $isRepeated ? 'Slow repeated query.' : 'Slow query.' ?></span>
                <?php elseif ($isRepeated) : ?>
                <span class="dk:sr-only">Repeated query.</span>
                <?php endif; ?>
            </span>
            <code data-dk-query-list-sql title="<?= h((string)($record['sql'] ?? '')) ?>" class="dk:block dk:max-h-10 dk:min-w-0 dk:overflow-hidden dk:break-words dk:text-xs dk:font-semibold dk:leading-5 dk:text-zinc-800 dk:dark:text-zinc-200"><?= h((string)($record['sql'] ?? '')) ?></code>
            <span data-dk-query-list-outcome class="dk:flex dk:min-w-0 dk:flex-col dk:items-end dk:gap-0.5 dk:text-right dk:text-xs dk:tabular-nums">
                <span
                    data-dk-query-list-driver
                    title="<?= h((string)($record['driver'] ?? '')) ?>"
                    class="dk:h-auto dk:max-w-full dk:truncate dk:bg-transparent dk:[font-family:inherit] dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
                ><?= h((string)($record['driver'] ?? '')) ?></span>
                <strong data-dk-query-list-duration class="dk:font-bold dk:text-zinc-700 dk:dark:text-zinc-200"><?= h((string)($record['duration_label'] ?? '')) ?></strong>
            </span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No queries match these controls.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0 dk:@container">
        <div data-dk-detail-empty class="dk:flex-1">
            Choose a query to inspect its evidence.
        </div>
        <?php foreach ($records as $record) :
            $record = (array)$record;
            $key = (string)($record['key'] ?? '');
            $executions = array_values((array)($record['executions'] ?? []));
            ?>
        <section
            data-dk-query-detail="<?= h($key) ?>"
            id="dk-query-detail-<?= h($key) ?>"
            aria-label="Selected query details"
            hidden
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:grid dk:grid-cols-[minmax(0,1fr)_auto] dk:items-start dk:gap-x-3 dk:gap-y-2">
                        <h3 class="dk:min-w-0 dk:text-sm dk:font-bold dk:leading-5">
                            <?= !empty($record['repeated']) ? 'Repeated query pattern' : 'Query #' . (int)($record['execution'] ?? 0) ?>
                        </h3>
                    </div>
                </header>
                <div role="tablist" aria-label="Query evidence" data-dk-detail-tabs class="dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800 dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2 dk:sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                    <div role="presentation" class="dk:min-w-0 dk:sm:col-start-2 dk:flex dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                        <?php foreach (['overview' => 'Overview', 'explain' => 'EXPLAIN'] as $tabKey => $tabLabel) : ?>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="<?= $tabKey === 'overview' ? 'true' : 'false' ?>"
                            data-dk-tab="<?= h('query:' . $key) ?>:<?= h($tabKey) ?>"
                            class="<?= h($segmentedTabClass) ?> dk:h-auto dk:min-h-8"
                        ><?= h($tabLabel) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div data-dk-tab-panel="<?= h('query:' . $key) ?>:overview">
                    <div class="dk:space-y-3 dk:p-3 dk:sm:space-y-4 dk:sm:p-4">
                        <?php if (!empty($record['repeated']) && count($executions) > 1) : ?>
                        <div class="dk:max-w-sm">
                            <p class="dk:mb-1.5 dk:text-xs dk:font-bold dk:uppercase dk:tracking-wide dk:text-zinc-400">
                                Repeated runs (<?= count($executions) ?>)
                            </p>
                            <?= $this->element('Crustum/DebugBar.ui/select_field', [
                                'scope' => 'query-execution:' . $key,
                                'label' => 'Choose a repeated run',
                                'options' => array_map(
                                    static fn(array $execution): array => [
                                        'value' => (string)($execution['execution'] ?? 0),
                                        'label' => '#' . (int)($execution['execution'] ?? 0) . ' — ' . (string)($execution['duration_label'] ?? ''),
                                    ],
                                    $executions
                                ),
                                'value' => (string)(($executions[0]['execution'] ?? 0)),
                            ]) ?>
                        </div>
                        <?php endif; ?>
                        <?php foreach ($executions as $executionIndex => $execution) :
                            $execution = (array)$execution;
                            $displaySql = (string)($execution['display_sql'] ?? $execution['sql'] ?? '');
                            $formattedSql = (string)($execution['formatted_sql'] ?? '');
                            if ($formattedSql === '' && $displaySql !== '') {
                                $formattedSql = QueryFormatter::format($displaySql);
                            }
                            $complete = !empty($execution['display_sql_complete']);
                            $sourceFile = null;
                            $sourceLine = null;
                            $sourceLabel = (string)($execution['source_label'] ?? '');
                            if (!empty($execution['source_available'])) {
                                $parts = explode(':', $sourceLabel);
                                $sourceLine = (int)array_pop($parts);
                                $sourceFile = implode(':', $parts);
                            }
                            $stack = array_values((array)($execution['stack'] ?? []));
                            ?>
                        <div
                            data-dk-query-execution="<?= (int)($execution['execution'] ?? 0) ?>"
                            <?= $executionIndex === 0 ? '' : 'hidden' ?>
                        >
                            <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2 dk:@2xl:grid-cols-4">
                                <div class="dk:min-w-0 dk:bg-transparent">
                                    <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Duration</dt>
                                    <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:font-bold dk:tabular-nums"><?= h((string)($execution['duration_label'] ?? '')) ?></dd>
                                </div>
                                <div class="dk:min-w-0 dk:bg-transparent">
                                    <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Query time</dt>
                                    <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:font-semibold dk:tabular-nums"><?= h((string)($execution['query_time_percent'] ?? 0)) ?>%</dd>
                                </div>
                                <div class="dk:min-w-0 dk:bg-transparent">
                                    <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Type</dt>
                                    <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:font-semibold"><?= h(ucfirst((string)($execution['query_type'] ?? ''))) ?></dd>
                                </div>
                                <div class="dk:min-w-0 dk:bg-transparent">
                                    <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Connection</dt>
                                    <dd title="<?= h((string)($execution['connection'] ?? '')) ?>" class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= h((string)($execution['connection'] ?? '')) ?></dd>
                                </div>
                            </dl>
                            <section class="dk:min-w-0 dk:mt-3">
                                <div class="dk:mb-2 dk:flex dk:items-center dk:justify-between dk:gap-3">
                                    <h4 class="dk:text-sm dk:font-semibold">Full query</h4>
                                    <div class="dk:shrink-0">
                                        <button
                                            type="button"
                                            data-dk-copy
                                            data-dk-copy-value="<?= h($displaySql) ?>"
                                            title="Copy query"
                                            aria-label="Copy query"
                                            class="<?= h($copyActionClass) ?>"
                                        >
                                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'copy', 'size' => '3.5']) ?>
                                            <span data-dk-copy-label>Copy query</span>
                                        </button>
                                    </div>
                                </div>
                                <pre class="<?= h($codeBlockClass) ?>"><code data-dk-language="sql" data-dk-query-sql><?= h($formattedSql !== '' ? $formattedSql : $displaySql) ?></code></pre>
                            </section>
                            <?php if (!$complete) : ?>
                            <p class="dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">Some binding values were not retained, so the remaining placeholders cannot be filled in.</p>
                            <?php endif; ?>
                            <?php if (!empty($record['likely_n_plus_one'])) : ?>
                            <header class="dk:min-w-0 dk:mt-3">
                                <h4 class="dk:text-xs dk:font-bold">Why this may be an N+1 query</h4>
                                <p class="dk:mt-0.5 dk:max-w-3xl dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">The same application call site ran this query at least three times with different bindings. If those loads happen inside a loop, trace the application call site and consider eager loading or batching.</p>
                            </header>
                            <?php endif; ?>
                            <section class="dk:p-3 dk:sm:p-4 dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800 dk:mt-3">
                                <dl class="dk:grid dk:min-w-0 dk:gap-x-6 dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800 dk:grid-cols-1">
                                    <?php if ($sourceFile !== null) : ?>
                                    <div class="dk:min-w-0 dk:bg-transparent dk:py-2">
                                        <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Source</dt>
                                        <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:mt-1">
                                            <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                                'file' => $sourceFile,
                                                'line' => $sourceLine,
                                            ]) ?>
                                        </dd>
                                    </div>
                                    <?php endif; ?>
                                </dl>
                                <?php if ($stack !== []) : ?>
                                <details class="dk:group/disclosure dk:min-w-0 dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800">
                                    <summary class="dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden">
                                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
                                        <span class="dk:min-w-0 dk:flex-1">Application stack</span>
                                        <span class="dk:shrink-0 dk:text-xs dk:font-normal dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= number_format(count($stack)) ?> <?= count($stack) === 1 ? 'frame' : 'frames' ?></span>
                                    </summary>
                                    <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
                                        <?= $this->element('Crustum/DebugBar.ui/inspector_stack', ['frames' => $stack]) ?>
                                    </div>
                                </details>
                                <?php endif; ?>
                            </section>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div data-dk-tab-panel="<?= h('query:' . $key) ?>:explain" hidden>
                    <section class="dk:space-y-3 dk:p-3 dk:sm:space-y-4 dk:sm:p-4">
                        <?php if (empty($executions[0]['explain_available'])) : ?>
                        <p class="dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400"><?= h((string)(($executions[0]['explain_unavailable_reason'] ?? null) ?: 'EXPLAIN is available for read queries only.')) ?></p>
                        <?php else : ?>
                        <p data-dk-query-explain-loading role="status" class="dk:flex dk:items-center dk:gap-2 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400" hidden>
                            <span class="dk:size-1.5 dk:shrink-0 dk:rounded-full dk:bg-indigo-500"></span>
                            <span>Running EXPLAIN…</span>
                        </p>
                        <div data-dk-query-explain-result hidden class="dk:space-y-3 dk:sm:space-y-4">
                            <div data-dk-query-explain-meta class="dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-6 dk:gap-y-2" hidden>
                                <div><span class="dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Mode </span><strong data-dk-query-explain-mode class="dk:font-semibold"></strong></div>
                                <div><span class="dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Driver </span><strong data-dk-query-explain-driver class="dk:font-semibold"></strong></div>
                            </div>
                            <pre class="<?= h($codeBlockClass) ?>"><code data-dk-language="json" data-dk-query-explain-plan-code></code></pre>
                        </div>
                        <div data-dk-query-explain-error role="alert" class="dk:rounded-lg dk:border dk:border-red-200 dk:bg-red-50/60 dk:p-3 dk:dark:border-red-950 dk:dark:bg-red-950/20" hidden>
                            <p class="dk:text-xs dk:font-semibold dk:leading-5 dk:text-red-700 dk:dark:text-red-300">EXPLAIN could not run</p>
                            <p class="dk:mt-1 dk:text-xs dk:leading-5 dk:text-zinc-600 dk:dark:text-zinc-300" data-dk-query-explain-error-text></p>
                        </div>
                        <?php endif; ?>
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
