<?php
/**
 * Inspector operation badge element.
 *
 * Method/type pill: GET emerald, POST sky, DELETE red, FAILED red, SLOW amber.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $operation Operation name, e.g. `GET`, `read`, `failed`
 */
use function Cake\Core\h;

$operation = strtoupper((string)($operation ?? ''));
$tone = match (true) {
    $operation === 'GET' || $operation === 'READ' || $operation === 'HIT' || $operation === 'LOADED' => 'good',
    $operation === 'POST' || $operation === 'WRITE' => 'info',
    str_contains($operation, 'FAIL') || $operation === 'DELETE' || $operation === 'MISS'
        || in_array($operation, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'], true) => 'bad',
    str_contains($operation, 'SLOW') || $operation === 'REPEATED' || $operation === 'WARNING' => 'warn',
    $operation === 'NOTICE' => 'info',
    default => 'neutral',
};
?>
<span data-dk-operation-badge data-dk-operation-tone="<?= h($tone) ?>" class="dk-operation-badge">
    <?= h($operation) ?>
</span>
