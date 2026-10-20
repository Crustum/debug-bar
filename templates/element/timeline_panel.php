<?php
/**
 * Timeline panel element.
 *
 * Faithful port of the upstream `timeline` inspector: summary with matching
 * and loaded counts, search plus View/Source select, sticky waterfall
 * header with tick labels, activity rows with kind marks positioned by
 * `--dk-timeline-*` percents, pressed states, and detail (At/Type/Started/
 * Duration facts, source, Open inspector). Interactive states are
 * server-rendered (`aria-pressed`, `aria-selected`, `hidden`); the boot
 * script in `Requests/view` owns search/filter/selection over the
 * `data-dk-*` hooks. Styling rides the shared `dk:` Tailwind utilities.
 *
 * CakePHP notes (no fake data): rows render server-side without cursor
 * paging, so loaded equals retained; there is no key-activity flag, so the
 * View optgroup holds All activity only; milestones are absent from the
 * merged events, so marks are spans (durations) and point events.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $events Merged events with `at`, `duration_ms`, `type`, `label`, `panel`, `source`
 * @var int $retained Retained event count
 * @var int $omitted Omitted event count
 * @var float $end_ms Request window in milliseconds
 */
use Crustum\DebugBar\Service\QueryAnalyzer;
use function Cake\Core\h;

$events = array_values((array)($events ?? []));
$retained = (int)($retained ?? count($events));
$omitted = (int)($omitted ?? 0);
$captured = $retained + $omitted;
$window = max(0.001, (float)($end_ms ?? 0));
$ticks = [0, 25, 50, 75, 100];

