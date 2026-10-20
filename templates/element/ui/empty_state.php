<?php
/**
 * Empty state element.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $message Message text
 * @var bool $success Emerald check icon when true, zinc code icon otherwise
 */
$success = !empty($success);
?>
<div data-dk-empty-state class="dk-empty-state">
    <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => $success ? 'check' : 'code']) ?>
    <p><?= h((string)($message ?? 'Nothing here yet.')) ?></p>
</div>
