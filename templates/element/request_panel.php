<?php
/**
 * Request panel element.
 *
 * Faithful port of the upstream `request` inspector: Received/Matched/
 * Responded lifecycle steps plus a Request details disclosure with side
 * navigation. Upstream ships Headers|Input|Query|Session; this port adds
 * honest CakePHP extras as their own tabs — Cookies and Attributes — so
 * the Headers tab shows real HTTP request headers instead of framework
 * attributes. Every tab renders the captured debug nodes through the same
 * `Toolbar::dumpNodes()` tree the Variables panel uses (expandable,
 * depth-capped, no truncated one-line JSON). Copy-all JSON stays available
 * per tab. Interactive states are server-rendered (`aria-selected`, `hidden`);
 * the boot script in `Requests/view` owns the detail tabs over the
 * `data-dk-*` hooks. Styling rides the shared `dk:` Tailwind utilities.
 *
 * CakePHP notes (no fake data): request/response sizes are measured from
 * the PSR-7 body streams at shutdown (rendering already ran), and the
 * authentication fact reads the `identity`/`authentication` attributes
 * through the auth plugin interfaces — identifier, display name, class,
 * failure status and messages only, never identity fields. Apps without
 * the auth plugin simply report Guest. Middleware lists the matched
 * route stack plus the application stack resolved like the route map
 * does; unresolvable stacks render their honest fallback. Secret leaves
 * (password/token/secret/credential-like names, any depth, any plugin
 * object) mask as `********` on every tab while entries stay visible;
 * the route `prefix` is stored raw because the core exporter masks every
 * `prefix` key (a database-config guard). Copy-all JSON degrades per tab,
 * masking old rows here; attribute entries mask contents rather than names.
 * Rows stored before HTTP headers
 * were captured show an honest gap note. Missing keys default so old rows
 * stay viewable.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var array $headers Response headers-sent info, not request headers.
 * @var array $attributes Framework request attributes as debug nodes.
 * @var \Cake\Error\Debug\NodeInterface $httpHeaders Real HTTP headers.
 * @var \Cake\Error\Debug\NodeInterface $data
 * @var \Cake\Error\Debug\NodeInterface $query
 * @var \Cake\Error\Debug\NodeInterface $cookie
 * @var \Cake\Error\Debug\NodeInterface $session
 * @var array $routeParams Raw route params (prefix intact).
 * @var string $matchedRoute
 * @var array $params
 * @var array $routeMiddleware
 * @var array $appMiddleware
 * @var array $auth
 * @var int|null $requestSize
 * @var int|null $responseSize
 */
use Cake\Error\Debug\NodeInterface;
use Crustum\DebugBar\Service\NodeFlattener;
use Crustum\DebugBar\Service\RedactionService;
use function Cake\Core\h;

$context = (string)($context ?? 'web');
if ($context === 'cli') {
    echo $this->element('Crustum/DebugBar.request_command', [
        'command' => (string)($command ?? ''),
        'label' => (string)($label ?? ($command ?? '')),
        'name' => (string)($name ?? ($label ?? ($command ?? ''))),
        'exit_code' => isset($exit_code) && is_int($exit_code) ? $exit_code : null,
        'duration_ms' => (float)($duration_ms ?? 0),
        'arguments' => (array)($arguments ?? []),
        'options' => (array)($options ?? []),
        'definition' => (array)($definition ?? []),
        'environment' => (array)($environment ?? []),
        'copy_command' => (string)($copy_command ?? ''),
        'result' => $result ?? null,
    ]);

    return;
}

$params ??= [];
$routeParams = is_array($routeParams ?? null) && $routeParams !== [] ? $routeParams : $params;
$matchedRoute ??= '';
$headers ??= [];
$attributes ??= [];
$routeMiddleware = array_values(array_filter(array_map('strval', (array)($routeMiddleware ?? []))));
$appMiddleware = array_values(array_filter(array_map('strval', (array)($appMiddleware ?? []))));
$auth = (array)($auth ?? []);
$authLabel = (string)($auth['label'] ?? 'Guest');
$authIdentifier = (string)($auth['identifier'] ?? '');
$authTitle = $authIdentifier !== '' && $authIdentifier !== $authLabel ? 'ID: ' . $authIdentifier : '';
$formatBytes = static function ($bytes): string {
    if (!is_int($bytes) || $bytes < 0) {
        return '—';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }

    return number_format($bytes) . ' B';
};

