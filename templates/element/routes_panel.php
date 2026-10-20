<?php
/**
 * Routes panel element.
 *
 * List + detail split in the house inspector chrome: summary line with the
 * matched route, search field plus a per-group dropdown, selectable rows
 * with pressed states (matched/duplicate markers kept), and detail facts
 * with the route defaults. The boot script in `Requests/view` owns
 * search/filter/selection over the `data-dk-*` hooks. Styling rides the
 * shared `dk:` Tailwind utilities. Routes read from the live router:
 * stored rows carry no route objects.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $matchedRoute
 */
use Cake\Error\Debugger;
use Cake\Routing\Router;
use Cake\Utility\Hash;
use Cake\Utility\Text;
use Crustum\DebugBar\Service\RouteFacts;
use function Cake\Core\h;

$routes = array_values(Router::routes());
$matchedRoute ??= '';

$groups = [];
$duplicates = [];
foreach ($routes as $route) {
    $group = $route->defaults['plugin'] ?? 'app';
    $groups[$group] = ($groups[$group] ?? 0) + 1;
    $duplicates[$route->template] = ($duplicates[$route->template] ?? 0) + 1;
}
$count = count($routes);
$filters = ['all' => ['All', $count]];
foreach ($groups as $group => $groupCount) {
    $filters[Text::slug($group)] = [$group, $groupCount];
}
?>
<div data-dk-routes-panel data-dk-active-filter="all" class="dk:space-y-4 dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col dk:lg:space-y-0">
    <?php if ($routes === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No routes are registered.']) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-routes-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <?= number_format($count) ?> <?= $count === 1 ? 'route' : 'routes' ?>,
        <?php if ($matchedRoute !== '') : ?>
        matched <code class="dk-code"><?= h($matchedRoute) ?></code>
        <?php else : ?>
        no matched route
        <?php endif; ?>
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
                <span class="dk:sr-only">Search routes</span>
                <input
                    data-dk-search="routes"
                    type="search"
                    placeholder="Search route names or templates"
                    aria-label="Search routes"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter routes by group</span>
                <select
                    data-dk-select="routes-filter"
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
    <div data-dk-routes-list aria-label="Application routes">
        <?php foreach ($routes as $index => $route) :
            $group = $route->defaults['plugin'] ?? 'app';
            $prefix = RouteFacts::prefix($route);
            $name = (string)Hash::get($route->options, '_name', $route->getName());
            $display = $name !== '' ? $name : '(unnamed)';
            $isMatched = $matchedRoute !== '' && $matchedRoute === $route->template;
            $isDuplicate = ($duplicates[$route->template] ?? 0) > 1;
            $key = 'route-' . $index;
            $pressed = $index === 0;
            ?>
        <button
            type="button"
            data-dk-route-entry
            data-dk-route-key="<?= h($key) ?>"
            data-dk-filter-group="<?= h(Text::slug($group)) ?>"
            data-dk-search-text="<?= h(strtolower($display . ' ' . $route->template . ' ' . $group . ' ' . $prefix)) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[minmax(0,1fr)_auto] dk:items-baseline dk:gap-x-3 dk:gap-y-1 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-start-1 dk:row-start-1 dk:min-w-0 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h($display) ?></span>
            <span class="dk:col-start-2 dk:row-start-1 dk:flex dk:shrink-0 dk:gap-1.5">
                <?php if ($prefix !== '') : ?>
                <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', ['operation' => $prefix]) ?>
                <?php endif; ?>
                <?php if ($isMatched) : ?>
                <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', ['operation' => 'MATCHED']) ?>
                <?php endif; ?>
                <?php if ($isDuplicate) : ?>
                <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', ['operation' => 'DUPLICATE']) ?>
                <?php endif; ?>
            </span>
            <span class="dk:col-start-1 dk:row-start-2 dk:min-w-0 dk:truncate dk:font-mono dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($route->template) ?></span>
            <span class="dk:col-start-2 dk:row-start-2 dk:w-full dk:truncate dk:text-right dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($group) ?></span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No routes match this filter and search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden>No route is selected. Adjust the filter or search.</div>
        <?php foreach ($routes as $index => $route) :
            $group = $route->defaults['plugin'] ?? 'app';
            $prefix = RouteFacts::prefix($route);
            $name = (string)Hash::get($route->options, '_name', $route->getName());
            $display = $name !== '' ? $name : '(unnamed)';
            $isMatched = $matchedRoute !== '' && $matchedRoute === $route->template;
            $isDuplicate = ($duplicates[$route->template] ?? 0) > 1;
            $key = 'route-' . $index;
            $pressed = $index === 0;
            ?>
        <section
            data-dk-route-detail="<?= h($key) ?>"
            aria-label="Selected route details"
            <?= $pressed ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <h3 class="dk:flex dk:min-w-0 dk:flex-nowrap dk:items-center dk:gap-2 dk:overflow-hidden">
                        <span class="dk:min-w-0 dk:truncate dk:text-base dk:font-bold dk:leading-6"><?= h($display) ?></span>
                        <?php if ($isMatched) : ?>
                        <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', ['operation' => 'MATCHED']) ?>
                        <?php endif; ?>
                    </h3>
                    <code class="dk:mt-1 dk:block dk:break-all dk:bg-transparent dk:font-mono dk:text-xs dk:font-medium dk:leading-4 dk:text-zinc-400"><?= h($route->template) ?></code>
                </header>
                <div class="dk:p-3 dk:sm:p-4">
                    <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2 dk:@2xl:grid-cols-4">
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Group</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= h($group) ?></dd>
                        </div>
                        <?php if ($prefix !== '') : ?>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Prefix</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= h($prefix) ?></dd>
                        </div>
                        <?php endif; ?>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Matched</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= $isMatched ? 'Yes' : 'No' ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Duplicate template</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= $isDuplicate ? 'Yes' : 'No' ?></dd>
                        </div>
                        <?php foreach ((array)$route->defaults as $defaultKey => $defaultValue) : ?>
                        <?php if ($defaultKey === 'prefix') {
                            continue;
                        } ?>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"><?= h((string)$defaultKey) ?></dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:font-mono"><?= Debugger::exportVar($defaultValue) ?></dd>
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
