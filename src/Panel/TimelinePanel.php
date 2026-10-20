<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Crustum\DebugBar\DebugPanel;

/**
 * Timeline Panel - derived event waterfall.
 *
 * No collection of its own: the toolbar assembles the merged event list
 * from sibling panel data at `saveData()` time (see `ToolbarService`).
 * Until then `data()` returns the pending shape so JSON/HTML contracts
 * stay stable.
 */
class TimelinePanel extends DebugPanel
{
    /**
     * Get the panel data (assembled by the toolbar, not here).
     *
     * @return array
     */
    public function data(): array
    {
        return [
            'events' => [],
            'retained' => 0,
            'omitted' => 0,
            'pending' => true,
        ];
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        return '';
    }
}
