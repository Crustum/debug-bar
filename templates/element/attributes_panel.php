<?php
/**
 * Attributes panel element.
 *
 * List + detail split in the house inspector chrome: summary line with the
 * retained/dropped counters, search field plus a scope/type dropdown,
 * selectable rows with pressed states, and detail facts per attribute.
 * Row selection needs no shell changes: the `data-dk-selection-config`
 * script tag registers this panel with the generic `selectPanelRecord()`
 * handler. Filtering needs no shell changes either: rows carry their
 * config scope and target type as space-separated
 * `data-dk-filter-group` tokens and the single `data-dk-select` dropdown
 * drives the generic `applyListFilter()` — one active key at a time.
 *
 * Data comes from `AttributesPanel::data()` — a bounded snapshot taken
 * directly via `AttributeResolver::collection()`, never a shell-out.
 * Rows past the snapshot cap are served on demand by
 * `AttributesController::query()` / `::inspect()` / `::warm()`
 * (POST JSON, same as `ComposerController` owns `outdated` / `audit`
 * for Packages).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var bool $available
 * @var string|null $reason
 * @var array $configs
 * @var int $total
 * @var int $retained
 * @var int $dropped
 * @var bool $truncated
 * @var array $items
 * @var array $byType
 * @var array $byConfig
 */
use Crustum\DebugBar\Panel\AttributesPanel;
use function Cake\Core\h;

