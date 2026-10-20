<?php
/**
 * Timer panel element.
 *
 * Kit rewrite of the legacy `c-timer-panel`: summary facts (peak memory,
 * total request time) up top, memory checkpoints and event timers as kit
 * tables. The timeline ranges stay exactly as they were — `SimpleGraph::bar`
 * output is untouched, only the surrounding markup changed. `Render File:`
 * timers keep the `source_ref` affordance; missing keys still default so
 * rows stored before collection stay viewable.
 *
 * @var \Crustum\DebugBar\View\AjaxView $this
 * @var float $requestTime
 * @var int $peakMemory
 * @var array $memory
 * @var array $timers
 */
use function Cake\Core\h;

$requestTime ??= 0;
$peakMemory ??= 0;
$memory ??= [];
$timers ??= [];
$renderFilePrefix = 'Render File: ';
$rootPrefix = defined('ROOT') ? (string)ROOT : '';
$displayPath = static function (string $path) use ($rootPrefix): string {
    if ($rootPrefix !== '' && str_starts_with($path, $rootPrefix)) {
        $path = ltrim(substr($path, strlen($rootPrefix)), '/\\');
    }

    return str_replace('\\', '/', $path);
};
$timerRows = [];
$end = end($timers);
$maxTime = $end ? $end['end'] : 0;
$values = array_values($timers);
$i = 0;
foreach ($timers as $timerName => $timeInfo) {
    $indent = 0;
    for ($j = 0; $j < $i; $j++) {
        if (($values[$j]['end'] > $timeInfo['start']) && ($values[$j]['end']) > $timeInfo['end']) {
            $indent++;
        }
    }
    $timerRows[] = [
        'indent' => $indent,
        'message' => (string)($timeInfo['message'] ?? ''),
        'time' => (float)($timeInfo['time'] ?? 0),
        'start' => (float)($timeInfo['start'] ?? 0),
    ];
    $i++;
}
?>
<div data-dk-timer-panel class="dk:text-zinc-950 dk:dark:text-white">
    <dl class="dk:grid dk:min-w-0 dk:grid-cols-2 dk:gap-x-6 dk:gap-y-2">
        <div class="dk:flex dk:min-w-0 dk:items-center dk:gap-2">
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'memory', 'size' => '4', 'class' => 'dk:shrink-0 dk:text-zinc-400']) ?>
            <dt class="dk:shrink-0 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Peak Memory Use</dt>
            <dd class="dk:min-w-0 dk:truncate dk:text-sm dk:font-bold dk:tabular-nums dk:text-zinc-900 dk:dark:text-zinc-100"><?= h($this->Number->toReadableSize($peakMemory)) ?></dd>
        </div>
        <div class="dk:flex dk:min-w-0 dk:items-center dk:gap-2">
            <?= $this->element('Crustum/DebugBar.ui/icon', ['name' => 'clock', 'size' => '4', 'class' => 'dk:shrink-0 dk:text-zinc-400']) ?>
            <dt class="dk:shrink-0 dk:text-xs dk:font-medium dk:text-zinc-500 dk:dark:text-zinc-400">Total Request Time</dt>
            <dd class="dk:min-w-0 dk:truncate dk:text-sm dk:font-bold dk:tabular-nums dk:text-zinc-900 dk:dark:text-zinc-100"><?= h((string)$this->Number->precision($requestTime * 1000, 0)) ?> ms</dd>
        </div>
    </dl>
    <section aria-label="Memory" class="dk:mt-4 dk:min-w-0">
        <h3 class="dk:text-xs dk:font-bold dk:uppercase dk:tracking-wider dk:text-zinc-400 dk:dark:text-zinc-500">Memory</h3>
        <table class="dk:mt-2 dk:w-full dk:min-w-0 dk:border-collapse dk:text-left dk:text-xs">
            <thead>
                <tr class="dk:border-b dk:border-zinc-200 dk:dark:border-zinc-800">
                    <th scope="col" class="dk:py-1.5 dk:pr-3 dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400">Message</th>
                    <th scope="col" class="dk:py-1.5 dk:text-right dk:font-semibold dk:text-zinc-500 dk:dark:text-zinc-400">Memory Use</th>
                </tr>
            </thead>
            <tbody class="dk:divide-y dk:divide-zinc-200/90 dk:dark:divide-zinc-800">
                <?php foreach ($memory as $key => $value) : ?>
                <tr>
                    <td class="dk:min-w-0 dk:break-words dk:py-1.5 dk:pr-3 dk:text-zinc-700 dk:dark:text-zinc-200" title="<?= h((string)$key) ?>"><?= h($displayPath((string)$key)) ?></td>
                    <td class="dk:whitespace-nowrap dk:py-1.5 dk:text-right dk:tabular-nums dk:text-zinc-700 dk:dark:text-zinc-200"><?= h($this->Number->toReadableSize($value)) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <section aria-label="Timers" class="dk:mt-4 dk:min-w-0">
        <h3 class="dk:text-xs dk:font-bold dk:uppercase dk:tracking-wider dk:text-zinc-400 dk:dark:text-zinc-500">Timers</h3>
        <?php if ($timerRows === []) : ?>
        <?= $this->element('Crustum/DebugBar.ui/empty_state', ['message' => 'No timers were captured.']) ?>
        <?php else : ?>
        <div aria-hidden="true" class="dk:mt-2 dk:hidden dk:grid-cols-[minmax(20rem,42fr)_minmax(20rem,43fr)_minmax(5rem,15fr)] dk:border-b dk:border-zinc-200/90 dk:text-xs dk:font-semibold dk:uppercase dk:tracking-wider dk:text-zinc-400 dk:dark:border-zinc-800 dk:lg:grid">
            <span class="dk:px-3 dk:py-2">Event</span>
            <span class="dk:border-x dk:border-zinc-200/90 dk:px-3 dk:py-2 dk:dark:border-zinc-800">Timeline</span>
            <span class="dk:px-3 dk:py-2 dk:text-right">Time in ms</span>
        </div>
        <div class="dk:divide-y dk:divide-zinc-200/80 dk:dark:divide-zinc-800">
            <?php foreach ($timerRows as $row) : ?>
            <div class="dk:grid dk:min-w-0 dk:grid-cols-[minmax(0,1fr)_auto] dk:items-center dk:gap-x-3 dk:py-2.5 dk:lg:grid-cols-[minmax(20rem,42fr)_minmax(20rem,43fr)_minmax(5rem,15fr)] dk:lg:gap-x-0 dk:lg:py-0">
                <span class="dk:min-w-0 dk:break-words dk:text-xs dk:text-zinc-700 dk:dark:text-zinc-200 dk:lg:px-3 dk:lg:py-2.5" title="<?= h($row['message']) ?>"><span style="display:block;padding-left:<?= (int)$row['indent'] ?>rem"><?php
                    if (str_starts_with($row['message'], $renderFilePrefix)) :
                        $renderFile = trim(substr($row['message'], strlen($renderFilePrefix)));
                        echo $this->element('Crustum/DebugBar.ui/source_ref', [
                            'file' => $renderFile,
                            'short' => $renderFilePrefix . $displayPath($renderFile),
                            'wrap' => true,
                        ]);
                    else :
                        echo h($row['message']);
                    endif;
                    ?></span></span>
                <span class="dk:relative dk:hidden dk:min-w-0 dk:border-x dk:border-zinc-200/90 dk:dark:border-zinc-800 dk:lg:block dk:lg:px-3 dk:lg:py-2.5">
                    <?= $this->SimpleGraph->bar(
                        $row['time'] * 1000,
                        $row['start'] * 1000,
                        [
                            'max' => $maxTime * 1000,
                            'requestTime' => $requestTime * 1000,
                        ]
                    ) ?>
                </span>
                <span class="dk:whitespace-nowrap dk:text-right dk:text-xs dk:font-semibold dk:tabular-nums dk:text-zinc-600 dk:dark:text-zinc-300 dk:lg:px-3 dk:lg:py-2.5"><?= h((string)$this->Number->precision($row['time'] * 1000, 2)) ?> ms</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
