<?php
/**
 * Inspector stack element.
 *
 * Central numbered application-stack list (upstream `inspector-stack`
 * without heading): index badge, function name and the shared
 * `source_ref` file:line affordance (editor link when `DebugBar.links`
 * resolves, copy button otherwise).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $frames List of `file`, `line`, `function` rows
 */
use function Cake\Core\h;

$frames = array_values((array)($frames ?? []));
?>
<ol class="dk:m-0 dk:list-none dk:divide-y dk:divide-zinc-200/90 dk:p-0 dk:dark:divide-zinc-800">
    <?php foreach ($frames as $frameIndex => $frame) :
        $frame = (array)$frame;
        $frameFile = isset($frame['file']) ? (string)$frame['file'] : '';
        $frameLine = isset($frame['line']) ? (int)$frame['line'] : null;
        $frameFunction = (string)($frame['function'] ?? '');
        ?>
    <li class="dk:flex dk:min-w-0 dk:items-start dk:gap-3 dk:py-2.5 dk:sm:py-3">
        <span class="dk:flex dk:size-6 dk:shrink-0 dk:items-center dk:justify-center dk:rounded-md dk:bg-zinc-100 dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-500 dk:dark:bg-zinc-800 dk:dark:text-zinc-400"><?= $frameIndex + 1 ?></span>
        <span class="dk:min-w-0 dk:flex-1">
            <code class="dk:block dk:truncate dk:font-mono dk:text-xs dk:font-semibold dk:leading-5 dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($frameFunction !== '' ? $frameFunction : 'Application call') ?></code>
            <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                'file' => $frameFile,
                'line' => $frameLine,
            ]) ?>
        </span>
    </li>
    <?php endforeach; ?>
</ol>
