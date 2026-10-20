<?php
/**
 * Copy button element.
 *
 * The shell `copy.js` part owns the `Copied` / `Copy failed` feedback that
 * clears after 3 seconds; the boot script wires `[data-dk-copy]` clicks.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $value Text to copy (attribute-escaped)
 * @var string $label Button label (default `Copy`)
 */
use function Cake\Core\h;

$value = (string)($value ?? '');
$label = (string)($label ?? 'Copy');
?>
<button
    type="button"
    data-dk-copy
    data-dk-copy-value="<?= h($value) ?>"
    title="<?= h($label) ?>"
    aria-label="<?= h($label) ?>"
    class="dk-copy-button"
>
    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'copy', 'size' => '4']) ?>
    <span data-dk-copy-label class="dk-copy-label"><?= h($label) ?></span>
</button>
