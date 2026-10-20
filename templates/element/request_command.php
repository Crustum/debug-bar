<?php
/**
 * Request command element (CLI context of the request panel).
 *
 * Perfect-UI timeline for console runs: a Received step with the invoked
 * command plus a Copy-command action (the CLI answer to Copy URL), and a
 * Responded step with the exit code, duration and result — no HTTP codes,
 * no Matched block. Arguments and options render as definition facts in
 * the Received step, mirroring the Route/Authentication facts of web
 * rows. Command-specific detail tabs (Arguments, Options, Definition,
 * Environment) replace the web Headers/Input/Query/Session/Cookies/
 * Attributes set, which carries no data outside HTTP. Interactive states
 * are server-rendered (`aria-selected`, `hidden`); tab switching rides
 * the generic `data-dk-tab` handler. Styling rides the shared `dk:`
 * Tailwind utilities.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var string $command Command class
 * @var string $label Short command label
 * @var string $name Routed command name
 * @var array $arguments Paired argument names and values
 * @var array $options Explicitly passed options
 * @var array $definition Full parser definition
 * @var array $environment Launch context (argv, cwd, php)
 * @var string $copyCommand Copyable command line
 * @var int|null $exitCode Exit code, null while running
 * @var float $durationMs Measured duration
 * @var mixed $result Command result, scalar or null
 */
use function Cake\Core\h;

$command = (string)($command ?? '');
$label = (string)($label ?? $command);
$name = (string)($name ?? $label);
$copyCommand = (string)($copy_command ?? '');
if ($copyCommand === '' && $name !== '') {
    $copyCommand = $name;
}

$pairs = [];
foreach ((array)($arguments ?? []) as $item) {
    if (is_array($item) && array_key_exists('value', $item)) {
        $pairs[] = [
            'name' => isset($item['name']) && is_string($item['name']) ? $item['name'] : null,
            'value' => $item['value'],
        ];

        continue;
    }

    $pairs[] = ['name' => null, 'value' => $item];
}

$options = (array)($options ?? []);
$definition = is_array($definition ?? null) ? $definition : ['arguments' => [], 'options' => []];
$environment = is_array($environment ?? null) ? $environment : [];
$exitCode = isset($exit_code) && is_int($exit_code) ? $exit_code : null;
$durationMs = (float)($duration_ms ?? 0);
$running = $exitCode === null;
$failed = $exitCode !== null && $exitCode !== 0;
$succeeded = $exitCode === 0;
$responseTone = $failed ? 'error' : ($succeeded ? 'success' : 'neutral');
$responseToneClass = match ($responseTone) {
    'success' => 'dk:text-emerald-600/80 dk:dark:text-emerald-400/75',
    'error' => 'dk:text-red-600/80 dk:dark:text-red-400/80',
    default => 'dk:text-zinc-500 dk:dark:text-zinc-400',
};
$responseStatusClass = $failed
    ? 'dk:text-red-600 dk:dark:text-red-400'
    : ($succeeded ? 'dk:text-emerald-600 dk:dark:text-emerald-400' : '');
$formatArg = static function (array $pair): string {
    $value = is_scalar($pair['value']) || $pair['value'] === null ? (string)($pair['value'] ?? '') : get_debug_type($pair['value']);

    return $pair['name'] !== null && $pair['name'] !== '' ? $pair['name'] . '=' . $value : $value;
};
$formatOption = static function (string $option, mixed $value): string {
    if ($value === true) {
        return '--' . $option;
    }

    if (is_array($value)) {
        return '--' . $option . '=' . implode(',', array_map('strval', $value));
    }

    return '--' . $option . '=' . (is_scalar($value) || $value === null ? (string)($value ?? '') : get_debug_type($value));
};
$argFacts = array_map($formatArg, $pairs);
$optionFacts = [];
foreach ($options as $option => $value) {
    $optionFacts[] = $formatOption((string)$option, $value);
}
$resultText = '—';
if (isset($result) && is_scalar($result) && trim((string)$result) !== '') {
    $resultText = (string)$result;
}
// Processor outcomes (`ack`, `reject`) headline the run; plain exit
// codes keep the `Exit N` form so command rows read unchanged.
$primaryStatus = $running ? 'Running' : 'Exit ' . $exitCode;
if (!$running && is_string($result ?? null) && trim((string)$result) !== '') {
    $primaryStatus = (string)$result;
}