$available = (bool)($available ?? false);
$reason = (string)($reason ?? '');
$configs = (array)($configs ?? []);
$items = array_values((array)($items ?? []));
$total = (int)($total ?? 0);
$retained = count($items);
$dropped = (int)($dropped ?? 0);
$truncated = (bool)($truncated ?? ($dropped > 0));
$configErrors = [];
foreach ($configs as $configName => $configStats) {
    $stats = (array)$configStats;
    $error = (string)($stats['error'] ?? '');
    if ($error !== '') {
        $configErrors[(string)$configName] = $error;
    }
}
$retainedByConfig = [];
$retainedByType = [];
foreach ($items as $item) {
    $item = (array)$item;
    $configKey = (string)($item['config'] ?? '');
    $typeKey = (string)($item['targetType'] ?? '');
    $retainedByConfig[$configKey] = ($retainedByConfig[$configKey] ?? 0) + 1;
    $retainedByType[$typeKey] = ($retainedByType[$typeKey] ?? 0) + 1;
}
$rowKey = static function (array $item): string {
    if (isset($item['key']) && is_string($item['key']) && $item['key'] !== '') {
        return $item['key'];
    }

    return AttributesPanel::rowKey($item);
};
$firstRowKey = $items !== [] ? $rowKey((array)$items[0]) : null;
$csrfToken = (string)($this->getRequest()->getAttribute('csrfToken') ?? '');
?>
<div data-dk-attributes-panel data-dk-active-filter="all"
    <?php if ($csrfToken !== '') : ?>
    data-dk-csrf-token="<?= h($csrfToken) ?>"
    <?php endif; ?>
    class="dk:space-y-4 dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col dk:lg:space-y-0">
    <?php if (!$available) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => $reason !== '' ? $reason : 'AttributeResolver is unavailable.',
    ]) ?>
    <?php elseif ($items === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'No attributes were discovered for this request.',
    ]) ?>
    <?php else : ?>
    <?php if ($truncated) : ?>
    <div data-dk-banner="attributes-truncated" role="status" class="dk:rounded-lg dk:border dk:border-amber-200 dk:bg-amber-50/60 dk:p-3 dk:text-xs dk:font-semibold dk:leading-5 dk:text-amber-700 dk:dark:border-amber-950 dk:dark:bg-amber-950/20 dk:dark:text-amber-300">
        Showing <?= number_format($retained) ?> of <?= number_format($total) ?> attributes.
        Refine via `POST /debug-bar/attributes/query` — see `AttributesController::query()`.
    </div>
    <?php endif; ?>
    <?php if ($configErrors !== []) : ?>
    <div data-dk-banner="attributes-errors" role="status" class="dk:rounded-lg dk:border dk:border-amber-200 dk:bg-amber-50/60 dk:p-3 dk:text-xs dk:font-semibold dk:leading-5 dk:text-amber-700 dk:dark:border-amber-950 dk:dark:bg-amber-950/20 dk:dark:text-amber-300">
        <?php foreach ($configErrors as $configName => $error) : ?>
        Scope `<?= h($configName) ?>`: <?= h($error) ?>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <details class="dk:group/disclosure dk:min-w-0 dk:border-b dk:border-zinc-200/90 dk:dark:border-zinc-800">
        <summary class="dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden">
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
            <span class="dk:min-w-0 dk:flex-1">Inspect on demand</span>
        </summary>
        <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
            <div class="dk:flex dk:flex-wrap dk:items-center dk:gap-x-4 dk:gap-y-2">
                <button
                    type="button"
                    data-dk-attributes-warm
                    data-dk-attributes-warm-url="<?= h((string)$this->Url->build([
                        'plugin' => 'Crustum/DebugBar',
                        'controller' => 'Attributes',
                        'action' => 'warm',
                    ])) ?>"
                    class="dk:inline-flex dk:h-9 dk:shrink-0 dk:items-center dk:gap-2 dk:rounded-lg dk:bg-indigo-600 dk:px-3 dk:text-xs dk:font-bold dk:text-white dk:transition dk:hover:bg-indigo-500 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:disabled:cursor-wait dk:disabled:opacity-60"
                >
                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'refresh', 'size' => '3.5']) ?>
                    <span data-dk-attributes-warm-label>Warm cache</span>
                </button>
                <label class="dk:inline-flex dk:cursor-pointer dk:items-center dk:gap-2 dk:text-xs dk:font-medium dk:text-zinc-600 dk:dark:text-zinc-300">
                    <span class="dk:sr-only">Scope for warm and inspect</span>
                    <select
                        data-dk-attributes-warm-config
                        class="dk:h-9 dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                    >
                        <?php foreach (array_keys($configs) as $configName) : ?>
                        <option value="<?= h((string)$configName) ?>"><?= h((string)$configName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button
                    type="button"
                    data-dk-attributes-inspect
                    data-dk-attributes-inspect-url="<?= h((string)$this->Url->build([
                        'plugin' => 'Crustum/DebugBar',
                        'controller' => 'Attributes',
                        'action' => 'inspect',
                    ])) ?>"
                    title="Inspect attributes by partial attribute or class name (attributes inspect)"
                    class="dk:inline-flex dk:h-9 dk:shrink-0 dk:items-center dk:gap-2 dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:px-3 dk:text-xs dk:font-bold dk:text-zinc-700 dk:transition dk:hover:bg-zinc-100 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70 dk:dark:text-zinc-200 dk:dark:hover:bg-zinc-800 dk:disabled:cursor-wait dk:disabled:opacity-60"
                >
                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '3.5']) ?>
                    <span data-dk-attributes-inspect-label>Inspect</span>
                </button>
            </div>
            <div class="dk:mt-3 dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-1 dk:sm:grid-cols-2">
                <label class="dk:relative dk:block dk:min-w-0">
                    <span class="dk:sr-only">Attribute class (partial match)</span>
                    <input
                        data-dk-attributes-inspect-attribute
                        type="search"
                        placeholder="Attribute class (partial)"
                        aria-label="Attribute class (partial match)"
                        autocomplete="off"
                        class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-3 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                    />
                </label>
                <label class="dk:relative dk:block dk:min-w-0">
                    <span class="dk:sr-only">Target class (partial match)</span>
                    <input
                        data-dk-attributes-inspect-class
                        type="search"
                        placeholder="Target class (partial)"
                        aria-label="Target class (partial match)"
                        autocomplete="off"
                        class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-3 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                    />
                </label>
            </div>
            <div data-dk-attributes-warm-results aria-live="polite" class="dk:min-w-0"></div>
            <div data-dk-attributes-inspect-results aria-live="polite" class="dk:min-w-0"></div>
        </div>
    </details>
    <?php
    ob_start();
    ?>
    <p
        data-dk-attributes-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <?= number_format($total) ?> <?= $total === 1 ? 'attribute' : 'attributes' ?>
        across <?= number_format(count($configs)) ?> <?= count($configs) === 1 ? 'scope' : 'scopes' ?>
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
                <span class="dk:sr-only">Search attributes</span>
                <input
                    data-dk-search="attributes"
                    type="search"
                    placeholder="Search attributes"
                    aria-label="Search attributes"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter attributes by scope or target type</span>
                <select
                    data-dk-select="attributes-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <option value="all">All (<?= number_format($retained) ?>)</option>
                    <?php foreach ($retainedByConfig as $configName => $configCount) : ?>
                    <option value="<?= h($configName) ?>">Scope: <?= h($configName) ?> (<?= number_format($configCount) ?>)</option>
                    <?php endforeach; ?>
                    <?php foreach ($retainedByType as $type => $typeCount) : ?>
                    <option value="<?= h($type) ?>">Type: <?= h(ucfirst($type)) ?> (<?= number_format($typeCount) ?>)</option>
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
    <div data-dk-attributes-list aria-label="Discovered attributes">
        <?php foreach ($items as $item) :
            $item = (array)$item;
            $key = $rowKey($item);
            $pressed = $key === $firstRowKey;
            $attributeName = (string)($item['attributeName'] ?? '');
            $className = (string)($item['className'] ?? '');
            $target = (array)($item['target'] ?? []);
            $configName = (string)($item['config'] ?? '');
            $type = (string)($item['targetType'] ?? $target['type'] ?? '');
            $targetName = (string)($item['targetName'] ?? $target['name'] ?? '');
            ?>
        <button
            type="button"
            data-dk-attribute-entry
            data-dk-attribute-key="<?= h($key) ?>"
            data-dk-filter-group="<?= h(trim($configName . ' ' . $type)) ?>"
            data-dk-search-text="<?= h(strtolower($attributeName . ' ' . $className . ' ' . $targetName . ' ' . $configName)) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[minmax(0,1fr)_auto] dk:items-baseline dk:gap-x-3 dk:gap-y-1 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-start-1 dk:row-start-1 dk:min-w-0 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h($attributeName) ?></span>
            <span data-dk-attribute-type class="dk:col-start-2 dk:row-start-1 dk:w-full dk:truncate dk:text-right dk:font-mono dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($type) ?></span>
            <span class="dk:col-start-1 dk:row-start-2 dk:col-span-2 dk:min-w-0 dk:truncate dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($targetName !== '' ? $targetName : $className) ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No attributes match this filter and search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden>No attribute is selected. Adjust the filter or search.</div>
        <?php foreach ($items as $item) :
            $item = (array)$item;
            $key = $rowKey($item);
            $pressed = $key === $firstRowKey;
            $attributeName = (string)($item['attributeName'] ?? '');
            $className = (string)($item['className'] ?? '');
            $target = (array)($item['target'] ?? []);
            $configName = (string)($item['config'] ?? '');
            $type = (string)($item['targetType'] ?? $target['type'] ?? '');
            $targetName = (string)($item['targetName'] ?? $target['name'] ?? '');
            $file = (string)($item['filePath'] ?? '');
            $line = (int)($item['lineNumber'] ?? 0);
            $plugin = $item['pluginName'] ?? null;
            $facts = [
                ['Target class', $className !== '' ? $className : '—'],
                ['Plugin', is_string($plugin) && $plugin !== '' ? $plugin : '-'],
                ['Type', $type !== '' ? $type : '—'],
                ['Target', $targetName !== '' ? $targetName : '—'],
                ['Scope', $configName !== '' ? $configName : '—'],
                ['File', $file !== '' ? $file . ($line > 0 ? ':' . $line : '') : '—'],
                ['File time', (string)($item['fileTimeDisplay'] ?? '') !== '' ? (string)$item['fileTimeDisplay'] : '—'],
            ];
            $argumentsDisplay = (array)($item['argumentsDisplay'] ?? []);
            ?>
        <section
            data-dk-attribute-detail="<?= h($key) ?>"
            aria-label="Selected attribute details"
            <?= $pressed ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <h3 class="dk:min-w-0 dk:truncate dk:text-base dk:font-bold dk:leading-6"><?= h($attributeName) ?></h3>
                    <p class="dk:mt-1 dk:break-words dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($className) ?></p>
                </header>
                <div class="dk:p-3 dk:sm:p-4">
                    <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2 dk:@2xl:grid-cols-4">
                        <?php foreach ($facts as [$factLabel, $factValue]) : ?>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($factLabel) ?></dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold" title="<?= h($factValue) ?>"><?= h($factValue) ?></dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                    <?php if ($argumentsDisplay !== []) : ?>
                    <h4 class="dk:mt-4 dk:text-xs dk:font-bold dk:text-zinc-700 dk:dark:text-zinc-200">Arguments</h4>
                    <dl class="dk:mt-2 dk:min-w-0 dk:space-y-1.5">
                        <?php foreach ($argumentsDisplay as $argName => $argValue) : ?>
                        <div class="dk:flex dk:min-w-0 dk:gap-2 dk:text-xs">
                            <dt class="dk:shrink-0 dk:font-mono dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"><?= h((string)$argName) ?></dt>
                            <dd class="dk:min-w-0 dk:break-words dk:font-mono dk:text-zinc-800 dk:dark:text-zinc-200"><?= h((string)$argValue) ?></dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                    <?php endif; ?>
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
