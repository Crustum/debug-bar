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
class LogPanel extends DebugPanel
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
}
