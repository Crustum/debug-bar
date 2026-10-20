<?php
/**
 * Exception detail element.
 *
 * Focused single-exception stream: class/message header, Source/Stack/Causes
 * tabs, app frames with context, vendor frames collapsed in disclosures.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $exception Normalized exception
 * @var string $scope Tab scope prefix
 */
use function Cake\Core\h;

$exception = (array)($exception ?? []);
$scope = (string)($scope ?? 'exception-1');
$frames = array_values((array)($exception['frames'] ?? []));
$causes = array_values((array)($exception['causes'] ?? []));
$appFrames = array_values(array_filter($frames, static fn(array $frame): bool => empty($frame['vendor'])));
$vendorFrames = array_values(array_filter($frames, static fn(array $frame): bool => !empty($frame['vendor'])));
?>
<div data-dk-exception>
    <?= $this->element('Crustum/DebugBar.ui/inspector_detail_header', [
        'title' => (string)($exception['class'] ?? 'Exception'),
        'badges' => $this->element('Crustum/DebugBar.ui/inspector_operation_badge', ['operation' => 'Failed']),
        'actions' => $this->element('Crustum/DebugBar.ui/copy_button', [
            'value' => (string)($exception['class'] ?? '') . ': ' . (string)($exception['message'] ?? ''),
            'label' => 'Copy',
        ]),
    ]) ?>
    <p class="dk-query-note"><?= h((string)($exception['message'] ?? '')) ?></p>
    <?= $this->element('Crustum/DebugBar.ui/inspector_detail_tabs', [
        'scope' => $scope,
        'tabs' => [
            ['key' => 'source', 'label' => 'Source'],
            ['key' => 'stack', 'label' => 'Stack'],
            ['key' => 'causes', 'label' => 'Causes'],
        ],
        'active' => 'source',
    ]) ?>
    <div data-dk-tab-panel="<?= h($scope) ?>:source">
        <?= $this->element('Crustum/DebugBar.ui/source_ref', [
            'file' => (string)($exception['file'] ?? ''),
            'line' => isset($exception['line']) ? (int)$exception['line'] : null,
        ]) ?>
    </div>
    <div data-dk-tab-panel="<?= h($scope) ?>:stack" hidden>
        <?php if ($appFrames === [] && $vendorFrames === []) : ?>
        <p class="dk-query-note">No stack frames captured.</p>
        <?php else : ?>
        <ol class="dk-query-stack">
            <?php foreach ($appFrames as $frame) :
                $frame = (array)$frame;
                $context = array_values((array)($frame['context'] ?? []));
                ?>
            <li class="dk-query-stack-frame">
                <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                    'file' => (string)($frame['file'] ?? ''),
                    'line' => isset($frame['line']) ? (int)$frame['line'] : null,
                ]) ?>
                <span class="dk-query-stack-function"><?= h((string)($frame['function'] ?? '')) ?></span>
                <?php if ($context !== []) : ?>
                <div class="dk-evidence">
                    <pre class="dk-code"><?php foreach ($context as $line) :
                        $line = (array)$line; ?><code data-dk-language="php"><?= h(str_pad((string)($line['line'] ?? ''), 4, ' ', STR_PAD_LEFT) . ' ' . (string)($line['code'] ?? '')) ?></code><?= "\n" ?><?php endforeach; ?></pre>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ol>
        <?php if ($vendorFrames !== []) : ?>
        <details class="dk-disclosure">
            <summary class="dk-disclosure-summary">Vendor frames (<?= count($vendorFrames) ?>)</summary>
            <div class="dk-disclosure-body">
                <ol class="dk-query-stack">
                    <?php foreach ($vendorFrames as $frame) :
                        $frame = (array)$frame; ?>
                    <li class="dk-query-stack-frame">
                        <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                            'file' => (string)($frame['file'] ?? ''),
                            'line' => isset($frame['line']) ? (int)$frame['line'] : null,
                        ]) ?>
                        <span class="dk-query-stack-function"><?= h((string)($frame['function'] ?? '')) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
            </div>
        </details>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <div data-dk-tab-panel="<?= h($scope) ?>:causes" hidden>
        <?php if ($causes === []) : ?>
        <p class="dk-query-note">No chained causes.</p>
        <?php else : ?>
        <?php foreach ($causes as $cause) :
            $cause = (array)$cause; ?>
        <div class="dk-validation-entry">
            <div class="dk-detail-header">
                <span class="dk-detail-title dk-code"><?= h((string)($cause['class'] ?? '')) ?></span>
            </div>
            <p class="dk-query-note"><?= h((string)($cause['message'] ?? '')) ?></p>
            <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                'file' => (string)($cause['file'] ?? ''),
                'line' => isset($cause['line']) ? (int)$cause['line'] : null,
            ]) ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
