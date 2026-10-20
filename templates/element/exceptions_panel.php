<?php
/**
 * Exceptions panel element.
 *
 * Single exception renders as a focused stream; several render as a split
 * list with detail. Detail tabs: Source (throw site + context), Stack (app
 * frames open, vendor frames collapsed) and Causes (chained, capped at 5).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $exceptions Normalized exceptions with `class`, `message`,
 *   `code`, `file`, `line`, `frames`, `causes`
 * @var array $summary Counters with `count`
 */
use function Cake\Core\h;

$exceptions = array_values((array)($exceptions ?? []));
?>
<div data-dk-exceptions-panel>
    <?php if ($exceptions === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'No exceptions were captured.',
        'success' => true,
    ]) ?>
    <?php elseif (count($exceptions) === 1) : ?>
    <?= $this->element('Crustum/DebugBar.ui/exception_detail', ['exception' => $exceptions[0], 'scope' => 'exception-1']) ?>
    <?php else : ?>
    <div data-dk-workspace class="dk-workspace" data-dk-inspector-focus-list>
    <div data-dk-workspace-list class="dk-workspace-list">
    <div data-dk-list-panel class="dk-list-panel">
    <div data-dk-list-body class="dk-list-body">
        <?php foreach ($exceptions as $index => $exception) :
            $exception = (array)$exception;
            $key = 'exception-' . ($index + 1);
            ?>
        <button
            type="button"
            data-dk-exception-entry
            data-dk-exception-key="<?= h($key) ?>"
            aria-pressed="<?= $index === 0 ? 'true' : 'false' ?>"
            class="dk-list-row"
        >
            <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', ['operation' => 'Failed']) ?>
            <span class="dk-nav-label"><code class="dk-code"><?= h((string)($exception['class'] ?? '')) ?></code></span>
        </button>
        <?php endforeach; ?>
    </div>
    </div>
    </div>
    <div data-dk-workspace-detail class="dk-workspace-detail">
    <div data-dk-detail-pane class="dk-detail-pane">
        <?php foreach ($exceptions as $index => $exception) : ?>
        <div
            data-dk-exception-detail="exception-<?= $index + 1 ?>"
            <?= $index === 0 ? '' : 'hidden' ?>
        >
            <?= $this->element('Crustum/DebugBar.ui/exception_detail', [
                'exception' => $exception,
                'scope' => 'exception-' . ($index + 1),
            ]) ?>
        </div>
        <?php endforeach; ?>
    </div>
    </div>
    </div>
    <?php endif; ?>
</div>
