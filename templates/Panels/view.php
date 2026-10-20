<?php
/**
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var \Crustum\DebugBar\Model\Entity\Panel $panel
 */
use function Cake\Core\h;
?>
<h2 class="c-panel__title"><?= h($panel->title) ?></h2>
<div class="c-panel__content">
    <?= $this->element($panel->element) ?>
</div>
