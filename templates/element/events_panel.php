<?php
/**
 * Events panel element.
 *
 * Faithful port of the upstream `events` inspector with dispatch groups:
 * listener activity, source filter defaulting to application events,
 * search across names/listeners/payloads, and detail Overview (origin,
 * sequence, dispatches, listener handling with duplicate-registration
 * flags), Payload (shape-only arguments) and Source (dispatch locations
 * plus dispatch timeline) tabs. Interactive states are server-rendered
 * (`aria-pressed`, `hidden`); the boot script in `Requests/view` owns
 * search/filter/selection over the `data-dk-*` hooks. Styling rides the
 * shared `dk:` Tailwind utilities.
 *
 * CakePHP notes (no fake data): dispatches carry no timing, so First
 * seen is omitted and the timeline lists sequences; listeners run
 * synchronously, so a non-stopped event marks its listeners completed
 * while stopped propagation marks the group partial.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $events Dispatch groups from `EventsPanel::data()`
 * @var array $summary Counters with `count`, `retained_count`,
 *   `application_count`, `framework_count`, `dispatches`
 */
use Crustum\DebugBar\Service\CallsiteCapture;
use function Cake\Core\h;

$events = array_values((array)($events ?? []));
$summary = (array)($summary ?? []);
$recordingOwner = $recording_owner ?? null;
$recordingOwner = is_string($recordingOwner) && $recordingOwner !== '' ? $recordingOwner : null;
$total = (int)($summary['retained_count'] ?? $summary['count'] ?? count($events));
$applicationCount = (int)($summary['application_count'] ?? 0);
$frameworkCount = (int)($summary['framework_count'] ?? 0);
$defaultSource = $applicationCount > 0 ? 'application' : 'all';
$selectedId = 0;
foreach ($events as $event) {
    $event = (array)$event;
    if ($defaultSource === 'all' || (string)($event['source'] ?? '') === $defaultSource) {
        $selectedId = (int)($event['id'] ?? 0);
        break;
    }
}
$segmentedTabClass = 'dk:relative dk:flex dk:min-w-0 dk:items-baseline dk:justify-center dk:gap-1.5 dk:whitespace-nowrap dk:rounded-md dk:border dk:border-transparent dk:bg-transparent dk:px-3 dk:py-1.5 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:transition-[background-color,color,box-shadow] dk:hover:bg-white/60 dk:hover:text-zinc-950 dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:aria-pressed:bg-white dk:aria-pressed:text-zinc-950 dk:aria-pressed:shadow-sm dk:aria-pressed:hover:bg-white dk:aria-pressed:hover:text-zinc-950 dk:aria-selected:bg-white dk:aria-selected:text-zinc-950 dk:aria-selected:shadow-sm dk:aria-selected:hover:bg-white dk:aria-selected:hover:text-zinc-950 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800/70 dk:dark:hover:text-white dk:dark:aria-pressed:bg-zinc-800 dk:dark:aria-pressed:text-white dk:dark:aria-selected:bg-zinc-800 dk:dark:aria-selected:text-white';
$sourceLinkClass = 'dk:inline-flex dk:h-auto dk:min-h-0 dk:max-w-full dk:items-center dk:border-0 dk:bg-transparent dk:p-0 dk:text-left dk:text-xs dk:font-semibold dk:text-zinc-700 dk:underline dk:decoration-zinc-300 dk:decoration-1 dk:underline-offset-2 dk:transition-colors dk:hover:bg-transparent dk:hover:text-zinc-950 dk:hover:decoration-zinc-600 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:dark:decoration-zinc-600 dk:dark:hover:bg-transparent dk:dark:hover:text-white dk:dark:hover:decoration-zinc-300';
?>
<div data-dk-events-panel data-dk-active-filter="<?= h($defaultSource) ?>" class="dk:space-y-4 dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col dk:lg:space-y-0">
    <?php if ($events === [] && $recordingOwner !== null) : ?>
    <div
        data-dk-events-owned
        class="dk:rounded-lg dk:border dk:border-dashed dk:border-zinc-300 dk:bg-zinc-50/60 dk:p-4 dk:text-xs dk:leading-5 dk:text-zinc-600 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/40 dk:dark:text-zinc-300"
    >
        Event recording is owned by <code class="dk:font-mono dk:text-xs dk:font-semibold"><?= h($recordingOwner) ?></code>,
        so this panel stays empty instead of showing a partial stream. See its own UI for dispatched events.
    </div>
    <?php elseif ($events === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No events were dispatched.']) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-events-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <?= number_format($total) ?> <?= $total === 1 ? 'event' : 'events' ?>
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
                <span class="dk:sr-only">Search events</span>
                <input
                    data-dk-search="events"
                    type="search"
                    placeholder="Search events, listeners, or payloads"
                    aria-label="Search events"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter events by source</span>
                <select
                    data-dk-select="events-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <?php foreach (['all' => 'All', 'application' => 'Application', 'framework' => 'Framework'] as $value => $label) : ?>
                    <option
                        value="<?= h($value) ?>"
                        <?= $value === $defaultSource ? 'selected' : '' ?>
                    ><?= h($label) ?> (<?= number_format($value === 'all' ? $total : ($value === 'application' ? $applicationCount : $frameworkCount)) ?>)</option>
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
    <div data-dk-events-list aria-label="CakePHP events">
        <?php foreach ($events as $event) :
            $event = (array)$event;
            $id = (int)($event['id'] ?? 0);
            $key = (string)($event['key'] ?? 'event-' . $id);
            $name = (string)($event['name'] ?? '');
            $display = (string)($event['display_name'] ?? $event['short'] ?? $name);
            $namespace = $event['namespace'] ?? null;
            $source = (string)($event['source'] ?? 'application');
            $occurrences = (int)($event['occurrence_count'] ?? $event['dispatches'] ?? 1);
            $listenerCount = (int)($event['listener_count'] ?? 0);
            $completedCount = (int)($event['completed_listener_count'] ?? 0);
            $queuedCount = (int)($event['queued_listener_count'] ?? 0);
            $duplicateRegistrations = (int)($event['duplicate_registration_count'] ?? $event['duplicate_registrations'] ?? 0);
            if ($listenerCount === 0) {
                $activity = 'No listeners';
            } elseif ($completedCount > 0 && $queuedCount > 0) {
                $activity = number_format($completedCount) . ' completed, ' . number_format($queuedCount) . ' queued';
            } elseif ($queuedCount > 0) {
                $activity = number_format($queuedCount) . ' queued';
            } else {
                $activity = number_format($completedCount) . ' completed';
            }
            $pressed = $id !== 0 && $id === $selectedId;
            $visible = $defaultSource === 'all' || $source === $defaultSource;
            ?>
        <button
            type="button"
            data-dk-event-entry
            data-dk-event-key="<?= h($key) ?>"
            data-dk-event-id="<?= $id ?>"
            data-dk-filter-group="<?= h($source) ?>"
            data-dk-filter-attention="<?= !empty($event['duplicate']) ? 'true' : 'false' ?>"
            data-dk-search-text="<?= h((string)($event['search'] ?? '')) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            <?= $visible ? '' : 'hidden' ?>
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[minmax(0,1fr)_8rem] dk:items-baseline dk:gap-x-3 dk:gap-y-1 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-start-1 dk:row-start-1 dk:min-w-0 dk:truncate dk:text-xs dk:font-bold dk:text-zinc-900 dk:dark:text-zinc-100"><?= h($display) ?></span>
            <span
                class="dk:col-start-2 dk:row-start-1 dk:w-full dk:truncate dk:text-right dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"
                title="<?= h($activity) ?>"
            ><?= h($activity) ?></span>
            <span class="dk:col-start-1 dk:row-start-2 dk:flex dk:min-w-0 dk:items-baseline dk:gap-2 dk:overflow-hidden dk:text-xs dk:text-zinc-400">
                <span class="dk:shrink-0 dk:font-semibold"><?= $source === 'application' ? 'Application' : 'Framework' ?></span>
                <?php if (is_string($namespace) && $namespace !== '') : ?>
                <code class="dk:min-w-0 dk:truncate dk:font-mono dk:text-xs"><?= h($namespace) ?></code>
                <?php endif; ?>
            </span>
            <?php if ($duplicateRegistrations > 0) : ?>
            <span class="dk:col-start-2 dk:row-start-2 dk:w-full dk:truncate dk:text-right dk:text-xs dk:font-semibold dk:text-amber-600 dk:dark:text-amber-300">
                Duplicate registration
            </span>
            <?php else : ?>
            <span class="dk:col-start-2 dk:row-start-2 dk:w-full dk:truncate dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-400">
                <?= number_format($occurrences) ?> <?= $occurrences === 1 ? 'dispatch' : 'dispatches' ?>
            </span>
            <?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No events match this source and search.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden>No event is selected. Adjust the source filter or search.</div>
        <?php foreach ($events as $event) :
            $event = (array)$event;
            $id = (int)($event['id'] ?? 0);
            $key = (string)($event['key'] ?? 'event-' . $id);
            $name = (string)($event['name'] ?? '');
            $display = (string)($event['display_name'] ?? $event['short'] ?? $name);
            $tabScope = $key;
            $source = (string)($event['source'] ?? 'application');
            $origin = $source === 'application' ? 'Application' : 'Framework';
            $occurrences = (int)($event['occurrence_count'] ?? $event['dispatches'] ?? 1);
            $firstSequence = (int)($event['first_sequence'] ?? $event['sequence'] ?? 0);
            $lastSequence = (int)($event['last_sequence'] ?? $firstSequence);
            $sequenceLabel = $firstSequence === $lastSequence ? '#' . $firstSequence : '#' . $firstSequence . '–' . $lastSequence;
            $listeners = array_values((array)($event['listeners'] ?? []));
            $duplicateRegistrations = (int)($event['duplicate_registration_count'] ?? $event['duplicate_registrations'] ?? 0);
            $outcome = (string)($event['listener_outcome_label'] ?? '');
            $listenerSummary = (string)($event['listener_summary'] ?? '');
            $shapes = array_values((array)($event['payload_shape'] ?? $event['data_shapes'] ?? []));
            $dispatchSources = array_values((array)($event['dispatch_sources'] ?? []));
            $dispatchSourceCount = (int)($event['dispatch_source_count'] ?? count($dispatchSources));
            $occurrenceList = array_values((array)($event['occurrences'] ?? []));
            $pressed = $id !== 0 && $id === $selectedId;
            $visible = $defaultSource === 'all' || $source === $defaultSource;
            ?>
        <section
            data-dk-event-detail="<?= h($key) ?>"
            aria-label="Selected CakePHP event details"
            <?= $pressed && $visible ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:grid dk:grid-cols-[minmax(0,1fr)_auto] dk:items-start dk:gap-x-3 dk:gap-y-2">
                        <div class="dk:min-w-0">
                            <h3 class="dk:break-words dk:text-base dk:font-bold dk:leading-6"><?= h($display) ?></h3>
                            <?php if ($name !== $display) : ?>
                            <code title="<?= h($name) ?>" class="dk:mt-1 dk:block dk:break-all dk:bg-transparent dk:font-mono dk:text-xs dk:font-medium dk:leading-4 dk:text-zinc-400"><?= h($name) ?></code>
                            <?php endif; ?>
                        </div>
                    </div>
                </header>
                <div role="tablist" aria-label="CakePHP event detail" data-dk-detail-tabs class="dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800 dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2 dk:sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                    <div role="presentation" class="dk:min-w-0 dk:sm:col-start-2 dk:flex dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                        <?php foreach (['overview' => ['Overview', 'eye'], 'payload' => ['Payload', 'database'], 'source' => ['Source', 'code']] as $tabKey => [$tabLabel, $tabIcon]) : ?>
                        <button
                            type="button"
                            role="tab"
                            aria-selected="<?= $tabKey === 'overview' ? 'true' : 'false' ?>"
                            aria-label="<?= h($tabLabel) ?>"
                            data-dk-tab="<?= h($tabScope . ':' . $tabKey) ?>"
                            class="<?= h($segmentedTabClass) ?> dk:h-auto"
                        >
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => $tabIcon, 'size' => '3.5', 'class' => 'dk:sm:hidden']) ?>
                            <span class="dk:hidden dk:sm:inline"><?= h($tabLabel) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:overview" class="dk:p-3 dk:sm:p-4">
                    <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2 dk:@2xl:grid-cols-4">
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Origin</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold"><?= h($origin) ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Sequence</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold dk:tabular-nums"><?= h($sequenceLabel) ?></dd>
                        </div>
                        <div class="dk:min-w-0 dk:bg-transparent">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Dispatches</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:truncate dk:font-semibold dk:tabular-nums"><?= number_format($occurrences) ?></dd>
                        </div>
                    </dl>
                    <section class="dk:mt-4 dk:sm:mt-6">
                        <div class="dk:flex dk:items-baseline dk:justify-between dk:gap-3">
                            <h4 class="dk:text-xs dk:font-bold">Listener handling</h4>
                            <span class="dk:bg-transparent dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($outcome) ?></span>
                        </div>
                        <p class="dk:mt-1 dk:text-xs dk:leading-5 dk:text-zinc-600 dk:dark:text-zinc-300"><?= h($listenerSummary) ?></p>
                        <?php if ($duplicateRegistrations > 0) : ?>
                        <p class="dk:mt-1 dk:text-xs dk:font-bold dk:text-amber-600 dk:dark:text-amber-300">
                            <?= number_format($duplicateRegistrations) ?> <?= $duplicateRegistrations === 1 ? 'extra registration needs review.' : 'extra registrations need review.' ?>
                        </p>
                        <?php endif; ?>
                        <?php if ($listeners !== []) : ?>
                        <div class="dk:mt-3 dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
                            <?php foreach ($listeners as $listener) :
                                $listener = (array)$listener;
                                $label = (string)($listener['name'] ?? $listener['label'] ?? '');
                                $listenerFile = (string)($listener['file'] ?? '');
                                $listenerLine = $listener['line'] ?? null;
                                $registrations = (int)($listener['registrations'] ?? 1);
                                $queued = !empty($listener['queued']);
                                ?>
                            <div class="dk:grid dk:grid-cols-[minmax(0,1fr)_auto] dk:gap-x-3 dk:gap-y-1.5 dk:bg-transparent dk:px-0 dk:py-3 dk:first:pt-0">
                                <code class="dk:col-start-1 dk:row-start-1 dk:min-w-0 dk:break-all dk:bg-transparent dk:font-mono dk:text-xs dk:font-semibold"><?= h($label) ?></code>
                                <span class="dk:col-start-2 dk:row-start-1 dk:justify-self-end dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"><?= $queued ? 'Queued' : 'Completed' ?></span>
                                <div class="dk:col-start-1 dk:row-start-2 dk:min-w-0 dk:truncate dk:text-xs">
                                    <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                        'file' => $listenerFile,
                                        'line' => $listenerLine,
                                    ]) ?>
                                </div>
                                <span class="dk:col-start-2 dk:row-start-2 dk:justify-self-end dk:text-xs dk:font-semibold dk:tabular-nums<?= $registrations > 1 ? ' dk:text-amber-600 dk:dark:text-amber-300' : ' dk:text-zinc-400' ?>">
                                    <?= number_format($registrations) ?> <?= $registrations === 1 ? 'registration' : 'registrations' ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </section>
                    <details class="dk:group dk:mt-3 dk:border-0 dk:bg-transparent dk:p-0 dk:sm:mt-4">
                        <summary class="dk:flex dk:cursor-pointer dk:list-none dk:items-center dk:gap-1.5 dk:bg-transparent dk:p-0 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-400">
                            How listener outcomes are recorded
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3', 'class' => 'dk:transition dk:group-open:rotate-180']) ?>
                        </summary>
                        <p class="dk:mt-2 dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">
                            Completed means every registered listener ran during synchronous dispatch. CakePHP does
                            not expose per-listener duration to this observer, and stopped propagation may skip
                            later listeners.
                        </p>
                    </details>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:payload" hidden class="dk:p-3 dk:sm:p-4">
                    <h4 class="dk:text-xs dk:font-bold">Payload shape</h4>
                    <?php if ($shapes === []) : ?>
                    <p class="dk:mt-2 dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">
                        No payload arguments were exposed.
                    </p>
                    <?php else : ?>
                    <dl class="dk:mt-2 dk:divide-y dk:divide-zinc-200/90 dk:bg-transparent dk:dark:divide-zinc-800">
                        <?php foreach ($shapes as $entry) :
                            $entry = (array)$entry;
                            $fields = array_values((array)($entry['fields'] ?? []));
                            $fieldCount = (int)($entry['field_count'] ?? count($fields));
                            ?>
                        <div class="dk:grid dk:gap-1 dk:py-2 dk:sm:grid-cols-[8rem_minmax(0,1fr)]">
                            <dt class="dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400">Argument <?= (int)($entry['position'] ?? 0) ?></dt>
                            <dd class="dk:min-w-0 dk:bg-transparent">
                                <code class="dk:block dk:break-all dk:bg-transparent dk:font-mono dk:text-xs dk:font-semibold"><?= h((string)($entry['type'] ?? '')) ?></code>
                                <?php if ($fields !== []) : ?>
                                <div class="dk:mt-2 dk:grid dk:gap-1 dk:sm:grid-cols-[4rem_minmax(0,1fr)]">
                                    <span class="dk:text-xs dk:font-semibold dk:text-zinc-400">Fields</span>
                                    <code class="dk:break-all dk:bg-transparent dk:font-mono dk:text-xs dk:text-zinc-600 dk:dark:text-zinc-300"><?= h(implode(', ', $fields)) ?></code>
                                </div>
                                <?php endif; ?>
                                <?php if ($fieldCount > count($fields)) : ?>
                                <p class="dk:mt-1 dk:text-xs dk:font-semibold dk:text-zinc-400">
                                    <?= number_format($fieldCount - count($fields)) ?> <?= $fieldCount - count($fields) === 1 ? 'more field is not shown.' : 'more fields are not shown.' ?>
                                </p>
                                <?php endif; ?>
                            </dd>
                        </div>
                        <?php endforeach; ?>
                    </dl>
                    <?php endif; ?>
                    <p class="dk:mt-3 dk:text-xs dk:leading-5 dk:text-zinc-400">
                        Field names and types are shown. Payload values are not captured.
                    </p>
                </div>
                <div data-dk-tab-panel="<?= h($tabScope) ?>:source" hidden class="dk:p-3 dk:sm:p-4">
                    <section>
                        <div class="dk:flex dk:items-baseline dk:justify-between dk:gap-3">
                            <h4 class="dk:text-xs dk:font-bold">Dispatch locations</h4>
                            <span class="dk:text-xs dk:font-semibold dk:text-zinc-400">
                                <?= number_format($dispatchSourceCount) ?> <?= $dispatchSourceCount === 1 ? 'application location' : 'application locations' ?>
                            </span>
                        </div>
                        <?php if ($dispatchSources === []) : ?>
                        <p class="dk:mt-2 dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">
                            No application dispatch location was captured. Framework-only stack frames stay hidden.
                        </p>
                        <?php else : ?>
                        <div class="dk:mt-2 dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
                            <?php foreach ($dispatchSources as $dispatchSource) :
                                $dispatchSource = (array)$dispatchSource;
                                $dispatchLabel = (string)($dispatchSource['source'] ?? '');
                                $dispatchCount = (int)($dispatchSource['count'] ?? 0);
                                ?>
                            <div class="dk:grid dk:min-w-0 dk:grid-cols-[minmax(0,1fr)_auto] dk:items-center dk:gap-3 dk:py-2.5">
                                <?php $dispatchParts = CallsiteCapture::split($dispatchLabel); ?>
                                <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                    'file' => $dispatchParts['file'],
                                    'line' => $dispatchParts['line'],
                                ]) ?>
                                <span class="dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-400">
                                    <?= number_format($dispatchCount) ?> <?= $dispatchCount === 1 ? 'dispatch' : 'dispatches' ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </section>
                    <?php if ($occurrences > 1) : ?>
                    <details class="dk:group dk:mt-4 dk:border-0 dk:bg-transparent dk:p-0 dk:sm:mt-6">
                        <summary class="dk:flex dk:cursor-pointer dk:list-none dk:items-center dk:justify-between dk:gap-3 dk:bg-transparent dk:p-0 dk:text-xs dk:font-bold dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500">
                            <span>Dispatch timeline</span>
                            <span class="dk:flex dk:items-center dk:gap-1.5 dk:text-xs dk:font-semibold dk:text-zinc-400">
                                <span><?= number_format(count($occurrenceList)) ?> <?= count($occurrenceList) === 1 ? 'dispatch shown' : 'dispatches shown' ?></span>
                                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3', 'class' => 'dk:transition dk:group-open:rotate-180']) ?>
                            </span>
                        </summary>
                        <div class="dk:mt-3 dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
                            <?php foreach ($occurrenceList as $occurrence) :
                                $occurrence = (array)$occurrence;
                                ?>
                            <div class="dk:grid dk:grid-cols-[auto_minmax(0,1fr)_auto] dk:items-center dk:gap-3 dk:bg-transparent dk:py-2.5">
                                <span class="dk:text-xs dk:font-bold dk:tabular-nums">#<?= (int)($occurrence['sequence'] ?? 0) ?></span>
                                <span></span>
                                <span></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
            </div>
        </section>
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
