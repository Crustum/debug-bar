<?php
/**
 * Debug tree element.
 *
 * Collapsible variable tree (the variables-page idea in house
 * styling): every structured value hides behind a `<details>` toggle with
 * a short summary, scalars render inline, long strings truncate, levels
 * cap at 100 children with an explicit remainder note. Empty strings render
 * a muted dash so blank values stay visible. No JavaScript —
 * disclosure is native and keyboard accessible.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $nodes Plain values (see `Service\NodeFlattener`)
 * @var int $depth Internal nesting depth
 * @var int $openDepth Levels auto-expanded on first paint (default 1)
 */
use function Cake\Core\h;

$nodes = (array)($nodes ?? []);
$depth = (int)($depth ?? 0);
$openDepth = (int)($openDepth ?? 1);
$cap = 100;
$keys = array_keys($nodes);
$count = count($keys);
$shown = array_slice($keys, 0, $cap, true);
$shortClass = static function (string $class): string {
    $class = ltrim($class, '\\');
    $position = strrpos($class, '\\');

    return $position === false ? $class : substr($class, $position + 1);
};
?>
<ul <?= $depth === 0 ? 'data-dk-debug-tree ' : '' ?>class="dk:m-0 dk:min-w-0 dk:list-none dk:p-0<?= $depth === 0 ? '' : ' dk:mt-1 dk:border-l dk:border-zinc-200 dk:pl-3 dk:dark:border-zinc-800' ?>">
    <?php foreach ($shown as $key) :
        $value = $nodes[$key];
        $label = (string)$key;
        $class = null;
        if (is_array($value) && isset($value['__class']) && is_string($value['__class'])) {
            $class = $value['__class'];
            unset($value['__class']);
        }
        $structured = is_array($value) && $value !== [];
        ?>
    <li data-dk-tree-row class="dk:min-w-0 dk:py-0.5">
        <?php if ($structured) :
            $childCount = count($value);
            $summary = $class !== null
                ? $shortClass($class) . ' · ' . $childCount . ($childCount === 1 ? ' field' : ' fields')
                : $childCount . ($childCount === 1 ? ' item' : ' items');
            ?>
        <details data-dk-tree-group class="dk:min-w-0"<?= $depth < $openDepth ? ' open' : '' ?>>
            <summary class="dk:grid dk:min-w-0 dk:cursor-pointer dk:grid-cols-[12rem_minmax(0,1fr)] dk:gap-x-3 dk:rounded dk:text-sm dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500">
                <span class="dk:min-w-0 dk:break-words dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($label) ?></span>
                <span class="dk:min-w-0 dk:truncate dk:text-zinc-400"><?= h($summary) ?></span>
            </summary>
            <?= $this->element('Crustum/DebugBar.ui/debug_tree', ['nodes' => $value, 'depth' => $depth + 1, 'openDepth' => $openDepth]) ?>
        </details>
        <?php else :
            if (is_array($value)) {
                $text = '[]';
            } elseif ($value === null) {
                $text = 'null';
            } elseif (is_bool($value)) {
                $text = $value ? 'true' : 'false';
            } else {
                $text = (string)$value;
            }
            if (strlen($text) > 2000) {
                $text = substr($text, 0, 2000) . '… (truncated)';
            }
            $isEmpty = $text === '';
            ?>
        <div class="dk:grid dk:min-w-0 dk:grid-cols-[12rem_minmax(0,1fr)] dk:gap-x-3 dk:text-sm">
            <span class="dk:min-w-0 dk:break-words dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($label) ?></span>
            <?php if ($isEmpty) : ?>
            <span aria-label="empty value" class="dk:min-w-0 dk:text-zinc-400 dk:dark:text-zinc-500">—</span>
            <?php else : ?>
            <span class="dk:min-w-0 dk:break-words dk:text-zinc-800 dk:dark:text-zinc-200<?= is_numeric($text) ? ' dk:tabular-nums' : '' ?>"><?= h($text) ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
    <?php if ($count > $cap) : ?>
    <li class="dk:min-w-0 dk:py-0.5 dk:text-xs dk:font-semibold dk:text-zinc-400">
        … <?= number_format($count - $cap) ?> more <?= $count - $cap === 1 ? 'item' : 'items' ?>
    </li>
    <?php endif; ?>
</ul>