$scope = 'request';
$stepGrid = 'dk:grid dk:grid-cols-[2rem_minmax(0,1fr)] dk:gap-x-3 dk:lg:grid-cols-[2.5rem_7rem_minmax(0,1fr)] dk:lg:gap-x-5';
$envItems = [];
if ($environment !== []) {
    $envItems = [
        'argv' => $environment['argv'] ?? [],
        'cwd' => $environment['cwd'] ?? null,
        'php' => $environment['php'] ?? null,
    ];
}
$definitionItems = [
    'arguments' => $definition['arguments'] ?? [],
    'options' => $definition['options'] ?? [],
];
$commandDetailGroups = [
    'arguments' => [
        'label' => 'Arguments',
        'empty' => 'No arguments were passed.',
        'items' => $pairs,
    ],
    'options' => [
        'label' => 'Options',
        'empty' => 'No options were passed.',
        'items' => $options,
    ],
    'definition' => [
        'label' => 'Definition',
        'empty' => 'No parser definition was captured.',
        'items' => $definitionItems,
    ],
    'environment' => [
        'label' => 'Environment',
        'empty' => 'No launch context was captured.',
        'items' => $envItems,
    ],
];
?>
<div data-dk-request-panel data-dk-request-context="cli" class="dk:lg:flex dk:lg:min-h-0 dk:lg:flex-1 dk:lg:flex-col">
    <div data-dk-request-trace>
        <ol data-dk-request-steps data-dk-request-timeline class="dk:list-none dk:p-0 dk:sm:px-6" aria-label="Command trace">
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
                        <?= $this->element('Crustum/DebugBar.ui/inspector_operation_badge', ['operation' => 'CLI']) ?>
                        <span data-dk-request-path class="dk:min-w-0 dk:font-semibold dk:[overflow-wrap:anywhere]"><?= h($name !== '' ? $name : $label) ?></span>
                        <?php if ($copyCommand !== '') : ?>
                        <?= $this->element('Crustum/DebugBar.ui/copy_button', ['value' => $copyCommand, 'label' => 'Copy command']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dk:col-start-2 dk:row-start-3 dk:min-w-0 dk:pt-2 dk:lg:col-start-3 dk:lg:row-start-2 dk:lg:pt-1 dk:border-b dk:border-zinc-200/80 dk:pb-6 dk:dark:border-zinc-800/80">
                    <?php if ($command !== '') : ?>
                    <p class="dk:text-sm dk:leading-5 dk:text-zinc-500 dk:[overflow-wrap:anywhere] dk:dark:text-zinc-400"><?= h($command) ?></p>
                    <?php endif; ?>
                    <dl class="dk:mt-4 dk:grid dk:max-w-3xl dk:grid-cols-2 dk:gap-x-5 dk:gap-y-4 dk:text-sm dk:leading-5 dk:lg:grid-cols-3">
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Arguments</dt>
                            <dd class="dk:mt-1 dk:[overflow-wrap:anywhere]"><?= h($argFacts !== [] ? implode(', ', $argFacts) : '—') ?></dd>
                        </div>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Options</dt>
                            <dd class="dk:mt-1 dk:[overflow-wrap:anywhere]"><?= h($optionFacts !== [] ? implode(', ', $optionFacts) : '—') ?></dd>
                        </div>
                    </dl>
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
                        <?= h($primaryStatus) ?>
                    </p>
                </div>
                <div class="dk:col-start-2 dk:row-start-3 dk:min-w-0 dk:pt-2 dk:lg:col-start-3 dk:lg:row-start-2 dk:lg:pt-1">
                    <dl class="dk:mt-2 dk:grid dk:max-w-3xl dk:grid-cols-2 dk:gap-x-5 dk:gap-y-4 dk:text-sm dk:leading-5 dk:lg:grid-cols-3">
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Duration</dt>
                            <dd class="dk:mt-1 dk:tabular-nums"><?= h(number_format($durationMs, 1) . ' ms') ?></dd>
                        </div>
                        <div class="dk:min-w-0">
                            <dt class="dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Result</dt>
                            <dd class="dk:mt-1 dk:[overflow-wrap:anywhere]"><?= h($resultText) ?></dd>
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
                <span class="dk:block dk:text-sm dk:font-semibold">Command details</span>
                <span class="dk:mt-0.5 dk:block dk:text-xs dk:text-zinc-500 dk:dark:text-zinc-400">Arguments, options, parser definition, and launch context</span>
            </span>
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'chevron-down', 'size' => '3.5', 'class' => 'dk:text-zinc-400 dk:transition dk:group-open:rotate-180']) ?>
        </summary>
        <div class="dk:border-t dk:border-zinc-200/90 dk:dark:border-zinc-800">
            <div role="tablist" aria-label="Command detail" data-dk-detail-tabs class="dk:grid dk:grid-cols-1 dk:justify-items-center dk:gap-2 dk:border-b dk:border-zinc-200/90 dk:px-3 dk:py-2.5 dk:sm:px-4 dk:dark:border-zinc-800">
                <div role="presentation" class="dk:flex dk:min-w-0 dk:max-w-full dk:gap-1 dk:overflow-x-auto dk:rounded-lg dk:bg-zinc-100/70 dk:p-1 dk:dark:bg-zinc-900/70">
                    <?php foreach ($commandDetailGroups as $tabKey => $tabGroup) : ?>
                    <button
                        type="button"
                        role="tab"
                        data-dk-request-detail="<?= h($tabKey) ?>"
                        data-dk-tab="<?= h($scope) ?>:<?= h($tabKey) ?>"
                        aria-selected="<?= $tabKey === 'arguments' ? 'true' : 'false' ?>"
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
                <?php foreach ($commandDetailGroups as $tabKey => $tabGroup) :
                    $tabItems = is_array($tabGroup['items']) ? $tabGroup['items'] : [];
                    $tabJson = (string)json_encode($tabItems, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                    ?>
                <div data-dk-tab-panel="<?= h($scope) ?>:<?= h($tabKey) ?>"<?= $tabKey === 'arguments' ? '' : ' hidden' ?>>
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
                        <?php if ($tabItems !== []) : ?>
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
</div>
