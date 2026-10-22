<?php
/**
 * Log panel element.
 *
 * Faithful port of the upstream `logs` inspector (`log-entry` +
 * `log-detail`): visible summary with record/entry counts, search plus a
 * severity select (All/Needs attention/each level), severity-colored rows
 * with message and metadata, and detail (severity header, Level/Records/
 * First seen/Last seen facts). Identical messages within a level collapse
 * into one group with an occurrence count plus first/last timestamps.
 * Interactive states are server-rendered (`aria-pressed`, `hidden`); the boot
 * script in `Requests/view` owns search/filter/selection over the
 * `data-dk-*` hooks. Styling rides the shared `dk:` Tailwind utilities.
 *
 * CakePHP notes (no fake data): the log engine keeps no channel or
 * per-record timing, so the channel select, related exception and source
 * sections stay out until the backend captures them; context renders from
 * the captured record (`VarExporter`, sensitive leaves masked) with the
 * shared `debug_tree`, and wall-clock timestamps render as captured.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var \Crustum\DebugBar\Log\Engine\DebugBarLog $logger
 */
use function Cake\Core\h;

$levels = [];
if (!empty($logger) && method_exists($logger, 'all')) {
    foreach ((array)$logger->all() as $level => $entries) {
        $levels[(string)$level] = array_values((array)$entries);
    }
} elseif (is_array($logger)) {
    foreach ($logger as $level => $entries) {
        $levels[(string)$level] = array_values((array)$entries);
    }
}
$groups = [];
foreach ($levels as $level => $entries) {
    $byMessage = [];
    foreach ($entries as $entry) {
        $entry = (array)$entry;
        $message = (string)($entry[1] ?? '');
        $time = (string)($entry[0] ?? '');
        $context = (array)($entry[3] ?? []);
        if (!isset($byMessage[$message])) {
            $byMessage[$message] = [
                'message' => $message,
                'count' => 0,
                'first' => $time,
                'last' => $time,
                'context' => $context,
                'context_count' => $context !== [] ? 1 : 0,
            ];
        } elseif ($context !== []) {
            $byMessage[$message]['context_count']++;
            if ($byMessage[$message]['context'] === []) {
                $byMessage[$message]['context'] = $context;
            }
        }
        $byMessage[$message]['count']++;
        if ($time !== '') {
            if ($byMessage[$message]['first'] === '' || $time < $byMessage[$message]['first']) {
                $byMessage[$message]['first'] = $time;
            }
            if ($time > $byMessage[$message]['last']) {
                $byMessage[$message]['last'] = $time;
            }
        }
    }
    foreach ($byMessage as $group) {
        $groups[] = ['level' => $level] + $group;
    }
}
$total = 0;
foreach ($levels as $entries) {
    $total += count($entries);
}
$attentionLevels = ['error', 'critical', 'alert', 'emergency', 'warning'];
$attentionCount = 0;
foreach ($levels as $level => $entries) {
    if (in_array(strtolower($level), $attentionLevels, true)) {
        $attentionCount += count($entries);
    }
}
$levelCounts = [];
foreach ($levels as $level => $entries) {
    $levelCounts[strtolower($level)] = count($entries);
}
$severityClass = static function (string $level): string {
    return match (strtolower($level)) {
        'info' => 'dk:text-blue-700 dk:dark:text-blue-300',
        'notice' => 'dk:text-violet-700 dk:dark:text-violet-300',
        'warning' => 'dk:text-amber-700 dk:dark:text-amber-300',
        'error', 'critical', 'alert', 'emergency' => 'dk:text-red-700 dk:dark:text-red-300',
        default => 'dk:text-zinc-500 dk:dark:text-zinc-400',
    };
};
?>
<div data-dk-logs-panel data-dk-active-filter="all" class="dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if ($total === 0) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', [
        'message' => 'There were no log entries made this request',
        'success' => true,
    ]) ?>
    <?php else : ?>
    <?php
    ob_start();
        ?>
    <p
        data-dk-log-visible-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <?= number_format($total) ?> <?= $total === 1 ? 'record' : 'records' ?>
        in
        <?= number_format(count($groups)) ?> <?= count($groups) === 1 ? 'entry' : 'entries' ?>
        <span
            data-dk-visible-count
            hidden
            class="dk:ml-1 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
        ></span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search logs</span>
                <input
                    data-dk-search="logs"
                    type="search"
                    placeholder="Message, level, or time"
                    aria-label="Search logs"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter logs by severity</span>
                <select
                    data-dk-select="logs-level"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <option value="all">All severities (<?= number_format($total) ?>)</option>
                    <?php if ($attentionCount > 0) : ?>
                    <option value="attention">Needs attention (<?= number_format($attentionCount) ?>)</option>
                    <?php endif; ?>
                    <?php foreach ($levelCounts as $level => $levelCount) : ?>
                    <option value="<?= h($level) ?>"><?= h(ucfirst($level)) ?> (<?= number_format($levelCount) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
        ?>
    <div data-dk-logs-list aria-label="Log entries">
        <?php foreach ($groups as $index => $group) :
            $level = strtolower((string)$group['level']);
            $attention = in_array($level, $attentionLevels, true);
            $pressed = $index === 0;
            ?>
        <button
            type="button"
            data-dk-log-entry
            data-dk-log-key="log-<?= $index ?>"
            data-dk-log-level="<?= h($level) ?>"
            data-dk-log-attention="<?= $attention ? 'true' : 'false' ?>"
            data-dk-log-record-count="<?= $group['count'] ?>"
            data-dk-log-first-sequence="<?= $index + 1 ?>"
            data-dk-filter-group="<?= h($level) ?>"
            data-dk-filter-attention="<?= $attention ? 'true' : 'false' ?>"
            data-dk-search-text="<?= h(strtolower($level . ' ' . $group['message'] . ' ' . $group['first'])) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            aria-controls="debugbar-log-detail"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[4.75rem_minmax(0,1fr)] dk:items-baseline dk:gap-x-2.5 dk:border-0 dk:px-3 dk:py-2.5 dk:text-left dk:text-xs dk:text-zinc-900 dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3 dk:dark:text-zinc-100<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span
                data-dk-log-severity
                class="dk:min-w-0 dk:bg-transparent dk:text-xs dk:font-bold dk:uppercase dk:leading-5 dk:tracking-wide <?= h($severityClass($level)) ?>"
            ><?= h($group['level'] !== '' ? $group['level'] : 'log') ?></span>
            <span class="dk:min-w-0">
                <span
                    data-dk-log-message
                    class="dk:block dk:max-h-10 dk:overflow-hidden dk:whitespace-pre-wrap dk:break-words dk:text-xs dk:font-semibold dk:leading-5 dk:[overflow-wrap:anywhere]"
                ><?= h($group['message'] !== '' ? $group['message'] : '—') ?></span>
                <span
                    data-dk-log-metadata
                    class="dk:mt-0.5 dk:flex dk:min-w-0 dk:flex-wrap dk:items-baseline dk:gap-x-3 dk:gap-y-0.5 dk:text-xs dk:leading-4 dk:text-zinc-500 dk:dark:text-zinc-400"
                >
                    <span data-dk-log-request-time class="dk:shrink-0 dk:tabular-nums"><?= h($group['first'] !== '' ? $group['first'] : '—') ?></span>
                    <?php if ($group['count'] > 1) : ?>
                    <span data-dk-log-repeat-label class="dk:shrink-0 dk:font-medium dk:tabular-nums">×<?= $group['count'] ?></span>
                    <?php endif; ?>
                </span>
            </span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No logs match these filters.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
        ?>
    <div data-dk-detail-pane class="dk:min-w-0" id="debugbar-log-detail">
        <div data-dk-detail-empty class="dk:min-w-0 dk:p-3 dk:sm:p-4" hidden>Choose a log entry to inspect its evidence.</div>
        <?php foreach ($groups as $index => $group) :
            $level = strtolower((string)$group['level']);
            ?>
        <div
            data-dk-log-detail="log-<?= $index ?>"
            aria-label="Selected log entry details"
            <?= $index === 0 ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:min-w-0">
                        <h3 class="dk:bg-transparent">
                            <span
                                data-dk-log-details-title
                                class="dk:block dk:whitespace-pre-wrap dk:break-words dk:bg-transparent dk:text-base dk:font-semibold dk:leading-6 dk:text-zinc-900 dk:[overflow-wrap:anywhere] dk:dark:text-zinc-100"
                            ><?= h($group['message'] !== '' ? $group['message'] : '—') ?></span>
                        </h3>
                        <span data-dk-log-detail-severity class="dk:mt-1 dk:block dk:text-xs dk:font-semibold <?= h($severityClass($level)) ?>">
                            <?= h($group['level'] !== '' ? $group['level'] : 'log') ?>
                        </span>
                    </div>
                </header>
                <section data-dk-log-detail-group="summary" class="dk:p-3 dk:sm:p-4">
                    <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2">
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Level</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:font-semibold <?= h($severityClass($level)) ?>"><?= h(ucfirst($group['level'] !== '' ? (string)$group['level'] : 'log')) ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Records</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:font-semibold dk:tabular-nums"><?= $group['count'] ?> <?= $group['count'] === 1 ? 'record' : 'records' ?> in 1 entry</dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">First seen</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:font-semibold dk:tabular-nums"><?= h($group['first'] !== '' ? $group['first'] : '—') ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Last seen</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:font-semibold dk:tabular-nums"><?= h($group['last'] !== '' ? $group['last'] : '—') ?></dd>
                        </div>
                    </dl>
                </section>
                <?php $context = (array)($group['context'] ?? []); ?>
                <?php if ($context !== []) : ?>
                <section data-dk-log-detail-group="context" class="dk:border-t dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <h4 class="dk:mb-2 dk:text-sm dk:font-semibold">Context<?php if ((int)($group['context_count'] ?? 0) > 1) :
                        ?> <span class="dk:text-xs dk:font-normal dk:text-zinc-500">first of <?= (int)$group['context_count'] ?> records with context</span><?php
                                                                           endif; ?></h4>
                    <div data-dk-tree-scope class="dk:min-w-0">
                        <?= $this->element('Crustum/DebugBar.ui/tree_filter', [
                            'label' => 'Search context',
                            'placeholder' => 'Search context keys and values',
                        ]) ?>
                        <?= $this->element('Crustum/DebugBar.ui/debug_tree', [
                            'nodes' => $context,
                        ]) ?>
                    </div>
                </section>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    $detailBody = (string)ob_get_clean();
        ?>
    <?= $this->element('Crustum/DebugBar.ui/inspector_workspace', [
        'mode' => 'split',
        'frame' => 'top',
        'list' => $this->element('Crustum/DebugBar.ui/inspector_list_panel', [
            'controls' => $controls,
            'body' => $listBody,
        ]),
        'detail' => $detailBody,
    ]) ?>
    <?php endif; ?>
</div>
