<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Log\Log;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Log\Engine\DebugBarLog;

/**
 * Log Panel - Reads log entries made this request.
 *
 * @ported-from \DebugKit\Panel\LogPanel
 */
class LogPanel extends DebugPanel implements TimelineSourceInterface
{
    /**
     * Initialize hook - sets up the log listener.
     *
     * @return void
     */
    public function initialize(): void
    {
        if (Log::getConfig('debug_bar_log_panel')) {
            return;
        }

        Log::setConfig('debug_bar_log_panel', [
            'engine' => 'Crustum/DebugBar.DebugBar',
        ]);
    }

    /**
     * Get the panel data
     *
     * @return array
     */
    public function data(): array
    {
        return [
            'logger' => Log::engine('debug_bar_log_panel'),
        ];
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        /** @var \Crustum\DebugBar\Log\Engine\DebugBarLog|null $logger */
        $logger = Log::engine('debug_bar_log_panel');
        if (!$logger) {
            return '0';
        }

        return (string)$logger->count();
    }

    /**
     * Resets captured messages (long-lived workers and tests).
     *
     * The engine registration survives; only buffered rows are dropped.
     *
     * @return void
     */
    public static function clear(): void
    {
        $logger = Log::engine('debug_bar_log_panel');
        if ($logger instanceof DebugBarLog) {
            $logger->clear();
        }
    }

    /**
     * Builds log entry rows for the waterfall.
     *
     * @param array<string, mixed> $data Log panel data.
     * @param float $requestStart Request start as microtime(true).
     * @return list<array{at: float, duration_ms: float, type: string, label: string, panel: string, source: array{file: string, line: int}|null, ref: mixed}>
     */
    public function timelineEvents(array $data, float $requestStart): array
    {
        $events = [];
        $logger = $data['logger'] ?? null;
        $levels = [];
        if (is_object($logger) && method_exists($logger, 'all')) {
            $levels = (array)$logger->all();
        } elseif (is_array($logger)) {
            $levels = $logger;
        }

        $groupIndex = [];
        foreach ($levels as $level => $entries) {
            foreach (array_values((array)$entries) as $entry) {
                $entry = (array)$entry;
                $when = (float)($entry[2] ?? 0);
                if ($when <= 0 && isset($entry[0])) {
                    $parsed = strtotime((string)$entry[0]);
                    $when = $parsed === false ? 0.0 : (float)$parsed;
                }

                $message = (string)($entry[1] ?? '');
                $group = $level . "\0" . $message;
                $groupIndex[$group] ??= count($groupIndex);

                $events[] = [
                    'at' => $when > 0 ? max(0.0, ($when - $requestStart) * 1000) : 0.0,
                    'duration_ms' => 0.0,
                    'type' => 'log',
                    'label' => mb_strimwidth($message, 0, 120, '…'),
                    'panel' => 'log',
                    'source' => null,
                    'ref' => 'log-' . $groupIndex[$group],
                ];
            }
        }

        return $events;
    }
}
