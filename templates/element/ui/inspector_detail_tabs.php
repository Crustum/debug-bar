<?php
/**
 * Inspector detail tabs element.
 *
 * Pure CSS + `data-dk-tab` hooks; the active tab class is toggled by shell JS.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $scope Tabs scope, e.g. `queries:3`
 * @var array $tabs List of `key`, `label` rows
 * @var string $active Active tab key
 */
use function Cake\Core\h;

$scope = (string)($scope ?? 'detail');
$tabs = array_values((array)($tabs ?? []));
$active = (string)($active ?? ($tabs[0]['key'] ?? ''));
?>
<div data-dk-detail-tabs="<?= h($scope) ?>" role="tablist" class="dk-detail-tabs">
    <?php foreach ($tabs as $tab) :
        $key = (string)($tab['key'] ?? '');
        ?>
    <button
        type="button"
        role="tab"
        aria-selected="<?= $key !== '' && $key === $active ? 'true' : 'false' ?>"
        data-dk-tab="<?= h($scope . ':' . $key) ?>"
        class="dk-detail-tab"
    >
        <?= h((string)($tab['label'] ?? $key)) ?>
    </button>
    <?php endforeach; ?>
</div>
