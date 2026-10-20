<?php
/**
 * Tree filter controls.
 *
 * Generic search-by-key/value plus expand-all on one row for lazily loaded
 * debug trees. The shell filters client-side only: matching rows stay,
 * the rest hide, stored data never changes. Rendered only by the panels
 * whose detail trees load lazily (variables, views).
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $label Accessible label for the search field
 * @var string $placeholder Search field placeholder
 */
use function Cake\Core\h;

$label = (string)($label ?? 'Search keys and values');
$placeholder = (string)($placeholder ?? 'Search keys and values');
?>
<div
    data-dk-tree-controls
    class="dk:mb-2 dk:flex dk:items-start dk:gap-x-2"
>
    <label class="dk:relative dk:block dk:min-w-0 dk:flex-1">
        <span class="dk:sr-only"><?= h($label) ?></span>
        <input
            data-dk-tree-search
            type="search"
            placeholder="<?= h($placeholder) ?>"
            aria-label="<?= h($label) ?>"
            autocomplete="off"
            class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
        />
        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
    </label>
    <button
        type="button"
        data-dk-tree-expand
        title="Expand all levels"
        aria-label="Expand all levels"
        class="dk-icon-button dk:shrink-0"
    >
        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'expand-all', 'size' => '4']) ?>
    </button>
</div>
