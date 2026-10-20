<?php
/**
 * Views panel element.
 *
 * Faithful port of the upstream `views` inspector: summary with view/render
 * counts, search plus Application/All/Framework select defaulting to
 * application views, group rows (name, origin, render counts) with pressed
 * states, and detail (Origin/Passed values facts, render source, per-render
 * select, lazy Passed data with loading/empty/error states). Renders group
 * by template file; variables snapshot once per request, so every render of
 * a group shares the same passed data. Interactive states are server-rendered
 * (`aria-pressed`, `aria-selected`, `hidden`); the boot script in
 * `Requests/view` owns search/filter/selection/lazy-load over the
 * `data-dk-*` hooks. Styling rides the shared `dk:` Tailwind utilities.
 *
 * CakePHP notes (no fake data): view composers are a framework concept with
 * no captured equivalent here, so the Composers fact stays out; per-render
 * variable sets are a single snapshot, shared across renders. Legacy rows
 * without origins fall back to the app flag; one group keeps one origin
 * with application winning over plugin over framework; positions are
 * 1-based to match the `?render=` endpoint; plugin rows name their owner.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $renders Render rows with `file`, `app`, `order`
 * @var mixed $vars Exported view variables snapshot (node, array or null)
 * @var string $panelId Stored panel id for the lazy endpoint
 */
use Cake\Error\Debug\NodeInterface;
use Crustum\DebugBar\Service\NodeFlattener;
use function Cake\Core\h;

$renders = array_values((array)($renders ?? []));
$panelId = (string)($panelId ?? '');
$panelUrl = '/debug-bar/panels/view-data/' . $panelId;

