<?php
/**
 * Plugins panel element.
 *
 * List + detail split in the house inspector chrome: summary line with a
 * visible count, search field plus a Loaded/Unloaded dropdown, selectable
 * rows with pressed states, and detail facts per plugin. The boot script
 * in `Requests/view` owns search/filter/selection over the `data-dk-*`
 * hooks. Styling rides the shared `dk:` Tailwind utilities.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var bool $hasEmptyAppConfig
 * @var array $plugins
 */
use function Cake\Core\h;

$plugins = (array)($plugins ?? []);
$loaded = 0;
foreach ($plugins as $pluginConfig) {
    if (!empty(((array)$pluginConfig)['isLoaded'])) {
        $loaded++;
    }
}
$count = count($plugins);
$unloaded = $count - $loaded;
$filters = ['all' => ['All', $count]];
if ($loaded > 0) {
    $filters['loaded'] = ['Loaded', $loaded];
}
if ($unloaded > 0) {
    $filters['unloaded'] = ['Unloaded', $unloaded];
}
$flagLabels = static function (array $pluginConfig): string {
    $flags = [];
    if (!empty($pluginConfig['onlyDebug'])) {
        $flags[] = 'debug only';
    }
    if (!empty($pluginConfig['onlyCli'])) {
        $flags[] = 'cli only';
    }
    if (!empty($pluginConfig['optional'])) {
        $flags[] = 'optional';
    }

    return $flags !== [] ? implode(', ', $flags) : 'No restrictions';
};
?>
<div data-dk-plugins-panel data-dk-active-filter="all" class="dk:space-y-4 dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col dk:lg:space-y-0">
    <?php if ($plugins === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'No plugins are registered in config/plugins.php.',
    ]) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-plugins-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <?= number_format($count) ?> <?= $count === 1 ? 'plugin' : 'plugins' ?>,
        <?= number_format($loaded) ?> loaded
        <span
            data-dk-visible-count
            hidden
            class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
        ></span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search plugins</span>
                <input
                    data-dk-search="plugins"
                    type="search"
                    placeholder="Search plugins"
                    aria-label="Search plugins"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter plugins by status</span>
                <select
                    data-dk-select="plugins-filter"
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
    <div data-dk-plugins-list aria-label="Application plugins">
        <?php foreach ($plugins as $pluginName => $pluginConfig) :
            $pluginName = (string)$pluginName;
            $pluginConfig = (array)$pluginConfig;
            $isLoaded = !empty($pluginConfig['isLoaded']);
            $pressed = $pluginName === (string)array_key_first($plugins);
            ?>
        <button
            type="button"
            data-dk-plugin-entry
            data-dk-plugin-key="<?= h($pluginName) ?>"
            data-dk-filter-group="<?= $isLoaded ? 'loaded' : 'unloaded' ?>"
            data-dk-search-text="<?= h(strtolower($pluginName . ' ' . $flagLabels($pluginConfig))) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[minmax(0,1fr)_auto] dk:items-baseline dk:gap-x-3 dk:gap-y-1 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-start-1 dk:row-start-1 dk:min-w-0 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h($pluginName) ?></span>
            <span class="dk:col-start-2 dk:row-start-1 dk:flex dk:shrink-0">
                <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', [
                    'operation' => $isLoaded ? 'LOADED' : 'OFF',
                ]) ?>
            </span>
            <span class="dk:col-start-1 dk:row-start-2 dk:min-w-0 dk:truncate dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($flagLabels($pluginConfig)) ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No plugins match this filter and search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden>No plugin is selected. Adjust the filter or search.</div>
        <?php foreach ($plugins as $pluginName => $pluginConfig) :
            $pluginName = (string)$pluginName;
            $pluginConfig = (array)$pluginConfig;
            $isLoaded = !empty($pluginConfig['isLoaded']);
            $pressed = $pluginName === (string)array_key_first($plugins);
            $facts = [
                ['Status', $isLoaded ? 'Loaded' : 'Not loaded'],
                ['Debug only', !empty($pluginConfig['onlyDebug']) ? 'Yes' : 'No'],
                ['CLI only', !empty($pluginConfig['onlyCli']) ? 'Yes' : 'No'],
                ['Optional', !empty($pluginConfig['optional']) ? 'Yes' : 'No'],
            ];
            ?>
        <section
            data-dk-plugin-detail="<?= h($pluginName) ?>"
            aria-label="Selected plugin details"
            <?= $pressed ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <h3 class="dk:flex dk:min-w-0 dk:flex-nowrap dk:items-center dk:gap-2 dk:overflow-hidden">
                        <span class="dk:min-w-0 dk:truncate dk:text-base dk:font-bold dk:leading-6"><?= h($pluginName) ?></span>
                        <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', [
                            'operation' => $isLoaded ? 'LOADED' : 'OFF',
                        ]) ?>
                    </h3>
                </header>
                <div class="dk:p-3 dk:sm:p-4">
                    <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2 dk:@2xl:grid-cols-4">
                        <?php foreach ($facts as [$factLabel, $factValue]) : ?>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($factLabel) ?></dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= h($factValue) ?></dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
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
