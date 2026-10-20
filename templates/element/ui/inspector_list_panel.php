<?php
/**
 * Inspector list panel element.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $controls List controls HTML (search + filters)
 * @var string $body List rows HTML
 */
?>
<div data-dk-list-panel class="dk-list-panel">
    <?php if (($controls ?? '') !== '') : ?>
    <?= $controls ?>
    <?php endif; ?>
    <div data-dk-list-body class="dk-scrollbar dk-list-body">
        <?= $body ?? '' ?>
    </div>
</div>
