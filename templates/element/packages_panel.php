<?php
/**
 * Packages panel element.
 *
 * List + detail split in the house inspector chrome: summary line with a
 * visible count, search field plus a Requirements dropdown, selectable
 * rows with pressed states, and detail facts per package (composer.lock
 * data plus a packagist link). The boot script in `Requests/view` owns
 * search/filter/selection over the `data-dk-*` hooks. Styling rides the
 * shared `dk:` Tailwind utilities.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $packages
 * @var array $devPackages
 */
use function Cake\Core\h;

$packages = array_values((array)($packages ?? []));
$devPackages = array_values((array)($devPackages ?? []));
$count = count($packages) + count($devPackages);
$filters = ['all' => ['All', $count]];
if ($packages !== []) {
    $filters['packages'] = ['Requirements', count($packages)];
}
if ($devPackages !== []) {
    $filters['dev'] = ['Dev Requirements', count($devPackages)];
}
$all = [];
foreach ($packages as $package) {
    $all[] = [(array)$package, 'packages'];
}
foreach ($devPackages as $package) {
    $all[] = [(array)$package, 'dev'];
}
$firstName = $all !== [] ? (string)($all[0][0]['name'] ?? '') : '';
$csrfToken = (string)($this->getRequest()->getAttribute('csrfToken') ?? '');
$flat = static function ($value): string {
    if (is_array($value)) {
        $names = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $names[] = (string)($item['name'] ?? '');
            } else {
                $names[] = (string)$item;
            }
        }
        $names = array_filter($names, static fn(string $name): bool => $name !== '');

        return implode(', ', $names);
    }
    if (is_bool($value)) {
        return $value ? 'Yes' : 'No';
    }

    return (string)$value;
};
?>
<div data-dk-packages-panel data-dk-active-filter="all"
    <?php if ($csrfToken !== '') : ?>
    data-dk-csrf-token="<?= h($csrfToken) ?>"
    <?php endif; ?>
    class="dk:space-y-4 dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col dk:lg:space-y-0">
    <?php if ($all === []) : ?>
    <div data-dk-banner="no-lock" role="status" class="dk:rounded-lg dk:border dk:border-amber-200 dk:bg-amber-50/60 dk:p-3 dk:text-xs dk:font-semibold dk:leading-5 dk:text-amber-700 dk:dark:border-amber-950 dk:dark:bg-amber-950/20 dk:dark:text-amber-300">
        'composer.lock' not found
    </div>
    <?php else : ?>
    <details class="dk:group/disclosure dk:min-w-0 dk:border-b dk:border-zinc-200/90 dk:dark:border-zinc-800">
        <summary class="dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden">
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
            <span class="dk:min-w-0 dk:flex-1">Check for updates</span>
        </summary>
        <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
            <div class="dk:flex dk:flex-wrap dk:items-center dk:gap-x-4 dk:gap-y-2">
                <button
                    type="button"
                    data-dk-packages-check
                    data-dk-packages-check-url="<?= h((string)$this->Url->build([
                        'plugin' => 'Crustum/DebugBar',
                        'controller' => 'Composer',
                        'action' => 'checkDependencies',
                    ])) ?>"
                    class="dk:inline-flex dk:h-9 dk:shrink-0 dk:items-center dk:gap-2 dk:rounded-lg dk:bg-indigo-600 dk:px-3 dk:text-xs dk:font-bold dk:text-white dk:transition dk:hover:bg-indigo-500 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:disabled:cursor-wait dk:disabled:opacity-60"
                >
                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'download', 'size' => '3.5']) ?>
                    <span data-dk-packages-check-label>Check for Updates</span>
                </button>
                <label class="dk:inline-flex dk:cursor-pointer dk:items-center dk:gap-2 dk:text-xs dk:font-medium dk:text-zinc-600 dk:dark:text-zinc-300">
                    <input
                        type="checkbox"
                        data-dk-packages-check-direct
                        class="dk:size-3.5 dk:accent-indigo-600"
                    />
                    Direct dependencies only
                </label>
                <button
                    type="button"
                    data-dk-packages-audit
                    data-dk-packages-audit-url="<?= h((string)$this->Url->build([
                        'plugin' => 'Crustum/DebugBar',
                        'controller' => 'Composer',
                        'action' => 'audit',
                    ])) ?>"
                    title="Audit locked dependencies for known vulnerabilities (composer audit)"
                    class="dk:inline-flex dk:h-9 dk:shrink-0 dk:items-center dk:gap-2 dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:px-3 dk:text-xs dk:font-bold dk:text-zinc-700 dk:transition dk:hover:bg-zinc-100 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70 dk:dark:text-zinc-200 dk:dark:hover:bg-zinc-800 dk:disabled:cursor-wait dk:disabled:opacity-60"
                >
                    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'warning', 'size' => '3.5']) ?>
                    <span data-dk-packages-audit-label>Audit vulnerabilities</span>
                </button>
            </div>
            <div data-dk-packages-updates aria-live="polite" class="dk:min-w-0"></div>
            <div data-dk-packages-audit-results aria-live="polite" class="dk:min-w-0"></div>
        </div>
    </details>
    <?php
    ob_start();
    ?>
    <p
        data-dk-packages-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <?= number_format($count) ?> <?= $count === 1 ? 'package' : 'packages' ?>
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
                <span class="dk:sr-only">Search packages</span>
                <input
                    data-dk-search="packages"
                    type="search"
                    placeholder="Search packages"
                    aria-label="Search packages"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter packages by group</span>
                <select
                    data-dk-select="packages-filter"
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
    <div data-dk-packages-list aria-label="Composer packages">
        <?php foreach ($all as [$package, $group]) :
            $name = (string)($package['name'] ?? '');
            $version = (string)($package['version'] ?? '');
            $description = (string)($package['description'] ?? '');
            $pressed = $name !== '' && $name === $firstName;
            ?>
        <button
            type="button"
            data-dk-package-entry
            data-dk-package-key="<?= h($name) ?>"
            data-dk-filter-group="<?= h($group) ?>"
            data-dk-search-text="<?= h(strtolower($name . ' ' . $description)) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[minmax(0,1fr)_auto] dk:items-baseline dk:gap-x-3 dk:gap-y-1 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-start-1 dk:row-start-1 dk:min-w-0 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h($name) ?></span>
            <span data-dk-package-version class="dk:col-start-2 dk:row-start-1 dk:w-full dk:truncate dk:text-right dk:font-mono dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($version) ?></span>
            <?php if ($description !== '') : ?>
            <span class="dk:col-start-1 dk:row-start-2 dk:col-span-2 dk:min-w-0 dk:truncate dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($description) ?></span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No packages match this filter and search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden>No package is selected. Adjust the filter or search.</div>
        <?php foreach ($all as [$package, $group]) :
            $name = (string)($package['name'] ?? '');
            $pressed = $name !== '' && $name === $firstName;
            $source = (array)($package['source'] ?? []);
            $sourceLabel = '';
            if ($source !== []) {
                $sourceLabel = (string)($source['url'] ?? '');
                if (isset($source['reference'])) {
                    $reference = (string)$source['reference'];
                    $sourceLabel .= ($sourceLabel !== '' ? ' @ ' : '') . substr($reference, 0, 12);
                }
            }
            $facts = [
                ['Version', $flat($package['version'] ?? '')],
                ['Type', $flat($package['type'] ?? '')],
                ['License', $flat($package['license'] ?? '')],
                ['Homepage', $flat($package['homepage'] ?? '')],
                ['Authors', $flat($package['authors'] ?? '')],
            ];
            if ($sourceLabel !== '') {
                $facts[] = ['Source', $sourceLabel];
            }
            ?>
        <section
            data-dk-package-detail="<?= h($name) ?>"
            aria-label="Selected package details"
            <?= $pressed ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <h3 class="dk:min-w-0 dk:truncate dk:text-base dk:font-bold dk:leading-6">
                        <a
                            href="https://packagist.org/packages/<?= h($name) ?>"
                            title="<?= h((string)($package['description'] ?? '')) ?>"
                            target="_blank"
                            rel="noreferrer"
                            class="dk:underline dk:decoration-zinc-300 dk:underline-offset-2 dk:transition-colors dk:hover:text-indigo-600 dk:hover:decoration-indigo-300 dk:dark:hover:text-indigo-300"
                        ><?= h($name) ?></a>
                    </h3>
                    <?php if ((string)($package['description'] ?? '') !== '') : ?>
                    <p class="dk:mt-1 dk:break-words dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400"><?= h((string)$package['description']) ?></p>
                    <?php endif; ?>
                </header>
                <div class="dk:p-3 dk:sm:p-4">
                    <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2 dk:@2xl:grid-cols-4">
                        <?php foreach ($facts as [$factLabel, $factValue]) : ?>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($factLabel) ?></dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= h($factValue !== '' ? $factValue : '—') ?></dd>
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
