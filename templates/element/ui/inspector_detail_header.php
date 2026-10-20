<?php
/**
 * Inspector detail header element.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $title Detail title
 * @var string $badges Badges HTML (operation badges, pills)
 * @var string $actions Actions HTML (copy buttons, links)
 */
?>
<div data-dk-detail-header class="dk-detail-header">
    <button type="button" data-dk-inspector-focus-back class="dk-detail-back">Back</button>
    <h3 class="dk-detail-title"><?= h((string)($title ?? '')) ?></h3>
    <?php if (($badges ?? '') !== '') : ?>
    <div class="dk-detail-badges"><?= $badges ?></div>
    <?php endif; ?>
    <?php if (($actions ?? '') !== '') : ?>
    <div class="dk-detail-actions"><?= $actions ?></div>
    <?php endif; ?>
</div>
