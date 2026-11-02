<?php
declare(strict_types=1);

namespace TestApp\Panel;

use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Panel\TimelineSourceInterface;

/**
 * Fixed waterfall source for timeline tests.
 */
class TimelineStubPanel extends DebugPanel implements TimelineSourceInterface
{
    /**
     * Builds one fixed row so allowlist wiring stays observable.
     *
     * @param array<string, mixed> $data Collected panel data.
     * @param float $requestStart Request start as microtime(true).
     * @return list<array{at: float, duration_ms: float, type: string, label: string, panel: string, source: array{file: string, line: int}|null, ref: mixed}>
     */
    public function timelineEvents(array $data, float $requestStart): array
    {
        return [
            [
                'at' => 5.0,
                'duration_ms' => 1.0,
                'type' => 'stub',
                'label' => 'stub event',
                'panel' => 'stub',
                'source' => null,
                'ref' => null,
            ],
        ];
    }
}
