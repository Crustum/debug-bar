<?php
/**
 * Deprecations panel element.
 *
 * Stored rows from requests where the panel collected no data unserialize
 * to an empty array; every section defaults so old rows stay viewable.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $paths
 * @var array $app
 * @var array $cake
 * @var array $plugins
 * @var array $vendor
 * @var array $other
 */

use function Cake\Core\h;

$app ??= [];
$plugins ??= [];
$cake ??= [];
$vendor ??= [];
$other ??= [];

$groups = [];
if (count($app)) {
    $groups[] = ['label' => 'App', 'items' => array_values($app)];
}
foreach ((array)$plugins as $plugin => $pluginData) {
    if (count((array)$pluginData)) {
        $groups[] = ['label' => (string)$plugin, 'items' => array_values((array)$pluginData)];
    }
}
if (count($cake)) {
    $groups[] = ['label' => 'Cake', 'items' => array_values($cake)];
}
foreach ((array)$vendor as $vendorSection => $vendorData) {
    if (count((array)$vendorData)) {
        $groups[] = ['label' => (string)$vendorSection, 'items' => array_values((array)$vendorData)];
    }
}
if (count($other)) {
    $groups[] = ['label' => 'Other', 'items' => array_values($other)];
}
?>
<div data-dk-deprecations-panel class="dk:space-y-4">
    <?php if ($groups === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'No deprecations',
        'success' => true,
    ]) ?>
    <?php else : ?>
    <?php foreach ($groups as $group) : ?>
    <section aria-label="<?= h($group['label']) ?> deprecations">
        <h3 class="dk:text-xs dk:font-bold dk:uppercase dk:tracking-wider dk:text-zinc-400 dk:dark:text-zinc-500">
            <?= h($group['label']) ?>
            <span class="dk:ml-1 dk:tabular-nums"><?= number_format(count($group['items'])) ?></span>
        </h3>
        <ul class="dk:mt-1.5 dk:space-y-1.5">
            <?php foreach ($group['items'] as $message) :
                $message = (array)$message;
                ?>
            <li class="dk:rounded-lg dk:bg-zinc-950/[0.03] dk:px-3 dk:py-2 dk:text-xs dk:dark:bg-white/[0.04]">
                <div class="dk:font-mono dk:font-semibold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h((string)($message['niceFile'] ?? '')) ?>:<?= h((string)($message['line'] ?? '')) ?></div>
                <div class="dk:mt-0.5 dk:break-words dk:text-zinc-600 dk:dark:text-zinc-300"><?= h((string)($message['message'] ?? '')) ?></div>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php endforeach; ?>
    <?php endif; ?>
</div>
