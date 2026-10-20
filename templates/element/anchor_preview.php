<?php
/**
 * Toolbar anchor previews.
 *
 * Six placement frames shown only while the toolbar is dragged. The shell
 * toggles `hidden` from `toolbarDragging`/`toolbarDragTarget` state; server
 * renders all six hidden so no-JS output stays clean.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 */
use function Cake\Core\h;

$placements = ['top-left', 'top', 'top-right', 'bottom-left', 'bottom', 'bottom-right'];
foreach ($placements as $placement) :
?>
    <div
        data-dk-anchor-preview="<?= h($placement) ?>"
        aria-hidden="true"
        hidden
    ></div>
<?php endforeach; ?>
