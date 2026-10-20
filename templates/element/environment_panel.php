<?php
/**
 * Environment panel element.
 *
 * Version facts plus one horizontal tab per environment group, in the
 * request-page tab chrome (shared `data-dk-tab` handlers, first tab
 * preselected server-side). Values render as `debug_tree` key/value rows —
 * single line each, no copy buttons, no cards — like request detail tabs.
 * Sections without data stay out entirely; secrets in INI/PHP values pass
 * through the Credentials filter as before.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $app
 * @var array $cake
 * @var array $ini
 * @var array $php
 * @var array $includedFiles
 * @var array $includePaths
 */
use Cake\Core\Configure;
use Cake\Error\Debug\NodeInterface;
use Crustum\DebugBar\Service\NodeFlattener;
use function Cake\Core\h;

$app ??= [];
$cake ??= [];
$ini ??= [];
$php ??= [];
$includePaths ??= [];
$includedFiles ??= [];

$filterScalars = fn(array $rows): array => array_map(
    fn($value): string => (string)$this->Credentials->filter($value),
    $rows,
);
$tabs = [];
$versions = ['CakePHP Version' => Configure::version()];
if (isset($php['PHP_VERSION'])) {
    $versions['PHP Version'] = (string)$php['PHP_VERSION'];
}
$tabs['versions'] = ['label' => 'Versions', 'nodes' => $versions];
if ($app !== []) {
    $tabs['app'] = ['label' => 'Application', 'nodes' => $app];
}
if ($cake !== []) {
    $tabs['cake'] = ['label' => 'CakePHP', 'nodes' => $cake];
}
if ($ini !== []) {
    $tabs['ini'] = ['label' => 'INI', 'nodes' => $filterScalars($ini)];
}
if ($php !== []) {
    $tabs['php'] = ['label' => 'PHP', 'nodes' => $filterScalars($php)];
}
$segmentLabels = [
    'cake' => 'Cake',
    'app' => 'Application',
    'plugins' => 'Plugins',
    'vendor' => 'Vendor',
    'other' => 'Other',
    'paths' => 'Include paths',
];
$flatFiles = [];
foreach ((array)$includedFiles as $segment => $node) {
    if ($node instanceof NodeInterface) {
        $flat = NodeFlattener::toPlain($node);
        if ($flat !== [] && $flat !== null && $flat !== '') {
            $flatFiles[$segmentLabels[(string)$segment] ?? (string)$segment] = $flat;
        }
    }
}
$flatPaths = [];
foreach (array_values((array)$includePaths) as $node) {
    if ($node instanceof NodeInterface) {
        $flat = NodeFlattener::toPlain($node);
        if (is_string($flat) && $flat !== '') {
            $flatPaths[] = $flat;
        }
    }
}
if ($flatPaths !== []) {
    $flatFiles[$segmentLabels['paths']] = $flatPaths;
}
if ($flatFiles !== []) {
    $tabs['files'] = ['label' => 'Files', 'nodes' => $flatFiles];
}
$firstTab = array_key_first($tabs);
$scope = 'environment';
?>
<div data-dk-environment-panel class="dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if (ini_get('zend.assertions') !== '1') : ?>
    <p data-dk-banner="zend-assertions" role="status" class="dk:flex dk:items-start dk:gap-2 dk:px-3 dk:sm:px-4 dk:pt-3 dk:text-xs dk:font-medium dk:leading-4 dk:text-amber-700 dk:dark:text-amber-300">
        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'warning', 'size' => '3.5', 'class' => 'dk:mt-px']) ?>
        <span>You should set <code class="dk:font-mono dk:text-xs">zend.assertions</code> to <code class="dk:font-mono dk:text-xs">1</code> in your <code class="dk:font-mono dk:text-xs">php.ini</code> for your development environment.</span>
    </p>
    <?php endif; ?>
    <div role="tablist" aria-label="Environment" data-dk-detail-tabs class="dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2 dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800">
        <div role="presentation" class="dk:flex dk:min-w-0 dk:max-w-full dk:gap-1 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
            <?php foreach ($tabs as $tabKey => $tab) : ?>
            <button
                type="button"
                role="tab"
                data-dk-tab="<?= h($scope) ?>:<?= h((string)$tabKey) ?>"
                aria-selected="<?= $tabKey === $firstTab ? 'true' : 'false' ?>"
                class="dk:relative dk:flex dk:min-w-0 dk:shrink-0 dk:items-baseline dk:justify-center dk:gap-1.5 dk:whitespace-nowrap dk:rounded-md dk:border dk:border-transparent dk:bg-transparent dk:px-3 dk:py-1.5 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:transition-[background-color,color,box-shadow] dk:hover:bg-white/60 dk:hover:text-zinc-950 dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:aria-selected:bg-indigo-50 dk:aria-selected:text-indigo-700 dk:aria-selected:shadow-sm dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800/70 dk:dark:hover:text-white dk:dark:aria-selected:bg-indigo-950/70 dk:dark:aria-selected:text-indigo-300"
            >
                <span><?= h($tab['label']) ?></span>
                <span class="dk:shrink-0 dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-400"><?= number_format(is_countable($tab['nodes']) ? count($tab['nodes']) : 0) ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="dk:min-w-0 dk:p-3 dk:sm:p-4">
        <?php foreach ($tabs as $tabKey => $tab) : ?>
        <div data-dk-tab-panel="<?= h($scope) ?>:<?= h((string)$tabKey) ?>"<?= $tabKey === $firstTab ? '' : ' hidden' ?>>
            <?= $this->element('Crustum/DebugBar.ui/debug_tree', ['nodes' => $tab['nodes']]) ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>
