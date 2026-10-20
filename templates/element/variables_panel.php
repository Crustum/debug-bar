<?php
/**
 * Variables panel element.
 *
 * Split list/detail like the views inspector: the toolbar page ships only
 * variable names, each variable's tree loads lazily through
 * `panels/view-variable` so one giant dump never blocks the bar. Errors
 * stay inline — they are small plain arrays, not node trees.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $error
 * @var array $variables Nodes by view-var name
 * @var array $errors Plain error arrays by view-var name
 * @var int $varsMaxDepth Depth cap applied at export time
 * @var string $panelId Stored panel id for the lazy endpoint
 */

use function Cake\Core\h;

$variables = (array)($variables ?? []);
$errors = (array)($errors ?? []);
$varsMaxDepth = (int)($varsMaxDepth ?? 5);
$panelId = (string)($panelId ?? '');
$panelUrl = $panelId !== '' ? '/debug-bar/panels/view-variable/' . $panelId : '';
$names = array_keys($variables);
natcasesort($names);
$names = array_values($names);
$selectedName = $names !== [] ? (string)$names[0] : null;
?>
<div data-dk-variables-panel data-dk-active-filter="all"<?php if ($panelUrl !== '') : ?> data-dk-variables-url="<?= h($panelUrl) ?>"<?php endif; ?> class="dk:text-zinc-950 dk:dark:text-white dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if (isset($error)) : ?>
    <p role="alert" class="dk:rounded-lg dk:bg-red-50 dk:px-3 dk:py-2 dk:text-xs dk:font-medium dk:text-red-700 dk:dark:bg-red-950/60 dk:dark:text-red-300"><?= h((string)$error) ?></p>
    <?php endif; ?>
    <?php if ($variables === [] && $errors === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'No view variables.',
        'success' => true,
    ]) ?>
    <?php else : ?>
    <?php if ($variables !== []) : ?>
    <p class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">
        <?= h(sprintf('%s levels of nested data shown.', $varsMaxDepth)) ?>
        Overwrite <code class="dk:font-mono dk:text-xs">Crustum/DebugBar.variablesPanelMaxDepth</code> to change it.
    </p>
    <?php endif; ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-variable-summary
        aria-live="polite"
        aria-atomic="true"
        class="dk:flex dk:min-w-0 dk:flex-col dk:items-start dk:text-xs dk:text-zinc-700 dk:dark:text-zinc-200"
    >
        <strong class="dk:font-bold">
            <span data-dk-variable-visible-count><?= number_format(count($names)) ?></span>
            <span><?= count($names) === 1 ? 'variable' : 'variables' ?></span>
            <span
                data-dk-visible-count
                hidden
                class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
            ></span>
        </strong>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search variables</span>
                <input
                    data-dk-search="variables"
                    type="search"
                    placeholder="Search variables"
                    aria-label="Search variables"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-variable-list aria-label="View variables">
        <?php foreach ($names as $name) :
            $pressed = $name === $selectedName;
            ?>
        <button
            type="button"
            data-dk-variable-entry
            data-dk-variable-key="<?= h((string)$name) ?>"
            data-dk-search-text="<?= h(strtolower((string)$name)) ?>"
            title="<?= h((string)$name) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            aria-controls="debugbar-variable-detail"
            class="dk:grid dk:h-auto dk:w-full dk:min-w-0 dk:grid-cols-[minmax(0,1fr)_auto] dk:items-center dk:gap-x-3 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span
                data-dk-variable-list-name
                class="dk:min-w-0 dk:truncate dk:font-mono dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"
            ><?= h((string)$name) ?></span>
            <?php if (isset($errors[$name])) : ?>
            <span title="Has validation errors" class="dk:size-1.5 dk:rounded-full dk:bg-amber-500"></span>
            <?php else : ?>
            <span></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No variables match this search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0" id="debugbar-variable-detail">
        <div data-dk-detail-empty class="dk:min-w-0 dk:flex-1 dk:p-3 dk:sm:p-4" hidden>Select a variable to inspect its value.</div>
        <?php foreach ($names as $name) :
            $pressed = $name === $selectedName;
            ?>
        <section
            data-dk-variable-detail="<?= h((string)$name) ?>"
            aria-label="Selected variable details"
            <?= $pressed ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:min-h-0 dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:flex dk:items-start dk:justify-between dk:gap-3">
                        <h3
                            data-dk-variable-detail-name
                            title="<?= h((string)$name) ?>"
                            class="dk:min-w-0 dk:flex-1 dk:break-words dk:font-mono dk:text-sm dk:font-bold dk:leading-5 dk:text-zinc-950 dk:dark:text-white"
                        ><?= h((string)$name) ?></h3>
                    </div>
                    <?php if (isset($errors[$name])) : ?>
                    <div class="dk:mt-3">
                        <p class="dk:mb-1 dk:text-xs dk:font-semibold dk:text-amber-700 dk:dark:text-amber-300">Validation errors</p>
                        <?= $this->element('Crustum/DebugBar.ui/debug_tree', [
                            'nodes' => (array)$errors[$name],
                        ]) ?>
                    </div>
                    <?php endif; ?>
                </header>
                <div
                    data-dk-variable-data-panel
                    data-dk-variables-url="<?= h($panelUrl) ?>"
                    data-dk-variable-name="<?= h((string)$name) ?>"
                    class="dk:min-h-0 dk:p-3 dk:sm:p-4"
                >
                    <?= $this->element('Crustum/DebugBar.ui/tree_filter', [
                        'label' => 'Search variable data',
                        'placeholder' => 'Search keys and values',
                    ]) ?>
                    <div
                        data-dk-variables-loading
                        role="status"
                        class="dk:flex dk:items-center dk:gap-2 dk:py-3 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"
                        <?= $pressed ? '' : 'hidden' ?>
                    >
                        <span class="dk:size-1.5 dk:rounded-full dk:bg-indigo-500"></span>
                        Loading variable data…
                    </div>
                    <div data-dk-variables-data hidden></div>
                    <div data-dk-variables-empty hidden>
                        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No data was captured for this variable.']) ?>
                    </div>
                    <div data-dk-variables-error role="alert" hidden>
                        <div class="dk:flex dk:flex-wrap dk:items-center dk:justify-between dk:gap-3">
                            <p class="dk:text-xs dk:font-semibold dk:text-amber-700 dk:dark:text-amber-300">
                                Variable data could not be loaded.
                            </p>
                            <button
                                type="button"
                                data-dk-variables-retry
                                class="dk:inline-flex dk:items-center dk:gap-1.5 dk:text-xs dk:font-semibold dk:text-indigo-600 dk:dark:text-indigo-300"
                            >
                                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'activity', 'size' => '3.5']) ?>
                                Retry
                            </button>
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
