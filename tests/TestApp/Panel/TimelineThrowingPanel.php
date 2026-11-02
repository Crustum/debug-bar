<?php
declare(strict_types=1);

namespace TestApp\Panel;

use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Panel\TimelineSourceInterface;
use RuntimeException;

/**
 * Always-failing waterfall source for timeline tests.
 */
class TimelineThrowingPanel extends DebugPanel implements TimelineSourceInterface
{
    /**
     * Fails so the builder skip path stays observable.
     *
     * @param array<string, mixed> $data Collected panel data.
     * @param float $requestStart Request start as microtime(true).
     * @return list<array{at: float, duration_ms: float, type: string, label: string, panel: string, source: array{file: string, line: int}|null, ref: mixed}>
     */
    public function timelineEvents(array $data, float $requestStart): array
    {
        throw new RuntimeException('stub boom');
    }
}
