<?php
/**
 * Authorization panel element.
 *
 * Faithful port of the upstream `authorization` inspector: decisions
 * list with search and All/Denied/Allowed filter, rows with ability,
 * result, user and argument summary, and a combined detail flow (result +
 * user facts, authorization response, arguments, authorization logic with
 * handler source, checked-from and stack, copy evidence). Interactive states
 * are server-rendered (`aria-pressed`, `hidden`); the boot script in
 * `Requests/view` owns search/filter/selection over the `data-dk-*` hooks.
 * Styling rides the shared `dk:` Tailwind utilities.
 *
 * CakePHP notes (no fake data): result codes and HTTP statuses do not
 * exist in CakePHP checks, so the response section shows the message
 * alone; policy checks resolve `Policy@canAction` with the method source,
 * rule checks render the checked request target as arguments and the
 * matched permission rule as its own JSON block (the vendor
 * `For ... --> Rule matched ...` debug reason duplicates both and stays
 * hidden); the role rides its own fact and the search index.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $decisions Presented decisions from `AuthorizationPanel::data()`
 * @var array $summary Counters with `count`, `allowed`, `denied`
 */
use function Cake\Core\h;

$decisions = array_values((array)($decisions ?? []));
$summary = (array)($summary ?? []);
$total = (int)($summary['count'] ?? count($decisions));
$allowedCount = 0;
$deniedCount = 0;
foreach ($decisions as $decision) {
    $decision = (array)$decision;
    if (($decision['result'] ?? null) === 'allowed') {
        $allowedCount++;
    } else {
        $deniedCount++;
    }
}
$allowedCount = (int)($summary['allowed'] ?? $allowedCount);
$deniedCount = (int)($summary['denied'] ?? $deniedCount);
$selectedKey = '';
foreach ($decisions as $decision) {
    $decision = (array)$decision;
    $selectedKey = (string)($decision['key'] ?? '');
    if ($selectedKey !== '') {
        break;
    }
}
?>
<div data-dk-authorization-panel data-dk-active-filter="all" class="dk:space-y-4 dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col dk:lg:space-y-0">
    <?php if ($decisions === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No authorization decisions were captured.']) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-authorization-summary
        aria-live="polite"
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"
    >
        <span data-dk-authorization-summary-count class="dk:block">
            <?= number_format($total) ?> <?= $total === 1 ? 'decision' : 'decisions' ?>
        </span>
        <span
            data-dk-visible-count
            hidden
            class="dk:mt-0.5 dk:block dk:text-xs dk:font-medium dk:text-zinc-400"
        ></span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search authorization decisions</span>
                <input
                    data-dk-search="authorization"
                    type="search"
                    placeholder="Search ability, user, or arguments"
                    aria-label="Search authorization decisions"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter authorization decisions</span>
                <select
                    data-dk-select="authorization-filter"
                    aria-label="Filter authorization decisions"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <option value="all">All (<?= number_format($total) ?>)</option>
                    <option value="denied">Denied (<?= number_format($deniedCount) ?>)</option>
                    <option value="allowed">Allowed (<?= number_format($allowedCount) ?>)</option>
                </select>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-authorization-list aria-label="Authorization decisions">
        <?php foreach ($decisions as $decision) :
            $decision = (array)$decision;
            $key = (string)($decision['key'] ?? '');
            $ability = (string)($decision['ability'] ?? '');
            $origin = (string)($decision['origin'] ?? '');
            $result = (string)($decision['result'] ?? 'denied');
            $resultLabel = (string)($decision['result_label'] ?? ucfirst($result));
            $userLabel = (string)($decision['user_label'] ?? 'Guest');
            $argumentSummary = (string)($decision['argument_summary'] ?? '—');
            $pressed = $key !== '' && $key === $selectedKey;
            ?>
        <button
            type="button"
            data-dk-authorization-entry
            data-dk-authorization-key="<?= h($key) ?>"
            data-dk-filter-group="<?= h($result) ?>"
            data-dk-filter-attention="<?= $result === 'denied' ? 'true' : 'false' ?>"
            data-dk-search-text="<?= h((string)($decision['search'] ?? '')) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[minmax(0,1fr)_4.75rem] dk:items-start dk:gap-x-3 dk:gap-y-1.5 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:sm:py-3<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:col-span-2 dk:flex dk:min-w-0 dk:items-baseline dk:gap-2">
                <span
                    data-dk-authorization-ability
                    class="dk:min-w-0 dk:break-words dk:text-xs dk:font-bold dk:leading-5"
                ><?= h($ability) ?></span>
                <span
                    data-dk-authorization-result-label
                    class="dk:shrink-0 dk:bg-transparent dk:text-xs dk:font-bold<?= $result === 'allowed' ? ' dk:text-emerald-700 dk:dark:text-emerald-300' : ' dk:text-red-700 dk:dark:text-red-300' ?>"
                ><?= h($resultLabel) ?></span>
                <?php if ($origin !== '') : ?>
                <span
                    data-dk-authorization-origin
                    title="What decided this: a policy check or an Rbac rule"
                    class="dk:shrink-0 dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-400 dk:dark:text-zinc-500"
                ><?= h($origin) ?></span>
                <?php endif; ?>
            </span>
            <span class="dk:col-span-2 dk:grid dk:min-w-0 dk:grid-cols-[4.75rem_minmax(0,1fr)] dk:gap-x-2 dk:gap-y-1 dk:text-xs dk:leading-4">
                <span class="dk:font-semibold dk:text-zinc-400">User</span>
                <span data-dk-authorization-user class="dk:min-w-0 dk:bg-transparent dk:p-0">
                    <span class="dk:block dk:min-w-0 dk:truncate dk:font-semibold dk:text-zinc-600 dk:dark:text-zinc-300"><?= h($userLabel) ?></span>
                </span>
                <span class="dk:font-semibold dk:text-zinc-400">Arguments</span>
                <span data-dk-authorization-arguments class="dk:min-w-0 dk:bg-transparent dk:p-0">
                    <span class="dk:block dk:min-w-0 dk:truncate dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($argumentSummary) ?></span>
                </span>
            </span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No authorization decisions match these filters.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty hidden>No decision is selected. Adjust the filter or search.</div>
        <?php foreach ($decisions as $decision) :
            $decision = (array)$decision;
            $key = (string)($decision['key'] ?? '');
            $ability = (string)($decision['ability'] ?? '');
            $origin = (string)($decision['origin'] ?? '');
            $result = (string)($decision['result'] ?? 'denied');
            $resultLabel = (string)($decision['result_label'] ?? ucfirst($result));
            $resultMessage = $decision['result_message'] ?? null;
            $resultMessage = is_string($resultMessage) && $resultMessage !== '' ? $resultMessage : null;
            $userLabel = (string)($decision['user_label'] ?? 'Guest');
            $userType = $decision['user_type'] ?? null;
            $userType = is_string($userType) && $userType !== '' ? $userType : null;
            $userIdentifierName = $decision['user_identifier_name'] ?? null;
            $userIdentifierName = is_string($userIdentifierName) && $userIdentifierName !== '' ? $userIdentifierName : null;
            $userIdentifier = $decision['user_identifier'] ?? null;
            $userIdentifier = is_scalar($userIdentifier) ? $userIdentifier : null;
            $role = $decision['role'] ?? null;
            $role = is_string($role) && $role !== '' ? $role : null;
            $matchedRulePretty = $decision['matched_rule_pretty'] ?? null;
            $matchedRulePretty = is_string($matchedRulePretty) && $matchedRulePretty !== '' ? $matchedRulePretty : null;
            $arguments = array_values((array)($decision['arguments'] ?? []));
            $handlerKind = (string)($decision['handler_kind'] ?? 'callback');
            $handlerLabel = (string)($decision['handler_label'] ?? 'Authorization logic');
            $handlerAvailable = !empty($decision['handler_available']);
            $handlerName = (string)($decision['handler_name'] ?? '');
            $handlerSourceLabel = $decision['handler_source_label'] ?? null;
            $handlerSourceLabel = is_string($handlerSourceLabel) && $handlerSourceLabel !== '' ? $handlerSourceLabel : null;
            $callsiteLabel = $decision['callsite_label'] ?? null;
            $callsiteLabel = is_string($callsiteLabel) && $callsiteLabel !== '' ? $callsiteLabel : null;
            $stack = array_values((array)($decision['stack'] ?? []));
            $copyEvidence = (string)($decision['copy_evidence'] ?? '{}');
            $pressed = $key !== '' && $key === $selectedKey;
            ?>
        <section
            data-dk-authorization-detail="<?= h($key) ?>"
            aria-label="Selected authorization decision details"
            <?= $pressed ? '' : 'hidden' ?>
        >
            <div class="dk:flex dk:flex-col">
                <header data-dk-authorization-header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                    <div class="dk:grid dk:grid-cols-[minmax(0,1fr)_auto] dk:items-start dk:gap-x-3 dk:gap-y-2">
                        <div class="dk:min-w-0">
                            <h3
                                data-dk-authorization-detail-ability
                                class="dk:min-w-0 dk:break-words dk:text-base dk:font-bold dk:leading-6"
                            ><?= h($ability) ?></h3>
                            <?php if ($origin !== '') : ?>
                            <p class="dk:mt-0.5 dk:text-xs dk:font-medium dk:text-zinc-400 dk:dark:text-zinc-500"><?= h($origin) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="dk:shrink-0">
                            <?= $this->element('Crustum/DebugBar.ui/copy_button', [
                                'value' => $copyEvidence,
                                'label' => 'Copy evidence',
                            ]) ?>
                        </div>
                    </div>
                </header>

                <div data-dk-authorization-detail-panel="combined" class="dk:p-0">
                    <div class="dk:space-y-3 dk:p-3 dk:sm:space-y-5 dk:sm:p-4">
                        <dl data-dk-authorization-metadata class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:grid dk:gap-x-6 dk:gap-y-3 dk:grid-cols-1 dk:@xs:grid-cols-2">
                            <div class="dk:min-w-0 dk:bg-transparent">
                                <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Result</dt>
                                <dd
                                    data-dk-authorization-detail-result
                                    class="dk:min-w-0 dk:truncate dk:bg-transparent dk:text-sm dk:font-bold<?= $result === 'allowed' ? ' dk:text-emerald-700 dk:dark:text-emerald-300' : ' dk:text-red-700 dk:dark:text-red-300' ?>"
                                ><?= h($resultLabel) ?></dd>
                            </div>
                            <div class="dk:min-w-0 dk:bg-transparent" data-dk-authorization-user-detail>
                                <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">User</dt>
                                <dd class="dk:min-w-0 dk:bg-transparent">
                                    <span class="dk:block dk:truncate dk:text-xs dk:font-semibold"><?= h($userLabel) ?></span>
                                    <?php if ($userType !== null) : ?>
                                    <code class="dk:mt-0.5 dk:block dk:truncate dk:bg-transparent dk:font-mono dk:text-xs dk:text-zinc-400"><?= h($userType) ?></code>
                                    <?php endif; ?>
                                    <?php if ($userIdentifier !== null) : ?>
                                    <span class="dk:mt-0.5 dk:flex dk:min-w-0 dk:gap-1.5 dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">
                                        <span><?= h($userIdentifierName ?? 'Identifier') ?></span>
                                        <span class="dk:min-w-0 dk:truncate dk:font-semibold dk:tabular-nums"><?= h((string)$userIdentifier) ?></span>
                                    </span>
                                    <?php endif; ?>
                                </dd>
                            </div>
                            <?php if ($role !== null) : ?>
                            <div class="dk:min-w-0 dk:bg-transparent">
                                <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Role</dt>
                                <dd
                                    data-dk-authorization-detail-role
                                    class="dk:min-w-0 dk:truncate dk:bg-transparent dk:text-sm dk:font-semibold dk:text-zinc-800 dk:dark:text-zinc-200"
                                ><?= h($role) ?></dd>
                            </div>
                            <?php endif; ?>
                        </dl>

                        <?php if ($resultMessage !== null) : ?>
                        <section data-dk-authorization-response class="dk:p-0">
                            <h4 class="dk:text-xs dk:font-bold">Authorization response</h4>
                            <dl class="dk:mt-2 dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0">
                                <div class="dk:min-w-0 dk:bg-transparent dk:grid dk:gap-1 dk:py-2 dk:sm:grid-cols-[8rem_minmax(0,1fr)]">
                                    <dt class="dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400">Message</dt>
                                    <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-xs dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($resultMessage) ?></dd>
                                </div>
                            </dl>
                        </section>
                        <?php endif; ?>

                        <?php if ($arguments !== []) : ?>
                        <section class="dk:p-0">
                            <h4 class="dk:text-xs dk:font-bold">Arguments</h4>
                            <dl
                                data-dk-authorization-arguments-detail
                                class="dk:mt-2 dk:divide-y dk:divide-zinc-200/90 dk:bg-transparent dk:dark:divide-zinc-800"
                            >
                                <?php foreach ($arguments as $argument) :
                                    $argument = (array)$argument;
                                    $roleLabel = (string)($argument['role_label'] ?? '');
                                    $argLabel = (string)($argument['label'] ?? '');
                                    $argType = (string)($argument['type'] ?? '');
                                    $identityLabel = $argument['identity_label'] ?? null;
                                    $identityLabel = is_string($identityLabel) && $identityLabel !== '' ? $identityLabel : null;
                                    ?>
                                <div class="dk:grid dk:gap-1 dk:py-2 dk:sm:grid-cols-[8rem_minmax(0,1fr)]">
                                    <dt class="dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($roleLabel) ?></dt>
                                    <dd class="dk:min-w-0 dk:bg-transparent">
                                        <span class="dk:block dk:break-words dk:text-xs dk:font-semibold dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($argLabel) ?></span>
                                        <code class="dk:mt-0.5 dk:block dk:break-words dk:bg-transparent dk:font-mono dk:text-xs dk:text-zinc-400"><?= h($argType) ?></code>
                                        <?php if ($identityLabel !== null) : ?>
                                        <span class="dk:mt-0.5 dk:block dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($identityLabel) ?></span>
                                        <?php endif; ?>
                                    </dd>
                                </div>
                                <?php endforeach; ?>
                            </dl>
                        </section>
                        <?php endif; ?>

                        <?php if ($matchedRulePretty !== null) : ?>
                        <section class="dk:p-0">
                            <div class="dk:flex dk:items-center dk:justify-between dk:gap-3">
                                <h4 class="dk:text-xs dk:font-bold">Matched rule</h4>
                                <?= $this->element('Crustum/DebugBar.ui/copy_button', [
                                    'value' => $matchedRulePretty,
                                    'label' => 'Copy rule',
                                ]) ?>
                            </div>
                            <div class="dk:mt-2">
                                <?= $this->element('Crustum/DebugBar.ui/inspector_evidence', [
                                    'language' => 'json',
                                    'content' => $matchedRulePretty,
                                ]) ?>
                            </div>
                        </section>
                        <?php endif; ?>
                    </div>

                    <?php if ($handlerAvailable || $handlerSourceLabel !== null || $callsiteLabel !== null || $stack !== []) : ?>
                    <section class="dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800">
                        <div class="dk:space-y-3 dk:p-3 dk:sm:space-y-5 dk:sm:p-4">
                            <h4 class="dk:text-xs dk:font-bold">Authorization logic</h4>
                            <?php if ($handlerAvailable || $handlerSourceLabel !== null) : ?>
                            <div class="dk:grid dk:gap-1 dk:sm:grid-cols-[8rem_minmax(0,1fr)]">
                                <span class="dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($handlerLabel) ?></span>
                                <span class="dk:min-w-0">
                                    <?php if ($handlerAvailable && $handlerName !== '') : ?>
                                    <code class="dk:block dk:min-w-0 dk:break-all dk:bg-transparent dk:font-mono dk:text-xs dk:font-medium dk:leading-5 dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($handlerName) ?></code>
                                    <?php endif; ?>
                                    <?php if ($handlerSourceLabel !== null) : ?>
                                    <span class="dk:mt-1 dk:block">
                                        <?= $this->element('Crustum/DebugBar.ui/source_ref', \Crustum\DebugBar\Service\CallsiteCapture::split($handlerSourceLabel)) ?>
                                    </span>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($stack === [] && $callsiteLabel !== null) : ?>
                            <div class="dk:grid dk:gap-1 dk:sm:grid-cols-[8rem_minmax(0,1fr)]">
                                <span class="dk:text-xs dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400">Checked from</span>
                                <span class="dk:min-w-0">
                                    <?= $this->element('Crustum/DebugBar.ui/source_ref', \Crustum\DebugBar\Service\CallsiteCapture::split($callsiteLabel)) ?>
                                </span>
                            </div>
                            <?php endif; ?>
                            <?php if ($stack !== []) : ?>
                            <?= $this->element('Crustum/DebugBar.ui/inspector_stack', ['frames' => $stack]) ?>
                            <?php endif; ?>
                        </div>
                    </section>
                    <?php endif; ?>

                    <p class="dk:px-3 dk:pb-3 dk:text-xs dk:leading-5 dk:text-zinc-500 dk:sm:px-4 dk:sm:pb-4 dk:dark:text-zinc-400">
                        Policy before hooks can change the final result and are not identified here.
                    </p>
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
