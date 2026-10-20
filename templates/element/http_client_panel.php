<?php
/**
 * HTTP client panel element.
 *
 * Faithful port of the upstream `http-client` inspector:
 * summary line, search + All/Failed/Slow select, Method/Request/Status/Time
 * grid with a tri-state Time sort, Response-first detail tabs, inline
 * status facts, bodies with copy actions, header disclosures, source facts
 * with an application-stack disclosure, and the no-response block.
 * Interactive states are server-rendered (`aria-pressed`, `hidden`); the boot
 * script in `Requests/view` owns search/filter/sort/selection over the
 * `data-dk-*` hooks. Styling rides the shared `dk:` Tailwind utilities.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $requests Presented requests from `HttpClientPanel::data()`
 * @var array $summary Counters with `count`, `slow`, `failed`,
 *   `duration_ms`, `total_label`
 */
use function Cake\Core\h;

$requests = array_values((array)($requests ?? []));
$summary = (array)($summary ?? []);
$total = (int)($summary['count'] ?? count($requests));
$slowCount = (int)($summary['slow'] ?? 0);
$failedCount = (int)($summary['failed'] ?? 0);
?>
<div data-dk-http-panel data-dk-active-filter="all">
    <?php if ($requests === []) : ?>
    <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No HTTP client requests were captured.']) ?>
    <?php else : ?>
    <?php
    ob_start();
    ?>
    <p
        data-dk-http-summary
        class="dk:min-w-0 dk:text-xs dk:font-semibold dk:text-zinc-700 dk:dark:text-zinc-200"
    >
        <span data-dk-http-summary-count>
            <?= number_format($total) ?> <?= $total === 1 ? 'request' : 'requests' ?>
        </span>
        <span
            data-dk-http-visible-count
            hidden
            class="dk:ml-1 dk:text-[11px] dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400"
        ></span>
        <span
            data-dk-http-summary-runtime
            class="dk:mt-0.5 dk:flex dk:flex-wrap dk:gap-x-2 dk:text-[11px] dk:font-medium dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"
        >
            <span><?= h((string)($summary['total_label'] ?? '')) ?> total</span>
        </span>
    </p>
    <div
        data-dk-inspector-list-controls
        class="dk:grid dk:items-start dk:gap-x-2 dk:gap-y-3 dk:grid-cols-[minmax(0,1fr)_8.75rem]"
    >
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block dk:min-w-0">
                <span class="dk:sr-only">Search outbound HTTP requests</span>
                <input
                    data-dk-search="http-client"
                    type="search"
                    placeholder="Search requests"
                    aria-label="Search requests"
                    autocomplete="off"
                    class="dk:h-9 dk:w-full dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-3 dk:pl-8 dk:text-xs dk:outline-none dk:transition dk:placeholder:text-zinc-400 dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                />
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'search', 'size' => '4', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:left-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
        <div class="dk:min-w-0">
            <label class="dk:relative dk:block">
                <span class="dk:sr-only">Filter outbound HTTP requests</span>
                <select
                    data-dk-select="http-client-filter"
                    class="dk:h-9 dk:w-full dk:appearance-none dk:rounded-lg dk:border dk:border-zinc-200 dk:bg-white/70 dk:pr-8 dk:pl-3 dk:text-xs dk:font-semibold dk:outline-none dk:transition dk:focus:border-indigo-400 dk:focus:ring-2 dk:focus:ring-indigo-500/15 dk:dark:border-zinc-700 dk:dark:bg-zinc-900/70"
                >
                    <option value="all">All (<?= number_format($total) ?>)</option>
                    <option value="failed">Failed (<?= number_format($failedCount) ?>)</option>
                    <option value="slow">Slow (<?= number_format($slowCount) ?>)</option>
                </select>
                <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:pointer-events-none dk:absolute dk:top-1/2 dk:right-2.5 dk:-translate-y-1/2 dk:text-zinc-400']) ?>
            </label>
        </div>
    </div>
    <?php
    $controls = (string)ob_get_clean();
    ob_start();
    ?>
    <div
        data-dk-http-list-heading
        class="dk:sticky dk:top-0 dk:z-10 dk:grid dk:h-auto dk:grid-cols-[3rem_minmax(0,1fr)_3.5rem_4.75rem] dk:items-center dk:gap-x-2 dk:border-b dk:border-zinc-200/90 dk:bg-white/95 dk:px-3 dk:py-2 dk:text-[11px] dk:font-semibold dk:text-zinc-400 dk:backdrop-blur-sm dk:dark:border-zinc-800 dk:dark:bg-zinc-950/95"
    >
        <span>Method</span>
        <span>Request</span>
        <span class="dk:text-right">Status</span>
        <span class="dk:flex dk:justify-end">
            <button
                type="button"
                aria-label="Sort by time"
                aria-pressed="false"
                data-dk-sort="http-client:duration"
                class="dk:inline-flex dk:min-h-5 dk:items-center dk:justify-end dk:gap-1 dk:transition-colors dk:focus-visible:rounded dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:text-zinc-400 dk:hover:text-zinc-600 dk:dark:hover:text-zinc-300"
            >
                <span aria-hidden="true" data-dk-sort-indicator class="dk:relative dk:size-3 dk:shrink-0">
                    <span data-dk-sort-icon hidden class="dk:absolute dk:inset-0 dk:transition-transform"><?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3']) ?></span>
                </span>
                <span aria-hidden="true">Time</span>
                <span class="dk:sr-only" data-dk-sort-label>Time, not sorted</span>
            </button>
        </span>
    </div>
    <div data-dk-http-list>
        <?php foreach ($requests as $index => $request) :
            $request = (array)$request;
            $key = (string)($request['key'] ?? 'http-' . ($index + 1));
            $method = (string)($request['method'] ?? 'GET');
            $host = (string)($request['host'] ?? '');
            $path = (string)($request['path'] ?? '/');
            $query = (string)($request['query'] ?? '');
            $url = (string)($request['url'] ?? '');
            $failed = !empty($request['failed']);
            $slow = !empty($request['slow']);
            $group = $failed ? 'failed' : ($slow ? 'slow' : 'ok');
            $pressed = $index === 0;
            ?>
        <button
            type="button"
            data-dk-http-entry
            data-dk-http-key="<?= h($key) ?>"
            data-dk-execution="<?= (int)($request['execution'] ?? $index + 1) ?>"
            data-dk-duration="<?= $failed ? -1 : h((string)($request['duration_ms'] ?? 0)) ?>"
            data-dk-filter-group="<?= h($group) ?>"
            data-dk-filter-attention="<?= !empty($request['attention']) ? 'true' : 'false' ?>"
            data-dk-search-text="<?= h((string)($request['search'] ?? '')) ?>"
            title="<?= h($url) ?>"
            aria-pressed="<?= $pressed ? 'true' : 'false' ?>"
            class="dk:grid dk:h-auto dk:w-full dk:grid-cols-[3rem_minmax(0,1fr)_3.5rem_4.75rem] dk:items-center dk:gap-x-2 dk:px-3 dk:py-2.5 dk:text-left dk:transition-colors dk:focus-visible:relative dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500<?= $pressed ? ' dk:bg-indigo-50/65 dk:dark:bg-indigo-950/20' : ' dk:hover:bg-zinc-50/80 dk:dark:hover:bg-zinc-900/60' ?>"
        >
            <span class="dk:box-border dk:flex dk:h-auto dk:shrink-0 dk:items-center dk:justify-center dk:rounded-md dk:bg-zinc-100/70 dk:[font-family:inherit] dk:font-bold dk:uppercase dk:tracking-wide dk:text-zinc-600 dk:dark:bg-white/10 dk:dark:text-zinc-200 dk:px-2 dk:py-0.5 dk:text-xs dk:w-12"><?= h($failed ? 'Failed' : $method) ?></span>
            <span class="dk:min-w-0">
                <span class="dk:block dk:truncate dk:text-xs dk:font-bold dk:text-zinc-800 dk:dark:text-zinc-100"><?= h($host) ?></span>
                <span class="dk:mt-0.5 dk:block dk:truncate dk:text-[11px] dk:text-zinc-500 dk:dark:text-zinc-400"><?= h($path . ($query !== '' ? '?' . $query : '')) ?></span>
            </span>
            <span class="dk:w-full dk:text-right dk:text-[11px] dk:font-bold dk:tabular-nums<?= $failed ? ' dk:text-red-600 dk:dark:text-red-300' : ' dk:text-zinc-500 dk:dark:text-zinc-400' ?>"><?= h((string)($request['list_status_label'] ?? ($failed ? 'Failed' : ''))) ?></span>
            <span class="dk:flex dk:min-w-0 dk:items-center dk:justify-end">
                <span class="dk:whitespace-nowrap dk:text-[11px] dk:font-semibold dk:tabular-nums<?= $slow ? ' dk:text-amber-600 dk:dark:text-amber-300' : ' dk:text-zinc-500 dk:dark:text-zinc-400' ?>"><?= h((string)($request['duration_label'] ?? '')) ?></span>
                <?php if ($slow) : ?>
                <span class="dk:sr-only">Slow request</span>
                <?php endif; ?>
            </span>
        </button>
        <?php endforeach; ?>
    </div>
    <div data-dk-list-empty hidden>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No outbound HTTP requests match these controls.']) ?>
    </div>
    <?php
    $listBody = (string)ob_get_clean();
    ob_start();
    ?>
    <div data-dk-detail-pane class="dk:min-w-0">
        <div data-dk-detail-empty class="dk:min-w-0 dk:p-3 dk:sm:p-4" hidden>Choose a request to inspect its evidence.</div>
        <?php foreach ($requests as $index => $request) :
            $request = (array)$request;
            $key = (string)($request['key'] ?? 'http-' . ($index + 1));
            $method = (string)($request['method'] ?? 'GET');
            $host = (string)($request['host'] ?? '');
            $path = (string)($request['path'] ?? '');
            $query = (string)($request['query'] ?? '');
            $url = (string)($request['url'] ?? '');
            $failed = !empty($request['failed']);
            $slow = !empty($request['slow']);
            $response = is_array($request['response'] ?? null) ? $request['response'] : null;
            $tabScope = $key;
            $requestHeaders = (array)($request['request_headers'] ?? []);
            $requestBodyPretty = (string)($request['request_body_pretty'] ?? '');
            $requestBodyLanguage = (string)($request['request_body_language'] ?? 'http');
            $responseBodyPretty = $response !== null ? (string)($request['response_body_pretty'] ?? '') : '';
            $responseBodyLanguage = (string)($request['response_body_language'] ?? 'http');
            $responseHeaders = $response !== null ? (array)($response['headers'] ?? []) : [];
            $source = (string)($request['source'] ?? '');
            $callsite = $request['callsite_label'] ?? null;
            $sourceFile = null;
            $sourceLine = null;
            if (is_string($callsite) && $callsite !== '') {
                $parts = explode(':', $callsite);
                $maybeLine = array_pop($parts);
                if (is_numeric($maybeLine)) {
                    $sourceLine = (int)$maybeLine;
                    $sourceFile = implode(':', $parts);
                } else {
                    $sourceFile = $callsite;
                }
            }
            $stack = array_values((array)($request['stack'] ?? []));
            $hasSource = !empty($request['has_source']);
            $copyActionClass = 'dk:inline-flex dk:h-auto dk:min-h-9 dk:shrink-0 dk:items-center dk:gap-2 dk:rounded-lg dk:px-2 dk:text-xs dk:font-medium dk:text-zinc-500 dk:transition-colors dk:hover:bg-zinc-100 dk:hover:text-zinc-900 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800 dk:dark:hover:text-zinc-100';
            ?>
        <section
            data-dk-http-detail="<?= h($key) ?>"
            aria-label="Selected outbound HTTP request details"
            <?= $index === 0 ? '' : 'hidden' ?>
        >
            <header class="dk:border-b dk:border-zinc-200/90 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
                <div class="dk:flex dk:min-w-0 dk:flex-wrap dk:items-center dk:justify-between dk:gap-3">
                    <div class="dk:grid dk:min-w-0 dk:flex-1 dk:grid-cols-[3rem_minmax(0,1fr)] dk:items-center dk:gap-x-2 dk:gap-y-1">
                        <span class="dk:box-border dk:flex dk:h-auto dk:shrink-0 dk:items-center dk:justify-center dk:rounded-md dk:bg-zinc-100/70 dk:[font-family:inherit] dk:font-bold dk:uppercase dk:tracking-wide dk:text-zinc-600 dk:dark:bg-white/10 dk:dark:text-zinc-200 dk:px-2 dk:py-0.5 dk:text-xs dk:w-12 dk:ring-1 dk:ring-inset dk:ring-zinc-200/70 dk:dark:ring-zinc-700"><?= h($failed ? 'Failed' : $method) ?></span>
                        <h3 title="<?= h($host) ?>" class="dk:min-w-0 dk:break-all dk:text-sm dk:font-bold dk:leading-5"><?= h($host) ?></h3>
                        <p title="<?= h($url) ?>" class="dk:col-span-2 dk:min-w-0 dk:break-all dk:text-xs dk:leading-5"><?= h($path . ($query !== '' ? '?' . $query : '')) ?></p>
                    </div>
                    <button
                        type="button"
                        data-dk-copy
                        data-dk-copy-value="<?= h($url) ?>"
                        title="Copy URL"
                        aria-label="Copy URL"
                        class="<?= h($copyActionClass) ?> dk:shrink-0"
                    >
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'link', 'size' => '3.5']) ?>
                        <span data-dk-copy-label>Copy URL</span>
                    </button>
                </div>
            </header>
            <div role="tablist" aria-label="Outbound HTTP request detail" data-dk-detail-tabs class="dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800 dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2 dk:sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                <div role="presentation" class="dk:min-w-0 dk:sm:col-start-2 dk:flex dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                    <?php foreach (['response' => 'Response', 'request' => 'Request'] as $tabKey => $tabLabel) : ?>
                    <button
                        type="button"
                        role="tab"
                        aria-selected="<?= $tabKey === 'response' ? 'true' : 'false' ?>"
                        data-dk-tab="<?= h($tabScope . ':' . $tabKey) ?>"
                        class="dk:relative dk:flex dk:min-w-0 dk:items-baseline dk:justify-center dk:gap-1.5 dk:whitespace-nowrap dk:rounded-md dk:border dk:border-transparent dk:bg-transparent dk:px-3 dk:py-1.5 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:transition-[background-color,color,box-shadow] dk:hover:bg-white/60 dk:hover:text-zinc-950 dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:aria-pressed:bg-white dk:aria-pressed:text-zinc-950 dk:aria-pressed:shadow-sm dk:aria-pressed:hover:bg-white dk:aria-pressed:hover:text-zinc-950 dk:aria-selected:bg-white dk:aria-selected:text-zinc-950 dk:aria-selected:shadow-sm dk:aria-selected:hover:bg-white dk:aria-selected:hover:text-zinc-950 dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800/70 dk:dark:hover:text-white dk:dark:aria-pressed:bg-zinc-800 dk:dark:aria-pressed:text-white dk:dark:aria-selected:bg-zinc-800 dk:dark:aria-selected:text-white"
                    ><?= h($tabLabel) ?></button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div data-dk-tab-panel="<?= h($tabScope) ?>:response" class="dk:p-3 dk:sm:p-4">
                <div class="dk:space-y-2">
                    <div class="dk:flex dk:flex-wrap dk:items-center dk:gap-x-4 dk:gap-y-2">
                        <p aria-label="Status" class="dk:break-words dk:text-base dk:font-bold<?= $failed ? ' dk:text-red-700 dk:dark:text-red-300' : '' ?>"><?= h((string)($request['status_label'] ?? '')) ?></p>
                        <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-6 dk:gap-y-2">
                            <div class="dk:min-w-0 dk:bg-transparent dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-2 dk:gap-y-1">
                                <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Duration</dt>
                                <dd title="<?= h((string)($request['timing_summary'] ?? '')) ?>" class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:tabular-nums<?= $slow ? ' dk:text-amber-700 dk:dark:text-amber-300' : '' ?>"><?= h((string)($request['duration_label'] ?? '')) ?></dd>
                            </div>
                            <?php if ((string)($request['content_type'] ?? '') !== '') : ?>
                            <div class="dk:min-w-0 dk:bg-transparent dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-2 dk:gap-y-1">
                                <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Content type</dt>
                                <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:break-all"><?= h((string)$request['content_type']) ?></dd>
                            </div>
                            <?php endif; ?>
                            <?php if ((string)($request['response_body_size_label'] ?? '—') !== '—') : ?>
                            <div class="dk:min-w-0 dk:bg-transparent dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-2 dk:gap-y-1">
                                <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Response size</dt>
                                <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:tabular-nums"><?= h((string)$request['response_body_size_label']) ?></dd>
                            </div>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
                <?php if ($response !== null && ($responseHeaders !== [] || $responseBodyPretty !== '')) : ?>
                <div class="dk:mt-4 dk:space-y-4">
                    <?php if ($responseBodyPretty !== '') : ?>
                    <div>
                        <section class="dk:min-w-0">
                            <div class="dk:mb-2 dk:flex dk:items-center dk:justify-between dk:gap-3">
                                <h4 class="dk:text-sm dk:font-semibold">Response body</h4>
                                <div class="dk:shrink-0">
                                    <button
                                        type="button"
                                        data-dk-copy
                                        data-dk-copy-value="<?= h($responseBodyPretty) ?>"
                                        title="Copy body"
                                        aria-label="Copy body"
                                        class="<?= h($copyActionClass) ?>"
                                    >
                                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'copy', 'size' => '3.5']) ?>
                                        <span data-dk-copy-label>Copy body</span>
                                    </button>
                                </div>
                            </div>
                            <pre class="dk-code dk-scrollbar dk:m-0 dk:min-w-0 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100 dk:p-4 dk:font-mono dk:text-sm dk:leading-6 dk:text-zinc-700 dk:dark:bg-zinc-950 dk:dark:text-zinc-200 dk:whitespace-pre-wrap dk:break-words"><code data-dk-language="<?= h($responseBodyLanguage) ?>"><?= h($responseBodyPretty) ?></code></pre>
                        </section>
                    </div>
                    <?php endif; ?>
                    <?php if ($responseHeaders !== []) : ?>
                    <details class="dk:group/disclosure dk:min-w-0 dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800">
                        <summary class="dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden">
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
                            <span class="dk:min-w-0 dk:flex-1">Response headers</span>
                            <span class="dk:shrink-0 dk:text-xs dk:font-normal dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= number_format(count($responseHeaders)) ?></span>
                        </summary>
                        <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
                            <pre class="dk-code dk-scrollbar dk:m-0 dk:min-w-0 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100 dk:p-4 dk:font-mono dk:text-sm dk:leading-6 dk:text-zinc-700 dk:dark:bg-zinc-950 dk:dark:text-zinc-200 dk:whitespace-pre-wrap dk:break-words"><code data-dk-language="json"><?= h((string)json_encode($responseHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($response === null) : ?>
                <div class="dk:mt-3 dk:sm:mt-5">
                    <p class="dk:text-xs dk:font-semibold">No HTTP response was received.</p>
                </div>
                <?php endif; ?>
            </div>
            <div data-dk-tab-panel="<?= h($tabScope) ?>:request" class="dk:p-3 dk:sm:p-4" hidden>
                <div class="dk:flex dk:flex-wrap dk:items-center dk:justify-between dk:gap-3">
                    <dl class="dk:min-w-0 dk:border-t-0 dk:bg-transparent dk:pt-0 dk:text-zinc-700 dk:dark:text-zinc-200 dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-6 dk:gap-y-2">
                        <?php if ((string)($request['request_body_size_label'] ?? '—') !== '—') : ?>
                        <div class="dk:min-w-0 dk:bg-transparent dk:flex dk:flex-wrap dk:items-baseline dk:gap-x-2 dk:gap-y-1">
                            <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Request size</dt>
                            <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:tabular-nums"><?= h((string)$request['request_body_size_label']) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <button
                        type="button"
                        data-dk-copy
                        data-dk-copy-value="<?= h((string)($request['curl'] ?? '')) ?>"
                        title="Copy safe cURL"
                        aria-label="Copy safe cURL"
                        class="<?= h($copyActionClass) ?>"
                    >
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'code', 'size' => '3.5']) ?>
                        <span data-dk-copy-label>Copy safe cURL</span>
                    </button>
                </div>
                <?php $requestBody = (string)($request['request_body'] ?? ''); ?>
                <?php if ($requestHeaders !== [] || $requestBodyPretty !== '') : ?>
                <div class="dk:mt-4 dk:space-y-4">
                    <?php if ($requestBodyPretty !== '') : ?>
                    <div>
                        <section class="dk:min-w-0">
                            <div class="dk:mb-2 dk:flex dk:items-center dk:justify-between dk:gap-3">
                                <h4 class="dk:text-sm dk:font-semibold">Request body</h4>
                                <div class="dk:shrink-0">
                                    <button
                                        type="button"
                                        data-dk-copy
                                        data-dk-copy-value="<?= h($requestBodyPretty) ?>"
                                        title="Copy body"
                                        aria-label="Copy body"
                                        class="<?= h($copyActionClass) ?>"
                                    >
                                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'copy', 'size' => '3.5']) ?>
                                        <span data-dk-copy-label>Copy body</span>
                                    </button>
                                </div>
                            </div>
                            <pre class="dk-code dk-scrollbar dk:m-0 dk:min-w-0 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100 dk:p-4 dk:font-mono dk:text-sm dk:leading-6 dk:text-zinc-700 dk:dark:bg-zinc-950 dk:dark:text-zinc-200 dk:whitespace-pre-wrap dk:break-words"><code data-dk-language="<?= h($requestBodyLanguage) ?>"><?= h($requestBodyPretty) ?></code></pre>
                        </section>
                    </div>
                    <?php endif; ?>
                    <?php if ($requestHeaders !== []) : ?>
                    <details class="dk:group/disclosure dk:min-w-0 dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800">
                        <summary class="dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden">
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
                            <span class="dk:min-w-0 dk:flex-1">Request headers</span>
                            <span class="dk:shrink-0 dk:text-xs dk:font-normal dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= number_format(count($requestHeaders)) ?></span>
                        </summary>
                        <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
                            <pre class="dk-code dk-scrollbar dk:m-0 dk:min-w-0 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100 dk:p-4 dk:font-mono dk:text-sm dk:leading-6 dk:text-zinc-700 dk:dark:bg-zinc-950 dk:dark:text-zinc-200 dk:whitespace-pre-wrap dk:break-words"><code data-dk-language="json"><?= h((string)json_encode($requestHeaders, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?></code></pre>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if ($hasSource) : ?>
            <section class="dk:p-3 dk:sm:p-4">
                <dl class="dk:grid dk:min-w-0 dk:gap-x-6 dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800 dk:grid-cols-1">
                    <?php if ($callsite !== null && $callsite !== '') : ?>
                    <div class="dk:min-w-0 dk:bg-transparent dk:py-2">
                        <dt class="dk:bg-transparent dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Source</dt>
                        <dd class="dk:min-w-0 dk:break-words dk:bg-transparent dk:text-sm dk:text-zinc-800 dk:dark:text-zinc-200 dk:mt-1">
                            <?= $this->element('Crustum/DebugBar.ui/source_ref', [
                                'file' => $sourceFile,
                                'line' => $sourceLine,
                            ]) ?>
                        </dd>
                    </div>
                    <?php endif; ?>
                </dl>
                <?php if ($stack !== []) : ?>
                <details class="dk:group/disclosure dk:min-w-0 dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800">
                    <summary class="dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden">
                        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
                        <span class="dk:min-w-0 dk:flex-1">Application stack</span>
                        <span class="dk:shrink-0 dk:text-xs dk:font-normal dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= number_format(count($stack)) ?> <?= count($stack) === 1 ? 'frame' : 'frames' ?></span>
                    </summary>
                    <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
                        <?= $this->element('Crustum/DebugBar.ui/inspector_stack', ['frames' => $stack]) ?>
                    </div>
                </details>
                <?php endif; ?>
            </section>
            <?php endif; ?>
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
