<?php
/**
 * Inspector workspace element.
 *
 * Shell chrome shared by every inspector: split (list + detail side by side
 * on desktop, swapped with focus move on mobile), focus (list full width,
 * detail as drill-in with a back button) or stream mode, card or top frame.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $mode `split`, `focus` or `stream`
 * @var string $frame `card` or `top`
 * @var string $list List pane HTML (split and focus modes)
 * @var string $detail Detail pane HTML
 */
$mode = (string)($mode ?? 'split');
$frame = (string)($frame ?? 'card');
if (!in_array($mode, ['split', 'focus', 'stream'], true)) {
    $mode = 'split';
}
if (!in_array($frame, ['card', 'top'], true)) {
    $frame = 'card';
}
?>
<div
    data-dk-workspace
    data-dk-workspace-mode="<?= h($mode) ?>"
    data-dk-workspace-frame="<?= h($frame) ?>"
    class="dk-workspace"
>
    <?php if ($mode === 'focus') : ?>
    <div data-dk-inspector-focus-list class="dk-workspace-focus-list">
        <?= $list ?? '' ?>
    </div>
    <div data-dk-inspector-focus-detail class="dk-workspace-focus-detail" tabindex="-1" hidden>
        <?= $detail ?? '' ?>
    </div>
    <?php elseif ($mode === 'split' && ($list ?? '') !== '') : ?>
    <div data-dk-inspector-focus-list class="dk-workspace-list">
        <?= $list ?>
    </div>
    <div data-dk-inspector-focus-detail class="dk-workspace-detail">
        <?= $detail ?? '' ?>
    </div>
    <?php else : ?>
    <div class="dk-workspace-stream">
        <?= $detail ?? '' ?>
    </div>
    <?php endif; ?>
</div>
