<?php
/**
 * Inspector detail empty element.
 *
 * Empty state inside the detail pane (e.g. no record selected yet).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $message Empty-state message
 */
use function Cake\Core\h;
?>
<div data-dk-detail-empty class="dk-detail-empty"><?= h((string)($message ?? 'Nothing selected.')) ?></div>