$groups = [];
foreach ($renders as $position => $render) {
    $render = (array)$render;
    $file = (string)($render['file'] ?? '');
    if (!isset($groups[$file])) {
        $groups[$file] = [
            'file' => $file,
            'name' => $file !== '' ? basename(str_replace('\\', '/', $file)) : 'Unknown view',
            'dir' => $file !== '' ? dirname(str_replace('\\', '/', $file)) : '',
            'origin' => 'framework',
            'plugin' => '',
            'items' => [],
        ];
    }
    $origin = (string)($render['origin'] ?? '');
    if ($origin === '') {
        $origin = !empty($render['app']) ? 'application' : 'framework';
    }
    if ($origin !== 'application' && $origin !== 'plugin') {
        $origin = 'framework';
    }
    $plugin = (string)($render['plugin'] ?? '');
    if ($plugin !== '' && ($groups[$file]['plugin'] ?? '') === '') {
        $groups[$file]['plugin'] = $plugin;
    }
    $rank = ['framework' => 0, 'plugin' => 1, 'application' => 2];
    if ($rank[$origin] > $rank[$groups[$file]['origin']]) {
        $groups[$file]['origin'] = $origin;
    }
    $groups[$file]['items'][] = [
        'position' => $position + 1,
        'order' => (int)($render['order'] ?? $position + 1),
    ];
}
$groups = array_values($groups);
foreach ($groups as $index => $group) {
    $groups[$index]['id'] = 'view-' . ($index + 1);
    $groups[$index]['count'] = count($group['items']);
    $groups[$index]['search'] = strtolower($group['file'] . ' ' . $group['name'] . ' ' . ($group['plugin'] ?? ''));
}
$total = count($renders);
$appCount = 0;
$pluginCount = 0;
$frameworkCount = 0;
foreach ($groups as $group) {
    if ($group['origin'] === 'application') {
        $appCount += $group['count'];
    } elseif ($group['origin'] === 'plugin') {
        $pluginCount += $group['count'];
    } else {
        $frameworkCount += $group['count'];
    }
}
$originLabel = static function (array $group): string {
    if (($group['origin'] ?? '') === 'application') {
        return 'Application';
    }
    if (($group['origin'] ?? '') === 'plugin') {
        return ($group['plugin'] ?? '') !== '' ? (string)$group['plugin'] : 'Plugin';
    }

    return 'Framework';
};
$defaultFilter = 'all';
if ($appCount > 0) {
    $defaultFilter = 'application';
} elseif ($pluginCount > 0) {
    $defaultFilter = 'plugin';
}
$visibleGroups = 0;
$visibleRenders = 0;
foreach ($groups as $group) {
    if ($defaultFilter === 'all' || $group['origin'] === $defaultFilter) {
        $visibleGroups++;
        $visibleRenders += $group['count'];
    }
}
$selectedId = null;
foreach ($groups as $group) {
    if ($defaultFilter === 'all' || $group['origin'] === $defaultFilter) {
        $selectedId = $group['id'];
        break;
    }
}
$flatVars = null;
if (isset($vars) && $vars instanceof NodeInterface) {
    $flatVars = NodeFlattener::toPlain($vars);
} elseif (is_array($vars ?? null)) {
    $flatVars = NodeFlattener::map($vars);
}
$varsCount = is_array($flatVars) ? count($flatVars) : 0;
?>
<div data-dk-views-panel data-dk-active-filter="<?= h($defaultFilter) ?>" data-dk-views-url="<?= h($panelUrl) ?>" class="dk:text-zinc-950 dk:dark:text-white dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if ($groups === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'No views were rendered.',
        'success' => true,
    ]) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-view-summary
        aria-live="polite"
        aria-atomic="true"
        class="dk:flex dk:min-w-0 dk:flex-col dk:items-start dk:text-xs dk:text-zinc-700 dk:dark:text-zinc-200"
    >
        <strong class="dk:font-bold">
            <span data-dk-view-visible-count><?= number_format($visibleGroups) ?></span>
            <span><?= $visibleGroups === 1 ? 'view' : 'views' ?></span>
            <span
                data-dk-visible-count
                hidden
                class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
            ></span>
        </strong>
        <span class="dk:mt-0.5 dk:block dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">
            <span data-dk-view-visible-render-count><?= number_format($visibleRenders) ?></span>
            <span><?= $visibleRenders === 1 ? 'render' : 'renders' ?></span>
            shown
        </span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search views</span>
                <input
                    data-dk-search="views"
                    type="search"
                    placeholder="Search views or sources"
                    aria-label="Search views"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter views by origin</span>
                <select
                    data-dk-select="views-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <option value="application"<?= $defaultFilter === 'application' ? ' selected' : '' ?>>Application (<?= number_format($appCount) ?>)</option>
                    <option value="plugin"<?= $defaultFilter === 'plugin' ? ' selected' : '' ?>>Plugin (<?= number_format($pluginCount) ?>)</option>
                    <option value="all"<?= $defaultFilter === 'all' ? ' selected' : '' ?>>All (<?= number_format($total) ?>)</option>
                    <option value="framework">Framework (<?= number_format($frameworkCount) ?>)</option>
                </select>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-view-list aria-label="Rendered views">
        <?php foreach ($groups as $group) :
            $pressed = $group['id'] === $selectedId;
            $visible = $defaultFilter === 'all' || $group['origin'] === $defaultFilter;
            ?>
        <button
            type="button"
            data-dk-view-entry
            data-dk-view-key="<?= h($group['id']) ?>"
            data-dk-view-origin="<?= h($group['origin']) ?>"
            data-dk-view-count="<?= $group['count'] ?>"
            data-dk-filter-group="<?= h($group['origin']) ?>"
            data-dk-search-text="<?= h($group['search']) ?>"
            title="<?= h($group['file']) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            aria-controls="debugbar-view-detail"
            <?= $visible ? '' : 'hidden' ?>
            class="dk:grid dk:h-auto dk:w-full dk:min-w-0 dk:grid-cols-[minmax(0,1fr)_auto] dk:items-center dk:gap-x-3 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span
                data-dk-view-list-name
                class="dk:min-w-0 dk:truncate dk:font-sans dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"
            ><?= h($group['name']) ?></span>
            <span class="dk:text-right dk:text-xs dk:font-bold dk:tabular-nums"><?= number_format($group['count']) ?></span>
            <span class="dk:min-w-0 dk:truncate dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">
                <?= h($originLabel($group)) ?>
            </span>
            <span class="dk:text-right dk:text-xs dk:font-medium dk:text-zinc-400">
                <?= $group['count'] === 1 ? 'render' : 'renders' ?>
            </span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No views match this origin and search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0" id="debugbar-view-detail">
        <div data-dk-detail-empty class="dk:min-w-0 dk:flex-1 dk:p-3 dk:sm:p-4" hidden>Select a view to inspect its renders, data, and source.</div>
        <?php foreach ($groups as $group) :
            $pressed = $group['id'] === $selectedId;
            $visible = $defaultFilter === 'all' || $group['origin'] === $defaultFilter;
            $firstPosition = (int)($group['items'][0]['position'] ?? 0);
            ?>
        <section
            data-dk-view-detail="<?= h($group['id']) ?>"
            aria-label="Selected view details"
            <?= $pressed && $visible ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:min-h-0 dk:flex-col">
                <header data-dk-view-detail-header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:flex dk:min-w-0 dk:flex-wrap dk:items-center dk:justify-between dk:gap-3">
                        <h3
                            data-dk-view-detail-name
                            title="<?= h($group['file']) ?>"
                            class="dk:min-w-0 dk:flex-1 dk:break-words dk:font-sans dk:text-sm dk:font-bold dk:leading-5 dk:text-zinc-950 dk:dark:text-white"
                        ><?= h($group['name']) ?></h3>
                        <?php if ($group['count'] > 1) : ?>
                        <div class="dk:w-32 dk:shrink-0">
                            <label class="dk:relative dk:block">
                                <span class="dk:sr-only">Select rendered view instance</span>
                                <select
                                    data-dk-select="views-render"
                                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                                >
                                    <?php foreach ($group['items'] as $item) : ?>
                                    <option value="<?= (int)$item['position'] ?>">Render #<?= (int)$item['order'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <dl
                        data-dk-view-detail-metadata
                        class="dk:mt-3 dk:flex dk:min-w-0 dk:flex-wrap dk:gap-x-6 dk:gap-y-2"
                    >
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-400">Origin</dt>
                            <dd class="dk:font-semibold"><?= h($originLabel($group)) ?></dd>
                        </div>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-400">Passed values</dt>
                            <dd class="dk:font-semibold dk:tabular-nums"><?= number_format($varsCount) ?></dd>
                        </div>
                    </dl>
                </header>
                <section data-dk-view-detail-content class="dk:space-y-3 dk:p-3 dk:sm:space-y-4 dk:sm:p-4">
                    <?php if ($group['file'] !== '') : ?>
                    <div class="dk:min-w-0">
                        <p class="dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Render source</p>
                        <div class="dk:mt-1">
                            <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                'file' => $group['file'],
                                'line' => null,
                            ]) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div
                        data-dk-view-data-panel
                        data-dk-views-url="<?= h($panelUrl) ?>"
                        data-dk-views-render="<?= $firstPosition ?>"
                        class="dk:min-h-0 dk:border-t dk:border-zinc-200/90 dk:pt-3 dk:sm:pt-4 dk:dark:border-zinc-800"
                    >
                        <h4 class="dk:mb-2 dk:text-xs dk:font-bold">Passed data</h4>
                        <?= $this->element('Crustum/DebugBar.ui/tree_filter', [
                            'label' => 'Search passed data',
                            'placeholder' => 'Search keys and values',
                        ]) ?>
                        <div
                            data-dk-views-loading
                            role="status"
                            class="dk:flex dk:items-center dk:gap-2 dk:py-3 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"
                            <?= $pressed && $visible ? '' : 'hidden' ?>
                        >
                            <span class="dk:size-1.5 dk:rounded-full dk:bg-indigo-500"></span>
                            Loading render data…
                        </div>
                        <div data-dk-views-data hidden></div>
                        <div data-dk-views-empty hidden>
                            <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No data was passed directly to this render.']) ?>
                        </div>
                        <div data-dk-views-error role="alert" hidden>
                            <div class="dk:flex dk:flex-wrap dk:items-center dk:justify-between dk:gap-3">
                                <p class="dk:text-xs dk:font-semibold dk:text-amber-700 dk:dark:text-amber-300">
                                    Render data could not be loaded.
                                </p>
                                <button
                                    type="button"
                                    data-dk-views-retry
                                    class="dk:inline-flex dk:items-center dk:gap-1.5 dk:text-xs dk:font-semibold dk:text-indigo-600 dk:dark:text-indigo-300"
                                >
                                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'activity', 'size' => '3.5']) ?>
                                    Retry
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
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