$inspectorMap = [
    'timer' => ['key' => 'timer', 'label' => 'Timer'],
    'sqllog' => ['key' => 'queries', 'label' => 'Queries'],
    'log' => ['key' => 'log', 'label' => 'Logs'],
    'cache' => ['key' => 'cache', 'label' => 'Cache'],
];
$items = [];
foreach ($events as $index => $event) {
    $event = (array)$event;
    $panelKey = (string)(($event['panel'] ?? '') !== '' ? $event['panel'] : 'timer');
    $inspector = $inspectorMap[$panelKey] ?? ['key' => $panelKey, 'label' => ucfirst($panelKey)];
    $at = max(0.0, (float)($event['at'] ?? 0));
    $duration = max(0.0, (float)($event['duration_ms'] ?? 0));
    $kind = $duration > 0 ? 'span' : 'event';
    $source = is_array($event['source'] ?? null) ? $event['source'] : null;
    $sourceFile = $source !== null ? (string)($source['file'] ?? '') : '';
    $sourceLine = $source !== null && isset($source['line']) ? (int)$source['line'] : null;
    $items[] = [
        'id' => 'timeline-' . ($index + 1),
        'inspector' => $inspector['key'],
        'inspector_label' => $inspector['label'],
        'kind' => $kind,
        'kind_label' => $kind === 'span' ? 'Duration' : 'Event',
        'label' => (string)($event['label'] ?? ''),
        'at' => $at,
        'at_label' => QueryAnalyzer::formatDuration($at),
        'duration' => $duration,
        'duration_label' => $duration > 0 ? QueryAnalyzer::formatDuration($duration) : null,
        'at_percent' => min(100.0, $at / $window * 100),
        'width_percent' => max(0.0, min(100.0, $duration / $window * 100)),
        'source_file' => $sourceFile,
        'source_line' => $sourceLine,
        'source_label' => $sourceFile !== '' ? $sourceFile . ($sourceLine !== null ? ':' . $sourceLine : '') : null,
        'search' => strtolower((string)($event['label'] ?? '') . ' ' . $inspector['label'] . ' ' . $panelKey),
        'filter_group' => $panelKey,
    ];
}
$sourceFilters = [];
foreach ($items as $item) {
    $sourceFilters[$item['filter_group']] = $item['inspector_label'];
}
?>
<div data-dk-timeline data-dk-timeline-panel data-dk-active-filter="all" class="dk:text-zinc-950 dk:dark:text-white dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if ($captured === 0 && $items === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'No timeline activity was captured for this request.',
        'success' => true,
    ]) ?>
    <?php else : ?>
    <?php if ($omitted > 0) : ?>
    <p data-dk-banner="timeline-incomplete" role="status" class="dk:flex dk:items-start dk:gap-2 dk:text-xs dk:font-medium dk:leading-4 dk:text-amber-700 dk:dark:text-amber-300">
        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'warning', 'size' => '3.5', 'class' => 'dk:mt-px']) ?>
        <span>Timeline incomplete: <?= number_format($omitted) ?> omitted.</span>
    </p>
    <?php endif; ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-timeline-summary
        aria-live="polite"
        aria-atomic="true"
        class="dk:flex dk:min-w-0 dk:flex-col dk:items-start dk:text-xs dk:text-zinc-700 dk:dark:text-zinc-200"
    >
        <strong class="dk:font-bold">
            <span data-dk-timeline-matching><?= number_format($retained) ?></span>
            <span>matching</span>
            <span
                data-dk-visible-count
                hidden
                class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
            ></span>
        </strong>
        <span class="dk:mt-0.5 dk:block dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">
            <?= number_format($retained) ?> loaded from <?= number_format($captured) ?> captured
            across <?= h(QueryAnalyzer::formatDuration($window)) ?>
        </span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search timeline activity</span>
                <input
                    data-dk-search="timeline"
                    type="search"
                    placeholder="Search activity or source"
                    aria-label="Search timeline activity"
                    autocomplete="off"
                    maxlength="500"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter timeline activity</span>
                <select
                    data-dk-select="timeline-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <optgroup label="View">
                        <option value="all">All activity</option>
                    </optgroup>
                    <optgroup label="Source">
                        <option value="request">Request</option>
                        <?php foreach ($sourceFilters as $value => $label) : ?>
                        <option value="<?= h($value) ?>"><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
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
        data-dk-timeline-waterfall-header
        class="dk:sticky dk:top-0 dk:z-10 dk:hidden dk:grid-cols-[minmax(13rem,0.8fr)_minmax(20rem,2fr)_6rem] dk:border-b dk:border-zinc-200/90 dk:bg-white/95 dk:text-xs dk:font-semibold dk:uppercase dk:tracking-wider dk:text-zinc-400 dk:backdrop-blur-sm dk:dark:border-zinc-800 dk:dark:bg-zinc-950/95 dk:lg:grid"
    >
        <span class="dk:px-3 dk:py-2">Activity</span>
        <span
            data-dk-timeline-waterfall
            class="dk:relative dk:border-x dk:border-zinc-200/90 dk:dark:border-zinc-800"
            aria-label="Timeline from zero to <?= h(QueryAnalyzer::formatDuration($window)) ?>"
        >
            <?php foreach ($ticks as $tick) : ?>
            <span
                data-dk-timeline-tick="<?= $tick ?>"
                class="dk:absolute dk:top-1/2 dk:-translate-x-1/2 dk:-translate-y-1/2 dk:normal-case dk:tracking-normal dk:first:translate-x-0 dk:last:-translate-x-full"
                style="left: <?= $tick ?>%"
            ><?= h(QueryAnalyzer::formatDuration($window * $tick / 100)) ?></span>
            <?php endforeach; ?>
        </span>
        <span class="dk:px-3 dk:py-2 dk:text-right">Time</span>
    </div>
    <div data-dk-timeline-list class="dk:divide-y dk:divide-zinc-200/80 dk:bg-transparent dk:dark:divide-zinc-800">
        <?php foreach ($items as $index => $item) :
            $pressed = $index === 0;
            ?>
        <button
            type="button"
            data-dk-timeline-item="<?= h($item['id']) ?>"
            data-dk-timeline-entry
            data-dk-timeline-key="<?= h($item['id']) ?>"
            data-dk-timeline-inspector="<?= h($item['filter_group']) ?>"
            data-dk-timeline-inspector-label="<?= h($item['inspector_label']) ?>"
            data-dk-timeline-kind="<?= h($item['kind_label']) ?>"
            data-dk-timeline-label="<?= h($item['label']) ?>"
            data-dk-timeline-at="<?= h((string)$item['at']) ?>"
            data-dk-timeline-at-label="<?= h($item['at_label']) ?>"
            data-dk-timeline-duration="<?= h((string)$item['duration']) ?>"
            data-dk-timeline-duration-label="<?= h((string)($item['duration_label'] ?? '')) ?>"
            data-dk-timeline-source="<?= h((string)($item['source_label'] ?? '')) ?>"
            data-dk-filter-group="<?= h($item['filter_group']) ?>"
            data-dk-search-text="<?= h($item['search']) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            aria-controls="debugbar-timeline-detail"
            class="dk:grid dk:h-auto dk:w-full dk:min-w-0 dk:grid-cols-[minmax(0,1fr)_auto] dk:items-center dk:gap-x-3 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3 dk:lg:grid-cols-[minmax(13rem,0.8fr)_minmax(20rem,2fr)_6rem] dk:lg:px-0 dk:lg:py-0<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
            style="--dk-timeline-at: <?= h((string)round($item['at_percent'], 2)) ?>%; --dk-timeline-start: <?= h((string)round($item['at_percent'], 2)) ?>%; --dk-timeline-width: <?= h((string)round($item['width_percent'], 2)) ?>%;"
        >
            <span class="dk:min-w-0 dk:lg:px-3 dk:lg:py-2.5">
                <span class="dk:block dk:truncate dk:text-xs dk:font-bold"><?= h($item['label']) ?></span>
                <span
                    data-dk-timeline-activity-inspector
                    class="dk:mt-0.5 dk:block dk:truncate dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
                >
                    <?= h($item['inspector_label']) ?>
                    <?php if ($item['duration_label'] !== null) : ?>
                    <span class="dk:lg:hidden">, <?= h($item['duration_label']) ?></span>
                    <?php endif; ?>
                </span>
            </span>
            <span
                data-dk-timeline-track
                class="dk:relative dk:hidden dk:h-full dk:min-h-11 dk:border-x dk:border-zinc-200/90 dk:bg-[linear-gradient(to_right,transparent_calc(25%-0.5px),rgba(161,161,170,0.16)_25%,transparent_calc(25%+0.5px),transparent_calc(50%-0.5px),rgba(161,161,170,0.16)_50%,transparent_calc(50%+0.5px),transparent_calc(75%-0.5px),rgba(161,161,170,0.16)_75%,transparent_calc(75%+0.5px))] dk:dark:border-zinc-800 dk:lg:block"
            >
                <?php if ($item['kind'] === 'span') : ?>
                <span
                    data-dk-timeline-mark
                    class="dk:absolute dk:top-1/2 dk:left-[var(--dk-timeline-start)] dk:h-2.5 dk:w-[max(3px,var(--dk-timeline-width))] dk:-translate-y-1/2 dk:rounded-sm dk:bg-indigo-500 dk:dark:bg-indigo-400"
                ></span>
                <?php else : ?>
                <span
                    data-dk-timeline-mark
                    class="dk:absolute dk:top-1/2 dk:left-[clamp(4px,var(--dk-timeline-at),calc(100%-4px))] dk:size-2.5 dk:-translate-x-1/2 dk:-translate-y-1/2 dk:rounded-full dk:border-2 dk:border-white dk:bg-zinc-500 dk:dark:border-zinc-950 dk:dark:bg-zinc-300"
                ></span>
                <?php endif; ?>
            </span>
            <span class="dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-600 dk:dark:text-zinc-300 dk:lg:px-3 dk:lg:py-2.5">
                <?= h($item['at_label']) ?>
            </span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No timeline activity matches this search and filter.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0" id="debugbar-timeline-detail">
        <div class="dk:p-3 dk:pb-0 dk:sm:px-4">
            <button
                type="button"
                data-dk-workspace-back
                class="dk:inline-flex dk:items-center dk:gap-1.5 dk:text-xs dk:font-semibold dk:text-indigo-600 dk:dark:text-indigo-300"
            >
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:rotate-90']) ?>
                Timeline
            </button>
        </div>
        <div data-dk-detail-empty class="dk:min-w-0 dk:p-3 dk:sm:p-4" hidden>Select timeline activity to inspect its evidence.</div>
        <?php foreach ($items as $index => $item) : ?>
        <section
            data-dk-timeline-detail="<?= h($item['id']) ?>"
            aria-label="Selected timeline activity details"
            <?= $index === 0 ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:flex dk:min-w-0 dk:flex-wrap dk:items-start dk:justify-between dk:gap-3">
                        <div class="dk:min-w-0 dk:flex-1">
                            <p class="dk:text-xs dk:font-semibold dk:uppercase dk:tracking-wider dk:text-zinc-400"><?= h($item['inspector_label']) ?></p>
                            <h3
                                data-dk-timeline-detail-label
                                class="dk:mt-0.5 dk:break-words dk:text-base dk:font-bold dk:leading-6"
                            ><?= h($item['label']) ?></h3>
                        </div>
                        <button
                            type="button"
                            data-dk-open-inspector="<?= h($item['inspector']) ?>"
                            class="dk:inline-flex dk:shrink-0 dk:items-center dk:gap-1.5 dk:text-xs dk:font-semibold dk:text-indigo-600 dk:dark:text-indigo-300"
                        >
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'external-link', 'size' => '3.5']) ?>
                            Open inspector
                        </button>
                    </div>
                </header>
                <div class="dk:p-3 dk:sm:p-4">
                    <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2 dk:@2xl:grid-cols-4">
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">At</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:font-semibold dk:tabular-nums"><?= h($item['at_label']) ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Type</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:font-semibold"><?= h($item['kind_label']) ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Started</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:font-semibold dk:tabular-nums"><?= h($item['at_label']) ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Duration</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:font-semibold dk:tabular-nums"><?= h($item['duration_label'] ?? 'Point event') ?></dd>
                        </div>
                    </dl>
                    <div class="dk:min-w-0 dk:mt-3">
                        <p class="dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Source</p>
                        <div class="dk:mt-1">
                            <?php if ($item['source_file'] !== '') : ?>
                            <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                'file' => $item['source_file'],
                                'line' => $item['source_line'],
                            ]) ?>
                            <?php else : ?>
                            <span class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Not captured for this activity.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <?php endforeach; ?>
    </div>
    <?php
    $detailBody = (string)ob_get_clean();
    ?>
    <?= $this->element('Crustum/DebugBar.ui/inspector_workspace', [
        'mode' => 'focus',
        'frame' => 'top',
        'list' => $this->element('Crustum/DebugBar.ui/inspector_list_panel', [
            'controls' => $controls,
            'body' => $listBody,
        ]),
        'detail' => $detailBody,
    ]) ?>
    <?php endif; ?>
</div>
