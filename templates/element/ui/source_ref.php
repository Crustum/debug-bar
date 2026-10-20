<?php
/**
 * Source reference element.
 *
 * Central file:line affordance for every stack frame and source row:
 * an editor anchor when `DebugBar.links` resolves (see
 * `Service\EditorLink`), otherwise the upstream-style copy button.
 * Styling follows the upstream source-link (underlined, truncated).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string|null $file Source file path
 * @var int|null $line Source line number
 * @var string|null $short Display text (defaults to `file:line`)
 * @var string|null $class Extra classes on the control
 * @var string|null $copy Copy value override (defaults to `file:line`)
 * @var string|null $title Title/tooltip override
 * @var bool $wrap Wrap the display text instead of truncating it
 */
use Crustum\DebugBar\Service\EditorLink;
use function Cake\Core\h;

$file = isset($file) ? (string)$file : '';
$line = isset($line) && $line !== '' ? (int)$line : null;
$wrap = (bool)($wrap ?? false);
$label = $file !== '' ? $file . ($line !== null ? ':' . $line : '') : '';
$short = isset($short) ? (string)$short : $label;
$extra = isset($class) ? (string)$class : '';
$copy = isset($copy) ? (string)$copy : $label;
$title = isset($title) ? (string)$title : $label;
$linkClass = 'dk:inline-flex dk:h-auto dk:min-h-0 dk:max-w-full dk:items-center dk:border-0 dk:bg-transparent dk:p-0 dk:text-left dk:text-xs dk:font-semibold dk:text-zinc-700 dk:underline dk:decoration-zinc-300 dk:decoration-1 dk:underline-offset-2 dk:transition-colors dk:hover:bg-transparent dk:hover:text-zinc-950 dk:hover:decoration-zinc-600 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:dark:decoration-zinc-600 dk:dark:hover:bg-transparent dk:dark:hover:text-white dk:dark:hover:decoration-zinc-300'
    . ($extra !== '' ? ' ' . $extra : '');
$url = EditorLink::url($file !== '' ? $file : null, $line);
$textClass = $wrap ? 'dk:min-w-0 dk:break-words' : 'dk:min-w-0 dk:truncate';
?>
<?php if ($label === '') : ?>
<span class="dk:text-xs dk:text-zinc-400">Source unavailable</span>
<?php elseif ($url !== null) : ?>
<a
    href="<?= h($url) ?>"
    title="Open in editor: <?= h($label) ?>"
    aria-label="Open in editor <?= h($label) ?>"
    class="<?= h($linkClass) ?>"
><span class="<?= h($textClass) ?>"><?= h($short) ?></span></a>
<?php else : ?>
<button
    type="button"
    data-dk-copy
    data-dk-copy-value="<?= h($copy) ?>"
    title="<?= h($title) ?>"
    aria-label="Copy source <?= h($label) ?>"
    class="<?= h($linkClass) ?>"
><span class="<?= h($textClass) ?>"><?= h($short) ?></span></button>
<?php endif; ?>
