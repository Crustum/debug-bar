<?php
/**
 * Inspector navigation element.
 *
 * Two groups — Favorites and Inspectors — mirroring the reference sidebar.
 * The server renders every row inside Inspectors; `renderNavGroups()` in the
 * boot script moves starred rows into Favorites from shell state
 * (localStorage-backed) on boot, star toggle and Shift+Arrow reorder.
 * Counts ride the rows from `RequestFacts::panelCount()`; the request
 * switch refreshes them from the panels payload.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $entries Inspector entries with `key`, `label`, `count`, `attention`
 * @var string $firstKey Initially selected inspector key
 */
use function Cake\Core\h;

$entries = array_values((array)($entries ?? []));
$firstKey = (string)($firstKey ?? 'request');
?>
<div data-dk-inspector-nav-groups class="dk-nav-groups">
    <div role="group" aria-label="Favorites" data-dk-nav-group="favorites" hidden>
        <p data-dk-nav-heading="favorites" class="dk-nav-heading">Favorites</p>
        <ul data-dk-nav-list="favorites" class="dk-scrollbar dk-nav-list"></ul>
    </div>
    <div role="group" aria-label="Inspectors" data-dk-nav-group="inspectors">
        <p data-dk-nav-heading="inspectors" class="dk-nav-heading">Inspectors</p>
        <ul data-dk-nav-list="inspectors" class="dk-scrollbar dk-nav-list">
            <?php foreach ($entries as $entry) : ?>
            <li class="dk-nav-row" data-dk-nav-row="<?= h((string)$entry['key']) ?>">
                <button
                    type="button"
                    data-dk-inspector-nav-item="<?= h((string)$entry['key']) ?>"
                    aria-current="<?= $entry['key'] === $firstKey ? 'page' : 'false' ?>"
                    aria-label="<?= h((string)$entry['label']) ?>"
                    class="dk-nav-item"
                >
                    <span class="dk-nav-label"><?= h((string)$entry['label']) ?></span>
                    <span class="dk-nav-side">
                        <?php if (($entry['count'] ?? null) !== null) : ?>
                        <span data-dk-inspector-count class="dk-nav-count dk-tabular"><?= h((string)$entry['count']) ?></span>
                        <?php endif; ?>
                    </span>
                    <span data-dk-inspector-attention class="dk-nav-attention" <?= empty($entry['attention']) ? 'hidden' : '' ?>></span>
                </button>
                <button
                    type="button"
                    data-dk-inspector-favorite="<?= h((string)$entry['key']) ?>"
                    aria-pressed="false"
                    aria-label="Add <?= h((string)$entry['label']) ?> to favorites"
                    title="Add to favorites"
                    class="dk-nav-favorite"
                >
                    <span class="dk-nav-star-outline"><?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'star', 'size' => '4']) ?></span>
                    <span class="dk-nav-star-filled" hidden><?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'star-filled', 'size' => '4']) ?></span>
                </button>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
