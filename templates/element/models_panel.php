<?php
/**
 * Models panel element.
 *
 * Faithful port of the upstream `models` inspector:
 * count summary,
 * search, sortable Model/Retrieved/Writes/Reloads columns, compact group
 * rows with responsive columns, and detail Records/Source tabs with the
 * "How this model was loaded / changed" explanations, record rows with
 * per-record source disclosures, write rows with changed-field evidence,
 * and the application-source list. Interactive states are server-rendered
 * (`aria-pressed`, `hidden`); the boot script in `Requests/view` owns
 * search/sort/selection over the `data-dk-*` hooks. Styling rides the
 * shared `dk:` Tailwind utilities.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $groups Model groups from `ModelsPanel::data()` with `model`,
 *   `name`, `table`, `connection`, `retrieved`, `writes`, `reloads`,
 *   `unidentified`, `records`, `writes_list` and `sources`
 * @var array $summary Counters with `models`, `retrieved`, `writes`
 */
use Crustum\DebugBar\Service\CallsiteCapture;
use function Cake\Core\h;

$groups = array_values((array)($groups ?? []));
$summary = (array)($summary ?? []);
$count = (int)($summary['models'] ?? count($groups));
$segmentedTabClass = 'dk:relative dk:flex dk:min-w-0 dk:items-baseline dk:justify-center dk:gap-1.5 dk:whitespace-nowrap dk:rounded-md dk:border dk:border-transparent dk:bg-transparent dk:px-3 dk:py-1.5 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:transition-[background-color,color,box-shadow] dk:hover:bg-white/60 dk:hover:text-zinc-950 dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:aria-pressed:bg-white dk:aria-pressed:text-zinc-950 dk:aria-pressed:shadow-sm dk:aria-pressed:hover:bg-white dk:aria-pressed:hover:text-zinc-950 dk:aria-selected:bg-white dk:aria-selected:text-zinc-950 dk:aria-selected:shadow-sm dk:aria-selected:hover:bg-white dk:aria-selected:hover:text-zinc-950 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800/70 dk:dark:hover:text-white dk:dark:aria-pressed:bg-zinc-800 dk:dark:aria-pressed:text-white dk:dark:aria-selected:bg-zinc-800 dk:dark:aria-selected:text-white';
$copyActionClass = 'dk:inline-flex dk:h-auto dk:min-h-9 dk:shrink-0 dk:items-center dk:gap-2 dk:rounded-lg dk:px-2 dk:text-xs dk:font-medium dk:text-zinc-500 dk:transition-colors dk:hover:bg-zinc-100 dk:hover:text-zinc-900 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800 dk:dark:hover:text-zinc-100';
$sourceLinkClass = 'dk:inline-flex dk:h-auto dk:min-h-0 dk:max-w-full dk:items-center dk:border-0 dk:bg-transparent dk:p-0 dk:text-left dk:text-xs dk:font-semibold dk:text-zinc-700 dk:underline dk:decoration-zinc-300 dk:decoration-1 dk:underline-offset-2 dk:transition-colors dk:hover:bg-transparent dk:hover:text-zinc-950 dk:hover:decoration-zinc-600 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:dark:decoration-zinc-600 dk:dark:hover:bg-transparent dk:dark:hover:text-white dk:dark:hover:decoration-zinc-300';
$codeBlockClass = 'dk-code dk-scrollbar dk:m-0 dk:min-w-0 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100 dk:p-4 dk:font-mono dk:text-sm dk:leading-6 dk:text-zinc-700 dk:dark:bg-zinc-950 dk:dark:text-zinc-200 dk:whitespace-pre-wrap dk:break-words';
$disclosureClass = 'dk:group/disclosure dk:min-w-0 dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800';
$disclosureSummaryClass = 'dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden';
$disclosureCountClass = 'dk:shrink-0 dk:text-xs dk:font-normal dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400';
?>
<div data-dk-models-panel data-dk-active-filter="all" class="dk:text-zinc-950 dk:[&_code]:bg-transparent dk:[&_dd]:bg-transparent dk:[&_dl]:bg-transparent dk:[&_dt]:bg-transparent dk:dark:text-white dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if ($groups === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No ORM operations were captured in this request.']) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-1"
    >
        <div class="dk:min-w-0 dk:self-center dk:col-span-full">
            <p
                data-dk-models-summary
                aria-live="polite"
                aria-atomic="true"
                class="dk:min-w-0 dk:text-xs dk:text-zinc-700 dk:dark:text-zinc-200"
            >
                <span data-dk-models-summary-count class="dk:font-bold">
                    <?= number_format($count) ?> <?= $count === 1 ? 'model' : 'models' ?>
                </span>
                <span
                    data-dk-visible-count
                    hidden
                    class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
                ></span>
            </p>
        </div>
        <div class="dk:min-w-0 dk:col-span-full">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search models</span>
                <input
                    data-dk-search="models"
                    type="search"
                    placeholder="Search models"
                    aria-label="Search models"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    $sortHeadings = [
        ['key' => 'model', 'label' => 'Model', 'align' => 'left', 'title' => ''],
        ['key' => 'retrieved', 'label' => 'Retrieved', 'align' => 'right', 'title' => ''],
        ['key' => 'writes', 'label' => 'Writes', 'align' => 'right', 'title' => ''],
        ['key' => 'reloads', 'label' => 'Reloads', 'align' => 'right', 'title' => 'Loads after the first for identified records'],
    ];
    ob_start();
    ?>
    <div
        data-dk-models-list-heading
        class="dk:sticky dk:top-0 dk:z-10 dk:hidden dk:grid-cols-[minmax(7rem,1fr)_3.5rem_2.75rem_3.75rem] dk:gap-2 dk:border-l-0 dk:border-b dk:border-zinc-200/90 dk:bg-white/95 dk:px-3 dk:py-2 dk:text-xs dk:font-semibold dk:text-zinc-400 dk:backdrop-blur-sm dk:dark:border-zinc-800 dk:dark:bg-zinc-950/95 dk:sm:grid"
    >
        <?php foreach ($sortHeadings as $heading) : ?>
        <span class="dk:flex <?= $heading['align'] === 'right' ? 'dk:justify-end' : 'dk:justify-start' ?>"<?= $heading['title'] !== '' ? ' title="' . h($heading['title']) . '"' : '' ?>>
            <button
                type="button"
                aria-label="Sort by <?= h(strtolower($heading['label'])) ?>"
                aria-pressed="false"
                data-dk-sort="models:<?= h($heading['key']) ?>"
                class="dk:inline-flex dk:min-h-5 dk:items-center dk:gap-1 dk:transition-colors dk:focus-visible:rounded dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:justify-start dk:text-zinc-400 dk:hover:text-zinc-600 dk:dark:hover:text-zinc-300"
            >
                <span aria-hidden="true" data-dk-sort-indicator class="dk:relative dk:size-3 dk:shrink-0">
                    <span data-dk-sort-icon hidden class="dk:absolute dk:inset-0 dk:transition-transform"><?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3']) ?></span>
                </span>
                <span aria-hidden="true"><?= h($heading['label']) ?></span>
                <span class="dk:sr-only" data-dk-sort-label><?= h($heading['label']) ?>, not sorted</span>
            </button>
        </span>
        <?php endforeach; ?>
    </div>
    <div data-dk-models-list class="dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-xs dk:text-zinc-950 dk:dark:text-white">
        <?php foreach ($groups as $index => $group) :
            $group = (array)$group;
            $key = 'model-' . ($index + 1);
            $model = (string)($group['model'] ?? $group['table'] ?? '');
            $short = (string)($group['name'] ?? $group['table'] ?? '');
            $table = (string)($group['table'] ?? '');
            $connection = (string)($group['connection'] ?? '');
            $connectionLabel = $connection !== '' ? $connection : '—';
            $tableLabel = $table !== '' ? $table : '—';
            $retrieved = (int)($group['retrieved'] ?? 0);
            $writes = (int)($group['writes'] ?? 0);
            $reloads = (int)($group['reloads'] ?? 0);
            $pressed = $index === 0;
            ?>
        <button
            type="button"
            data-dk-model-entry
            data-dk-model-key="<?= h($key) ?>"
            data-dk-model-index="<?= $index ?>"
            data-dk-model-sort-name="<?= h(strtolower($short)) ?>"
            data-dk-model-sort-retrieved="<?= $retrieved ?>"
            data-dk-model-sort-writes="<?= $writes ?>"
            data-dk-model-sort-reloads="<?= $reloads ?>"
            data-dk-search-text="<?= h(strtolower($model . ' ' . $connection . ' ' . $table)) ?>"
            aria-controls="debugbar-model-detail"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:min-w-0 dk:cursor-pointer dk:grid-cols-[minmax(0,1fr)_auto] dk:items-center dk:gap-x-3 dk:gap-y-1 dk:border-l-0 dk:px-3 dk:py-2.5 dk:text-left dk:text-xs dk:text-zinc-950 dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:dark:text-white dk:sm:grid-cols-[minmax(7rem,1fr)_3.5rem_2.75rem_3.75rem] dk:sm:gap-x-2 dk:sm:gap-y-2 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-start-1 dk:row-start-1 dk:min-w-0">
                <span class="dk:block dk:truncate dk:text-xs dk:font-bold"><?= h($short) ?></span>
                <span
                    class="dk:mt-0.5 dk:block dk:truncate dk:text-xs dk:text-zinc-400"
                    title="<?= h($connection) ?> connection, <?= h($table) ?> table"
                >
                    <?= h($connectionLabel) ?>, <?= h($tableLabel) ?>
                </span>
            </span>
            <span class="dk:col-span-2 dk:row-start-2 dk:flex dk:min-w-0 dk:flex-wrap dk:gap-x-4 dk:gap-y-1 dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400 dk:sm:hidden">
                <span>
                    <span class="dk:text-zinc-400">Retrieved</span>
                    <strong class="dk:font-semibold dk:tabular-nums dk:text-zinc-700 dk:dark:text-zinc-300"><?= number_format($retrieved) ?></strong>
                </span>
                <span>
                    <span class="dk:text-zinc-400">Writes</span>
                    <strong class="dk:font-semibold dk:tabular-nums dk:text-zinc-700 dk:dark:text-zinc-300"><?= number_format($writes) ?></strong>
                </span>
                <span>
                    <span class="dk:text-zinc-400">Reloads</span>
                    <strong class="dk:font-semibold dk:tabular-nums<?= $reloads > 0 ? ' dk:text-amber-700 dk:dark:text-amber-300' : ' dk:text-zinc-700 dk:dark:text-zinc-300' ?>"><?= number_format($reloads) ?></strong>
                </span>
            </span>
            <span class="dk:col-start-2 dk:row-start-1 dk:hidden dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-700 dk:dark:text-zinc-300 dk:sm:block"><?= number_format($retrieved) ?></span>
            <span class="dk:col-start-3 dk:row-start-1 dk:hidden dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-700 dk:dark:text-zinc-300 dk:sm:block"><?= number_format($writes) ?></span>
            <span class="dk:col-start-4 dk:row-start-1 dk:hidden dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:sm:block<?= $reloads > 0 ? ' dk:text-amber-700 dk:dark:text-amber-300' : ' dk:text-zinc-500 dk:dark:text-zinc-400' ?>"><?= number_format($reloads) ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden class="dk:p-3">
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No models match this search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden class="dk:flex-1">Select a model to inspect its activity.</div>
        <?php foreach ($groups as $index => $group) :
            $group = (array)$group;
            $key = 'model-' . ($index + 1);
            $tabScope = $key;
            $model = (string)($group['model'] ?? $group['table'] ?? '');
            $connection = (string)($group['connection'] ?? '');
            $table = (string)($group['table'] ?? '');
            $connectionLabel = $connection !== '' ? $connection : '—';
            $tableLabel = $table !== '' ? $table : '—';
            $retrieved = (int)($group['retrieved'] ?? 0);
            $writes = array_values((array)($group['writes_list'] ?? []));
            $records = array_values((array)($group['records'] ?? []));
            $sources = array_values((array)($group['sources'] ?? []));
            $unidentified = (int)($group['unidentified'] ?? 0);
            ?>
        <section
            data-dk-model-detail="<?= h($key) ?>"
            aria-label="Selected model details"
            class="dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-xs dk:text-zinc-950 dk:dark:text-white"
            <?= $index === 0 ? '' : 'hidden' ?>
        >
            <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800 dk:border-l-0 dk:bg-transparent dk:text-xs dk:text-zinc-950 dk:dark:text-white">
                <div class="dk:grid dk:grid-cols-[minmax(0,1fr)_auto] dk:items-start dk:gap-x-3 dk:gap-y-2">
                    <h3 class="dk:min-w-0 dk:break-all dk:text-base dk:font-semibold dk:leading-6 dk:text-zinc-950 dk:dark:text-white"><?= h($model) ?></h3>
                </div>
                <dl class="dk:mt-2.5 dk:flex dk:flex-wrap dk:items-center dk:gap-x-3 dk:gap-y-1 dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400 dk:gap-x-3 dk:gap-y-2 dk:sm:gap-x-8">
                    <div class="dk:min-w-0">
                        <dt class="dk:text-xs dk:font-semibold dk:text-zinc-400">Connection</dt>
                        <dd class="dk:text-sm dk:font-semibold dk:text-zinc-700 dk:dark:text-zinc-300"><?= h($connectionLabel) ?></dd>
                    </div>
                    <div class="dk:min-w-0">
                        <dt class="dk:text-xs dk:font-semibold dk:text-zinc-400">Table</dt>
                        <dd class="dk:text-sm dk:font-semibold dk:text-zinc-700 dk:dark:text-zinc-300"><?= h($tableLabel) ?></dd>
                    </div>
                </dl>
            </header>
            <div role="tablist" aria-label="Model detail" data-dk-detail-tabs class="dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800 dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2 dk:sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                <div role="presentation" class="dk:min-w-0 dk:sm:col-start-2 dk:flex dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                    <?php foreach (['records' => 'Records', 'source' => 'Source'] as $tabKey => $tabLabel) : ?>
                    <button
                        type="button"
                        role="tab"
                        aria-selected="<?= $tabKey === 'records' ? 'true' : 'false' ?>"
                        data-dk-tab="<?= h($tabScope . ':' . $tabKey) ?>"
                        class="<?= h($segmentedTabClass) ?> dk:h-auto"
                    ><?= h($tabLabel) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dk:p-3 dk:sm:p-4">
                <div data-dk-tab-panel="<?= h($tabScope) ?>:records" class="dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-xs dk:text-zinc-950 dk:dark:text-white">
                    <?php if ($retrieved > 0) : ?>
                    <section class="dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-xs dk:text-zinc-950 dk:dark:text-white">
                        <header class="dk:min-w-0">
                            <h4 class="dk:text-xs dk:font-bold">How this model was loaded</h4>
                            <p class="dk:mt-0.5 dk:max-w-3xl dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">Retrieved counts each load of a record. If it exceeds 1, check whether the repeated loads are expected.</p>
                        </header>
                        <div class="dk:mt-3 dk:border-y dk:border-zinc-200/90 dk:dark:border-zinc-800">
                            <div class="dk:hidden dk:gap-3 dk:border-b dk:border-zinc-200/90 dk:py-2 dk:text-xs dk:font-semibold dk:text-zinc-400 dk:dark:border-zinc-800 dk:sm:grid dk:grid-cols-[minmax(0,1.5fr)_4.5rem_minmax(0,1fr)]">
                                <span>Identifier</span>
                                <span class="dk:text-right">Retrieved</span>
                                <span>Source</span>
                            </div>
                            <div class="dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
                                <?php foreach ($records as $record) :
                                    $record = (array)$record;
                                    $identifier = (string)($record['identifier'] ?? '');
                                    $loads = (int)($record['loads'] ?? $record['retrieved'] ?? 0);
                                    $recordSource = (string)($record['source'] ?? '');
                                    $recordSources = array_values((array)($record['sources'] ?? []));
                                    $unknownSources = (int)($record['unknown_sources'] ?? 0);
                                    ?>
                                <article class="dk:grid dk:min-w-0 dk:gap-2 dk:border-l-0 dk:bg-transparent dk:px-0 dk:py-2.5 dk:text-xs dk:text-zinc-950 dk:dark:text-white dk:sm:items-center dk:sm:gap-3 dk:sm:grid-cols-[minmax(0,1.5fr)_4.5rem_minmax(0,1fr)]">
                                    <p class="dk:min-w-0 dk:break-all dk:text-sm dk:font-semibold<?= is_numeric($identifier) ? ' dk:font-mono dk:tabular-nums' : '' ?>"><?= h($identifier) ?></p>
                                    <span class="dk:text-sm dk:font-semibold dk:tabular-nums dk:sm:text-right<?= $loads > 1 ? ' dk:text-amber-700 dk:dark:text-amber-300' : ' dk:text-zinc-600 dk:dark:text-zinc-300' ?>>
                                        <span class="dk:text-zinc-400 dk:sm:hidden">Retrieved </span><?= number_format($loads) ?>
                                    </span>
                                    <?php if ($recordSource !== '') :
                                        $recordParts = CallsiteCapture::split($recordSource);
                                        $recordSourceFile = $recordParts['file'];
                                        $recordSourceLine = $recordParts['line'];
                                        ?>
                                    <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                        'file' => $recordSourceFile,
                                        'line' => $recordSourceLine,
                                        'short' => basename(str_replace('\\', '/', $recordSourceFile)) . ($recordSourceLine !== null ? ':' . $recordSourceLine : ''),
                                        'class' => 'dk:justify-self-start dk:text-zinc-500 dk:dark:text-zinc-400',
                                    ]) ?>
                                    <?php else : ?>
                                    <span class="dk:text-xs dk:text-zinc-400">—</span>
                                    <?php endif; ?>
                                    <?php if (count($recordSources) > 1 || $unknownSources > 0) : ?>
                                    <details class="dk:sm:col-span-3 <?= h($disclosureClass) ?>">
                                        <summary class="<?= h($disclosureSummaryClass) ?>">
                                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
                                            <span class="dk:min-w-0 dk:flex-1">Record sources</span>
                                            <span class="<?= h($disclosureCountClass) ?>"><?= number_format(count($recordSources)) ?></span>
                                        </summary>
                                        <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
                                            <ul class="dk:list-none dk:space-y-3 dk:p-0">
                                                <?php foreach ($recordSources as $retainedSource) :
                                                    $retainedSource = (array)$retainedSource;
                                                    $retainedLabel = (string)($retainedSource['source'] ?? '');
                                                    ?>
                                                <li class="dk:flex dk:min-w-0 dk:flex-wrap dk:items-baseline dk:justify-between dk:gap-2">
                                                    <?php if ($retainedLabel !== '') :
                                                        $retainedParts = CallsiteCapture::split($retainedLabel);
                                                        ?>
                                                    <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                                        'file' => $retainedParts['file'],
                                                        'line' => $retainedParts['line'],
                                                        'class' => 'dk:min-w-0 dk:break-all',
                                                    ]) ?>
                                                    <?php else : ?>
                                                    <span class="dk:text-sm dk:text-zinc-500 dk:dark:text-zinc-400">Source unavailable</span>
                                                    <?php endif; ?>
                                                    <span class="dk:shrink-0 dk:text-xs dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400">
                                                        <?= number_format((int)($retainedSource['retrieval_count'] ?? 0)) ?> <?= (int)($retainedSource['retrieval_count'] ?? 0) === 1 ? 'retrieval' : 'retrievals' ?><?= (int)($retainedSource['change_count'] ?? 0) > 0 ? ', ' . number_format((int)$retainedSource['change_count']) . ' ' . ((int)$retainedSource['change_count'] === 1 ? 'write' : 'writes') : '' ?>
                                                    </span>
                                                </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <?php if ($unknownSources > 0) : ?>
                                            <p class="dk:mt-3 dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">
                                                <?= number_format($unknownSources) ?> retrievals had no application source.
                                            </p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                    <?php endif; ?>
                                </article>
                                <?php endforeach; ?>
                                <?php if ($unidentified > 0) : ?>
                                <article class="dk:grid dk:min-w-0 dk:gap-2 dk:border-l-0 dk:bg-transparent dk:px-0 dk:py-2.5 dk:sm:items-center dk:sm:gap-3 dk:sm:grid-cols-[minmax(0,1.5fr)_4.5rem_minmax(0,1fr)]">
                                    <p class="dk:text-xs dk:font-semibold">—</p>
                                    <span class="dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-600 dk:dark:text-zinc-300 dk:sm:text-right">
                                        <span class="dk:text-zinc-400 dk:sm:hidden">Retrieved </span><?= number_format($unidentified) ?>
                                    </span>
                                    <span class="dk:text-xs dk:text-zinc-400">—</span>
                                </article>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($unidentified > 0) : ?>
                        <p class="dk:mt-2 dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">
                            A dash means the model identifier was unavailable. These retrievals are excluded from the reload count.
                        </p>
                        <?php endif; ?>
                    </section>
                    <?php endif; ?>
                    <?php if ($writes !== []) : ?>
                    <section class="dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-xs dk:text-zinc-950 dk:dark:text-white<?= $retrieved > 0 ? ' dk:mt-3 dk:sm:mt-5' : '' ?>">
                        <header class="dk:min-w-0">
                            <h4 class="dk:text-xs dk:font-bold">How this model changed</h4>
                            <p class="dk:mt-0.5 dk:max-w-3xl dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">Each row is one completed write. If a write is unexpected, inspect its changed fields and application source.</p>
                        </header>
                        <div class="dk:mt-3 dk:border-y dk:border-zinc-200/90 dk:dark:border-zinc-800">
                            <div class="dk:hidden dk:grid-cols-[6rem_4rem_minmax(0,1fr)] dk:gap-3 dk:border-b dk:border-zinc-200/90 dk:py-2 dk:text-xs dk:font-semibold dk:text-zinc-400 dk:dark:border-zinc-800 dk:sm:grid">
                                <span>Operation</span>
                                <span>Record</span>
                                <span>Source</span>
                            </div>
                            <div class="dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
                                <?php foreach ($writes as $write) :
                                    $write = (array)$write;
                                    $operation = (string)($write['operation'] ?? 'Changed');
                                    $writeKey = (string)($write['key'] ?? $write['identifier'] ?? '');
                                    $writeSource = (string)($write['source'] ?? '');
                                    $changed = (array)($write['changes'] ?? $write['changed'] ?? []);
                                    $atMs = $write['at_ms'] ?? null;
                                    ?>
                                <article class="dk:grid dk:min-w-0 dk:gap-2 dk:border-l-0 dk:bg-transparent dk:px-0 dk:py-2.5 dk:text-xs dk:text-zinc-950 dk:dark:text-white dk:sm:grid-cols-[6rem_4rem_minmax(0,1fr)] dk:sm:items-center dk:sm:gap-3">
                                    <span class="dk:text-xs dk:font-semibold">
                                        <span class="dk:text-zinc-400 dk:sm:hidden">Operation </span><?= h($operation) ?>
                                    </span>
                                    <span class="dk:min-w-0 dk:break-all dk:text-sm dk:font-semibold<?= is_numeric($writeKey) ? ' dk:font-mono dk:tabular-nums' : '' ?>">
                                        <span class="dk:font-sans dk:text-zinc-400 dk:sm:hidden">Record </span><?= h($writeKey !== '' ? $writeKey : '—') ?>
                                    </span>
                                    <?php if ($writeSource !== '') :
                                        $writeParts = CallsiteCapture::split($writeSource);
                                        $writeSourceFile = $writeParts['file'];
                                        $writeSourceLine = $writeParts['line'];
                                        ?>
                                    <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                        'file' => $writeSourceFile,
                                        'line' => $writeSourceLine,
                                        'short' => basename(str_replace('\\', '/', $writeSourceFile)) . ($writeSourceLine !== null ? ':' . $writeSourceLine : ''),
                                        'class' => 'dk:justify-self-start dk:text-zinc-500 dk:dark:text-zinc-400',
                                    ]) ?>
                                    <?php else : ?>
                                    <span class="dk:text-xs dk:text-zinc-400">—</span>
                                    <?php endif; ?>
                                    <details class="dk:sm:col-span-3 <?= h($disclosureClass) ?>">
                                        <summary class="<?= h($disclosureSummaryClass) ?>">
                                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
                                            <span class="dk:min-w-0 dk:flex-1">Changed fields</span>
                                            <span class="<?= h($disclosureCountClass) ?>"><?= number_format(count($changed)) ?></span>
                                        </summary>
                                        <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
                                            <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-6 dk:gap-y-2 dk:mb-3">
                                                <div class="dk:min-w-0 dk:bg-transparent dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-2 dk:gap-y-1">
                                                    <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">From request start</dt>
                                                    <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:tabular-nums"><?= h(is_numeric($atMs) ? '+' . \Crustum\DebugBar\Service\QueryAnalyzer::formatDuration((float)$atMs) : 'Not captured') ?></dd>
                                                </div>
                                            </dl>
                                            <?php if ($changed !== []) : ?>
                                            <pre class="<?= h($codeBlockClass) ?>"><code data-dk-language="json"><?= h((string)json_encode($changed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                                            <?php else : ?>
                                            <p class="dk:text-sm dk:text-zinc-500 dk:dark:text-zinc-400">No changed field values were retained for this write.</p>
                                            <?php endif; ?>
                                        </div>
                                    </details>
                                </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </section>
                    <?php endif; ?>
                    <?php if ($retrieved === 0 && $writes === []) : ?>
                    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No model retrievals were captured for this context.']) ?>
                    <?php endif; ?>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:source" hidden class="dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-xs dk:text-zinc-950 dk:dark:text-white">
                    <section class="dk:border-l-0 dk:bg-transparent dk:p-0 dk:text-xs dk:text-zinc-950 dk:dark:text-white">
                        <?php if ($sources !== []) : ?>
                        <div class="dk:border-y dk:border-zinc-200/90 dk:dark:border-zinc-800">
                            <div aria-hidden="true" class="dk:hidden dk:grid-cols-[minmax(0,1fr)_10rem] dk:gap-3 dk:border-b dk:border-zinc-200/90 dk:py-2 dk:text-xs dk:font-semibold dk:text-zinc-400 dk:dark:border-zinc-800 dk:sm:grid">
                                <span>Source</span>
                                <span class="dk:text-right">Activity</span>
                            </div>
                            <div class="dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
                                <?php foreach ($sources as $sourceRow) :
                                    $sourceRow = (array)$sourceRow;
                                    $sourceLabel = (string)($sourceRow['source'] ?? '');
                                    $sourceRetrievals = (int)($sourceRow['retrieval_count'] ?? 0);
                                    $sourceChanges = (int)($sourceRow['change_count'] ?? 0);
                                    $activityParts = [];
                                    if ($sourceRetrievals > 0) {
                                        $activityParts[] = number_format($sourceRetrievals) . ' ' . ($sourceRetrievals === 1 ? 'retrieval' : 'retrievals');
                                    }
                                    if ($sourceChanges > 0) {
                                        $activityParts[] = number_format($sourceChanges) . ' ' . ($sourceChanges === 1 ? 'write' : 'writes');
                                    }
                                    ?>
                                <article class="dk:grid dk:min-w-0 dk:gap-2 dk:border-l-0 dk:bg-transparent dk:px-0 dk:py-3 dk:text-xs dk:text-zinc-950 dk:dark:text-white dk:sm:grid-cols-[minmax(0,1fr)_10rem] dk:sm:items-start dk:sm:gap-3">
                                    <div class="dk:min-w-0">
                                        <?php if ($sourceLabel !== '') :
                                            $sourceParts = CallsiteCapture::split($sourceLabel);
                                            ?>
                                        <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                            'file' => $sourceParts['file'],
                                            'line' => $sourceParts['line'],
                                            'class' => 'dk:break-all dk:text-xs',
                                        ]) ?>
                                        <?php else : ?>
                                        <span class="dk:text-xs dk:text-zinc-400">Source unavailable</span>
                                        <?php endif; ?>
                                        <?php $sourceChain = array_values(array_filter(array_map('strval', (array)($sourceRow['chain'] ?? [])))); ?>
                                        <?php if ($sourceChain !== []) : ?>
                                        <p class="dk:mt-1 dk:min-w-0 dk:break-words dk:font-mono dk:text-xs dk:leading-5 dk:text-zinc-400 dk:dark:text-zinc-500" title="Call chain behind this source, innermost first"><?= h(implode(' → ', $sourceChain)) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="dk:sm:text-right">
                                        <p class="dk:text-xs dk:font-semibold dk:text-zinc-400 dk:sm:hidden">Activity</p>
                                        <p class="dk:mt-0.5 dk:text-xs dk:text-zinc-600 dk:dark:text-zinc-300 dk:sm:mt-0"><?= h($activityParts === [] ? 'No retained activity' : implode(', ', $activityParts)) ?></p>
                                    </div>
                                </article>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php else : ?>
                        <div class="dk:mt-3 dk:border-l-0 dk:border-y dk:border-zinc-200/90 dk:bg-transparent dk:px-0 dk:py-3 dk:text-xs dk:text-zinc-950 dk:dark:border-zinc-800 dk:dark:text-white">
                            <p class="dk:text-xs dk:font-semibold">Source unavailable</p>
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
