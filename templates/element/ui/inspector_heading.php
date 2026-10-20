<?php
/**
 * Inspector heading element.
 *
 * Section label plus description inside inspector bodies. Descriptions come
 * from the inspector registry (no framework branding in copy).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $label Heading label
 * @var string $description Heading description
 */
use function Cake\Core\h;

$label = (string)($label ?? '');
$description = (string)($description ?? '');
?>
<div data-dk-inspector-section-heading>
    <?php if ($label !== '') : ?>
    <h3 class="dk-inspector-heading"><?= h($label) ?></h3>
    <?php endif; ?>
    <?php if ($description !== '') : ?>
    <p class="dk-inspector-description"><?= h($description) ?></p>
    <?php endif; ?>
</div>
