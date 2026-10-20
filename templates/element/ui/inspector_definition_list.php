<?php
/**
 * Inspector definition list element.
 *
 * Key/value rows for detail overviews. Values are caller HTML (evidence,
 * badges, plain text escaped by the caller).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $rows List of `term`, `html` rows
 */
use function Cake\Core\h;

$rows = array_values((array)($rows ?? []));
?>
<dl data-dk-definition-list class="dk-definition-list">
    <?php foreach ($rows as $row) :
        if (!is_array($row)) {
            continue;
        }
        ?>
    <div class="dk-definition-row">
        <dt><?= h((string)($row['term'] ?? '')) ?></dt>
        <dd><?= (string)($row['html'] ?? '') ?></dd>
    </div>
    <?php endforeach; ?>
</dl>