$flatAttributes = NodeFlattener::map(is_array($attributes) ? $attributes : []);
$nodeGroups = [
    'headers' => isset($httpHeaders) && $httpHeaders instanceof NodeInterface ? ['httpHeaders' => $httpHeaders] : null,
    'input' => isset($data) && $data instanceof NodeInterface ? ['data' => $data] : [],
    'query' => isset($query) && $query instanceof NodeInterface ? ['query' => $query] : [],
    'session' => isset($session) && $session instanceof NodeInterface ? ['session' => $session] : [],
    'cookies' => isset($cookie) && $cookie instanceof NodeInterface ? ['cookie' => $cookie] : [],
    'attributes' => $flatAttributes !== [] || $attributes !== [] ? (is_array($attributes) ? $attributes : []) : [],
];
$copyMask = new RedactionService([]);
$maskSensitive = static function (mixed $value) use ($copyMask): mixed {
    if (!is_array($value)) {
        return $value;
    }

    return $copyMask->maskSensitive($value);
};
$flatGroups = [];
foreach (['headers' => null, 'input' => null, 'query' => null, 'session' => null, 'cookies' => null, 'attributes' => null] as $flatKey => $unused) {
    $nodes = $nodeGroups[$flatKey] ?? [];
    if ($nodes === null) {
        $flatGroups[$flatKey] = null;
    } elseif ($flatKey === 'attributes') {
        $maskedAttributes = [];
        foreach ($flatAttributes as $name => $item) {
            $maskedAttributes[$name] = is_array($item) ? $maskSensitive($item) : $item;
        }
        $flatGroups[$flatKey] = $maskedAttributes;
    } else {
        $first = reset($nodes);
        $flat = $first instanceof NodeInterface ? NodeFlattener::toPlain($first) : [];
        $flatGroups[$flatKey] = $maskSensitive(is_array($flat) ? $flat : ['value' => $flat]);
    }
}
$requestDetailGroups = [
    'headers' => [
        'label' => 'Headers',
        'empty' => 'No request headers were captured.',
        'gap' => 'Request headers were not captured for this request (stored before header capture existed).',
        'nodes' => $nodeGroups['headers'],
        'items' => $flatGroups['headers'] ?? [],
    ],
    'input' => [
        'label' => 'Input',
        'empty' => 'No input was captured.',
        'gap' => null,
        'nodes' => $nodeGroups['input'],
        'items' => $flatGroups['input'] ?? [],
    ],
    'query' => [
        'label' => 'Query',
        'empty' => 'No query parameters were captured.',
        'gap' => null,
        'nodes' => $nodeGroups['query'],
        'items' => $flatGroups['query'] ?? [],
    ],
    'session' => [
        'label' => 'Session',
        'empty' => 'No session was captured.',
        'gap' => null,
        'nodes' => $nodeGroups['session'],
        'items' => $flatGroups['session'] ?? [],
    ],
    'cookies' => [
        'label' => 'Cookies',
        'empty' => 'No cookies were captured.',
        'gap' => null,
        'nodes' => $nodeGroups['cookies'],
        'items' => $flatGroups['cookies'] ?? [],
    ],
    'attributes' => [
        'label' => 'Attributes',
        'empty' => 'No request attributes were captured.',
        'gap' => null,
        'nodes' => $nodeGroups['attributes'],
        'items' => $flatGroups['attributes'] ?? [],
    ],
];
$routePath = ($routeParams['controller'] ?? '?') . '::' . ($routeParams['action'] ?? '?');
if (!empty($routeParams['prefix'])) {
    $routePath = $routeParams['prefix'] . '/' . $routePath;
}
if (!empty($routeParams['plugin'])) {
    $routePath = $routeParams['plugin'] . '.' . $routePath;
}
$rawMethod = (string)($requestMethod ?? '');
$rawUrl = (string)($requestUrl ?? '');
$requestMethod = $rawMethod !== '' ? $rawMethod : 'GET';
$requestUrl = $rawUrl;
$requestStatus = (int)($requestStatus ?? 0);
$requestContentType = (string)($requestContentType ?? '');
$statusTexts = [
    200 => 'OK',
    201 => 'Created',
    204 => 'No Content',
    301 => 'Moved Permanently',
    302 => 'Found',
    304 => 'Not Modified',
    400 => 'Bad Request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not Found',
    405 => 'Method Not Allowed',
    422 => 'Unprocessable Entity',
    429 => 'Too Many Requests',
    500 => 'Internal Server Error',
    502 => 'Bad Gateway',
    503 => 'Service Unavailable',
];
$statusText = $statusTexts[$requestStatus] ?? '';
$succeeded = $requestStatus >= 200 && $requestStatus < 300;
$failed = $requestStatus >= 400;
$responseTone = $failed ? 'error' : ($succeeded ? 'success' : 'neutral');
$responseToneClass = match ($responseTone) {
    'success' => 'dk:text-emerald-600/80 dk:dark:text-emerald-400/75',
    'error' => 'dk:text-red-600/80 dk:dark:text-red-400/80',
    default => 'dk:text-zinc-500 dk:dark:text-zinc-400',
};
$responseStatusClass = $failed
    ? 'dk:text-red-600 dk:dark:text-red-400'
    : ($succeeded ? 'dk:text-emerald-600 dk:dark:text-emerald-400' : '');
