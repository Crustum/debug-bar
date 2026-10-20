<?php
/**
 * Select field element.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $scope Select scope, e.g. `views:render`
 * @var array $options List of `value`, `label` rows
 * @var string|null $value Selected value
 * @var string $label Accessible label
 */
use function Cake\Core\h;

$scope = (string)($scope ?? 'select');
$options = array_values((array)($options ?? []));
$value = isset($value) ? (string)$value : null;
?>
<label class="dk-select-field">
    <span class="dk-sr-only"><?= h((string)($label ?? 'Choose')) ?></span>
    <select data-dk-select="<?= h($scope) ?>" class="dk-select">
        <?php foreach ($options as $option) : ?>
        <option
            value="<?= h((string)($option['value'] ?? '')) ?>"
            <?= $value !== null && (string)($option['value'] ?? '') === $value ? 'selected' : '' ?>
        ><?= h((string)($option['label'] ?? $option['value'] ?? '')) ?></option>
        <?php endforeach; ?>
    </select>
</label>
