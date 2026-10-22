<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Log\Engine;

use Cake\Log\Engine\BaseLog;
use Crustum\DebugBar\Service\VarExporter;
use Stringable;

/**
 * A CakeLog listener which saves having to munge files or other configured loggers.
 *
 * @ported-from \DebugKit\Log\Engine\DebugKitLog
 */
class DebugBarLog extends BaseLog
{
    /**
     * logs
     *
     * @var array
     */
    protected array $logs = [];

    /**
     * Captures log messages in memory.
     *
     * Context is exported the Variables way (`VarExporter`: expandable
     * inner objects, sensitive leaves masked, JSON-safe) so the inspector
     * can render it without executing anything. Empty contexts stay `[]`.
     *
     * @param mixed $level The type of message being logged.
     * @param \Stringable|string $message The message being logged.
     * @param array $context Additional context data
     * @return void
     */
    public function log(mixed $level, Stringable|string $message, array $context = []): void
    {
        $this->logs[$level] ??= [];

        $this->logs[$level][] = [
            date('Y-m-d H:i:s'),
            $this->interpolate($message, $context),
            microtime(true),
            $context === [] ? [] : VarExporter::export($context),
        ];
    }

    /**
     * Get the logs.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->logs;
    }

    /**
     * Get the number of log entries.
     *
     * @return int
     */
    public function count(): int
    {
        return array_reduce($this->logs, fn(int $sum, array $v): int => $sum + count($v), 0);
    }

    /**
     * Drops captured messages (long-lived workers and tests).
     *
     * @return void
     */
    public function clear(): void
    {
        $this->logs = [];
    }

    /**
     * Check if there are no logs.
     *
     * @return bool
     */
    public function noLogs(): bool
    {
        return $this->logs === [];
    }
}