$urlParts = parse_url($requestUrl);
$requestOrigin = (string)($origin ?? '');
if ($requestOrigin === '' && isset($urlParts['host'])) {
    $requestOrigin = ($urlParts['scheme'] ?? 'http') . '://' . $urlParts['host']
        . (isset($urlParts['port']) ? ':' . $urlParts['port'] : '');
}
$copyUrl = (string)($fullUrl ?? '');
if ($copyUrl === '') {
    $copyUrl = $requestUrl;
}
$scope = 'request';
$isHttp = $rawMethod !== '' || $rawUrl !== '';
$hasRequestFacts = $params !== []
    || $routeParams !== []
    || $flatAttributes !== []
    || (string)($fullUrl ?? '') !== '';
$stepGrid = 'dk:grid dk:grid-cols-[2rem_minmax(0,1fr)] dk:gap-x-3 dk:lg:grid-cols-[2.5rem_7rem_minmax(0,1fr)] dk:lg:gap-x-5';
?>
<div data-dk-request-panel class="dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <?php if (!$isHttp) : ?>
    <div data-dk-request-non-http class="dk:rounded-xl dk:border dk:border-zinc-200 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
        <h3 class="dk:text-xs dk:font-bold">Runtime summary</h3>
        <p class="dk:mt-2 dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">This request was captured outside HTTP (console, worker, or test) — no method, URL, or route to show.</p>
    </div>
    <?php elseif (!$hasRequestFacts) : ?>
    <div data-dk-request-empty class="dk:rounded-xl dk:border dk:border-zinc-200 dk:p-3 dk:sm:p-4 dk:dark:border-zinc-800">
        <h3 class="dk:text-xs dk:font-bold">No request facts</h3>
        <p class="dk:mt-2 dk:text-xs dk:leading-5 dk:text-zinc-500 dk:dark:text-zinc-400">This run stored no request data — console and worker captures record into their own panels.</p>
    </div>
    <?php else : ?>
    <div data-dk-request-trace>
        <ol data-dk-request-steps data-dk-request-timeline class="dk:list-none dk:p-0 dk:sm:px-6" aria-label="Request trace">
            <li data-dk-request-step="received" class="<?= h($stepGrid) ?> dk:pb-8 dk:lg:pb-10">
                <div aria-hidden="true" class="dk:relative dk:col-start-1 dk:row-start-1 dk:row-end-4 dk:lg:row-end-3">
                    <span data-dk-request-line class="dk:absolute dk:top-4 dk:-bottom-12 dk:left-1/2 dk:w-px dk:-translate-x-1/2 dk:bg-zinc-200 dk:lg:top-5 dk:dark:bg-zinc-800"></span>
                    <span data-dk-request-dot class="dk:relative dk:z-[1] dk:grid dk:size-8 dk:place-items-center dk:rounded-full dk:bg-zinc-100 dk:lg:size-10 dk:dark:bg-zinc-900 dk:text-slate-500 dk:dark:text-slate-400">
                        <?= $this->element('Crustum/DebugBar.ui/trace_icon', ['name' => 'received']) ?>
                    </span>
                </div>
                <h3 class="dk:col-start-2 dk:row-start-1 dk:flex dk:h-8 dk:items-center dk:text-sm dk:font-semibold dk:leading-5 dk:lg:h-10 dk:lg:text-base">Received</h3>
                <div data-dk-request-primary class="dk:col-start-2 dk:row-start-2 dk:min-w-0 dk:pt-2 dk:text-base dk:leading-7 dk:lg:col-start-3 dk:lg:row-start-1 dk:lg:py-1.5 dk:lg:text-lg">
                    <div class="dk:flex dk:min-w-0 dk:items-start dk:gap-3">
                        <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', ['operation' => $requestMethod]) ?>
                        <span data-dk-request-path class="dk:min-w-0 dk:font-semibold dk:[overflow-wrap:anywhere]"><?= h($requestUrl !== '' ? $requestUrl : '/') ?></span>
                        <?php if ($requestUrl !== '') : ?>
                        <?= $this->element('Crustum/DebugBar.ui/copy_button', ['value' => $copyUrl, 'label' => 'Copy URL']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dk:col-start-2 dk:row-start-3 dk:min-w-0 dk:pt-2 dk:lg:col-start-3 dk:lg:row-start-2 dk:lg:pt-1 dk:border-b dk:border-zinc-200/80 dk:pb-6 dk:dark:border-zinc-800/80">
                    <?php if ($requestOrigin !== '') : ?>
                    <p class="dk:text-sm dk:leading-5 dk:text-zinc-500 dk:[overflow-wrap:anywhere] dk:dark:text-zinc-400"><?= h($requestOrigin) ?></p>
                    <?php endif; ?>
                </div>
            </li>
            <li data-dk-request-step="matched" class="<?= h($stepGrid) ?> dk:pb-8 dk:lg:pb-10">
                <div aria-hidden="true" class="dk:relative dk:col-start-1 dk:row-start-1 dk:row-end-4 dk:lg:row-end-3">
                    <span data-dk-request-line class="dk:absolute dk:top-4 dk:-bottom-12 dk:left-1/2 dk:w-px dk:-translate-x-1/2 dk:bg-zinc-200 dk:lg:top-5 dk:dark:bg-zinc-800"></span>
                    <span data-dk-request-dot class="dk:relative dk:z-[1] dk:grid dk:size-8 dk:place-items-center dk:rounded-full dk:bg-zinc-100 dk:lg:size-10 dk:dark:bg-zinc-900 dk:text-violet-500/70 dk:dark:text-violet-300/70">
                        <?= $this->element('Crustum/DebugBar.ui/trace_icon', ['name' => 'matched']) ?>
                    </span>
                </div>
                <h3 class="dk:col-start-2 dk:row-start-1 dk:flex dk:h-8 dk:items-center dk:text-sm dk:font-semibold dk:leading-5 dk:lg:h-10 dk:lg:text-base">Matched</h3>
                <div data-dk-request-primary class="dk:col-start-2 dk:row-start-2 dk:min-w-0 dk:pt-2 dk:text-base dk:leading-7 dk:lg:col-start-3 dk:lg:row-start-1 dk:lg:py-1.5 dk:lg:text-lg">
                    <code data-dk-request-controller data-dk-language="php" class="dk:font-medium dk:[overflow-wrap:anywhere]"><?= h($routePath) ?></code>
                </div>
                <div class="dk:col-start-2 dk:row-start-3 dk:min-w-0 dk:pt-2 dk:lg:col-start-3 dk:lg:row-start-2 dk:lg:pt-1 dk:border-b dk:border-zinc-200/80 dk:pb-6 dk:dark:border-zinc-800/80">
                    <dl class="dk:mt-4 dk:grid dk:max-w-3xl dk:grid-cols-2 dk:gap-x-5 dk:gap-y-4 dk:text-sm dk:leading-5 dk:lg:grid-cols-3">
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Route</dt>
                            <dd class="dk:mt-1 dk:[overflow-wrap:anywhere]"><?= h($matchedRoute !== '' ? $matchedRoute : 'Unnamed route') ?></dd>
                        </div>
                        <?php if ($routeMiddleware !== []) : ?>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Middleware</dt>
                            <dd class="dk:mt-1 dk:[overflow-wrap:anywhere]"><?= h(implode(', ', $routeMiddleware)) ?></dd>
                        </div>
                        <?php endif; ?>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Authentication</dt>
                            <dd class="dk:mt-1 dk:[overflow-wrap:anywhere]"<?= $authTitle !== '' ? ' title="' . h($authTitle) . '"' : '' ?>><?= h($authLabel) ?></dd>
                        </div>
                    </dl>
                    <?php if ($appMiddleware !== []) : ?>
                    <details class="dk:group/disclosure dk:min-w-0 dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800">
                        <summary class="dk:flex dk:min-h-11 dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:rounded-md dk:py-3 dk:text-sm dk:font-medium dk:text-zinc-700 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-zinc-200 dk:[&::-webkit-details-marker]:hidden">
                            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:shrink-0 dk:-rotate-90 dk:text-zinc-400 dk:transition-transform dk:group-open/disclosure:rotate-0 dk:motion-reduce:transition-none']) ?>
                            <span class="dk:min-w-0 dk:flex-1">App middleware</span>
                            <span class="dk:shrink-0 dk:text-xs dk:font-normal dk:tabular-nums dk:text-zinc-500 dk:dark:text-zinc-400"><?= number_format(count($appMiddleware)) ?></span>
                        </summary>
                        <div class="dk:min-w-0 dk:pb-4 dk:text-sm">
                            <ol class="dk:m-0 dk:list-none dk:divide-y dk:divide-zinc-200/90 dk:p-0 dk:dark:divide-zinc-800">
                                <?php foreach ($appMiddleware as $middlewareIndex => $middlewareName) : ?>
                                <li class="dk:flex dk:min-w-0 dk:items-start dk:gap-3 dk:py-2">
                                    <span class="dk:flex dk:size-6 dk:shrink-0 dk:items-center dk:justify-center dk:rounded-md dk:bg-zinc-100 dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-500 dk:dark:bg-zinc-800 dk:dark:text-zinc-400"><?= $middlewareIndex + 1 ?></span>
                                    <code class="dk:min-w-0 dk:flex-1 dk:break-all dk:font-mono dk:text-xs dk:leading-5 dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($middlewareName) ?></code>
                                </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
            </li>
            <li data-dk-request-step="responded" class="<?= h($stepGrid) ?>">
                <div aria-hidden="true" class="dk:relative dk:col-start-1 dk:row-start-1 dk:row-end-4 dk:lg:row-end-3">
                    <span data-dk-request-dot class="dk:relative dk:z-[1] dk:grid dk:size-8 dk:place-items-center dk:rounded-full dk:bg-zinc-100 dk:lg:size-10 dk:dark:bg-zinc-900 <?= h($responseToneClass) ?>">
                        <?= $this->element('Crustum/DebugBar.ui/trace_icon', ['name' => $responseTone]) ?>
                    </span>
                </div>
                <h3 class="dk:col-start-2 dk:row-start-1 dk:flex dk:h-8 dk:items-center dk:text-sm dk:font-semibold dk:leading-5 dk:lg:h-10 dk:lg:text-base">Responded</h3>
                <div data-dk-request-primary class="dk:col-start-2 dk:row-start-2 dk:min-w-0 dk:pt-2 dk:text-base dk:leading-7 dk:lg:col-start-3 dk:lg:row-start-1 dk:lg:py-1.5 dk:lg:text-lg">
                    <p data-dk-request-status class="dk:text-xl dk:font-medium dk:leading-7 dk:tabular-nums dk:[overflow-wrap:anywhere] dk:lg:text-2xl <?= h($responseStatusClass) ?>">
                        <?= h(trim(($requestStatus > 0 ? (string)$requestStatus : '—') . ($statusText !== '' ? ' ' . $statusText : ''))) ?>
                    </p>
                </div>
                <div class="dk:col-start-2 dk:row-start-3 dk:min-w-0 dk:pt-2 dk:lg:col-start-3 dk:lg:row-start-2 dk:lg:pt-1">
                    <dl class="dk:mt-2 dk:grid dk:max-w-3xl dk:grid-cols-2 dk:gap-x-5 dk:gap-y-4 dk:text-sm dk:leading-5 dk:lg:grid-cols-3">
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Content type</dt>
                            <dd class="dk:mt-1 dk:[overflow-wrap:anywhere]"><?= h($requestContentType !== '' ? $requestContentType : '—') ?></dd>
                        </div>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Request size</dt>
                            <dd class="dk:mt-1 dk:tabular-nums"><?= h($formatBytes($requestSize ?? null)) ?></dd>
                        </div>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Response size</dt>
                            <dd class="dk:mt-1 dk:tabular-nums"><?= h($formatBytes($responseSize ?? null)) ?></dd>
                        </div>
                    </dl>
                </div>
            </li>
        </ol>
    </div>

    <details
        data-dk-request-details
        class="dk:group dk:mt-4 dk:overflow-hidden dk:rounded-xl dk:border dk:border-zinc-200/90 dk:bg-white/45 dk:sm:mx-6 dk:sm:mt-8 dk:dark:border-zinc-800 dk:dark:bg-zinc-900/25"
    >
        <summary class="dk:flex dk:cursor-pointer dk:list-none dk:items-center dk:gap-3 dk:px-3 dk:py-3 dk:focus-visible:outline-2 dk:focus-visible:outline-inset dk:focus-visible:outline-indigo-500 dk:sm:px-4">
            <span class="dk:min-w-0 dk:flex-1">
                <span class="dk:block dk:text-sm dk:font-semibold">Request details</span>
                <span class="dk:mt-0.5 dk:block dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Headers, input, query parameters, session, cookies, and attributes</span>
            </span>
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:text-zinc-400 dk:transition dk:group-open:rotate-180']) ?>
        </summary>
        <div class="dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800">
            <div role="tablist" aria-label="Request detail" data-dk-detail-tabs class="dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2 dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800">
                <div role="presentation" class="dk:flex dk:min-w-0 dk:max-w-full dk:gap-1 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                    <?php foreach ($requestDetailGroups as $tabKey => $tabGroup) : ?>
                    <button
                        type="button"
                        role="tab"
                        data-dk-request-detail="<?= h($tabKey) ?>"
                        data-dk-tab="<?= h($scope) ?>:<?= h($tabKey) ?>"
                        aria-selected="<?= $tabKey === 'headers' ? 'true' : 'false' ?>"
                        class="dk:relative dk:flex dk:min-w-0 dk:shrink-0 dk:items-baseline dk:justify-center dk:gap-1.5 dk:whitespace-nowrap dk:rounded-md dk:border dk:border-transparent dk:bg-transparent dk:px-3 dk:py-1.5 dk:text-xs dk:font-semibold dk:text-zinc-500 dk:transition-[background-color,color,box-shadow] dk:hover:bg-white/60 dk:hover:text-zinc-950 dk:focus-visible:z-10 dk:focus-visible:outline-2 dk:focus-visible:outline-indigo-500 dk:aria-selected:bg-indigo-50 dk:aria-selected:text-indigo-700 dk:aria-selected:shadow-sm dk:dark:text-zinc-400 dk:dark:hover:bg-zinc-800/70 dk:dark:hover:text-white dk:dark:aria-selected:bg-indigo-950/70 dk:dark:aria-selected:text-indigo-300"
                    >
                        <span><?= h($tabGroup['label']) ?></span>
                        <span
                            data-dk-request-detail-count
                            class="dk:shrink-0 dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-400"
                        ><?= number_format(count(is_array($tabGroup['items']) ? $tabGroup['items'] : [])) ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dk:min-w-0 dk:p-3 dk:sm:p-4">
                <?php foreach ($requestDetailGroups as $tabKey => $tabGroup) :
                    $tabItems = is_array($tabGroup['items']) ? $tabGroup['items'] : [];
                    $tabNodes = is_array($tabGroup['nodes']) ? $tabGroup['nodes'] : [];
                    $tabJson = (string)json_encode($tabItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                    ?>
                <div data-dk-tab-panel="<?= h($scope) ?>:<?= h($tabKey) ?>"<?= $tabKey === 'headers' ? '' : ' hidden' ?>>
                    <div class="dk:flex dk:items-center dk:justify-between dk:gap-3">
                        <div class="dk:flex dk:min-w-0 dk:items-baseline dk:gap-3">
                            <h3 class="dk:text-xs dk:font-bold"><?= h($tabGroup['label']) ?></h3>
                            <span
                                data-dk-request-detail-panel-count
                                class="dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-400"
                            ><?= number_format(count($tabItems)) ?></span>
                        </div>
                        <?php if ($tabItems !== []) : ?>
                        <button
                            type="button"
                            data-dk-copy
                            data-dk-copy-value="<?= h($tabJson) ?>"
                            title="Copy all <?= h(strtolower($tabGroup['label'])) ?> as JSON"
                            aria-label="Copy all <?= h(strtolower($tabGroup['label'])) ?> as JSON"
                            class="dk:shrink-0 dk:text-xs dk:font-bold dk:text-indigo-600 dk:focus-visible:outline-2 dk:focus-visible:outline-offset-2 dk:focus-visible:outline-indigo-500 dk:dark:text-indigo-300"
                        ><span data-dk-copy-label>Copy all</span></button>
                        <?php endif; ?>
                    </div>
                    <div class="dk:mt-3 dk:min-w-0">
                        <?php if ($tabGroup['nodes'] === null) : ?>
                        <p class="dk:rounded-lg dk:bg-zinc-50 dk:px-3 dk:py-3 dk:text-xs dk:text-zinc-500 dk:sm:py-4 dk:dark:bg-zinc-900 dk:dark:text-zinc-400"><?= h((string)$tabGroup['gap']) ?></p>
                        <?php elseif ($tabNodes !== []) : ?>
                        <?= $this->element('Crustum/DebugBar.ui/debug_tree', ['nodes' => $tabItems]) ?>
                        <?php else : ?>
                        <p class="dk:rounded-lg dk:bg-zinc-50 dk:px-3 dk:py-3 dk:text-xs dk:text-zinc-500 dk:sm:py-4 dk:dark:bg-zinc-900 dk:dark:text-zinc-400"><?= h($tabGroup['empty']) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </details>
    <?php endif; ?>
    <?php if (!empty($headers) && ($headers['response'] ?? false)) : ?>
    <div data-dk-banner="headers-sent" role="status" class="dk:sm:mx-6 dk:mt-4 dk:flex dk:items-start dk:gap-2 dk:text-xs dk:font-medium dk:leading-5 dk:text-amber-700 dk:dark:text-amber-300">
        <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'warning', 'size' => '3.5', 'class' => 'dk:mt-px dk:shrink-0']) ?>
        <span>
            Headers already sent at file <?= h((string)($headers['file'] ?? '')) ?> and line <?= (int)($headers['line'] ?? 0) ?>.
        </span>
    </div>
    <?php endif; ?>
</div>
