<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

/**
 * Timeline event source.
 *
 * Panels owning timestamped data expose it to the waterfall through this
 * contract. The toolbar collects each loaded source in registry order
 * with no configuration; a panel without timestamps simply stays out by
 * not implementing the contract.
 *
 * Inspector presentation is plugin-side too: a source may define the
 * optional duck-typed methods `timelineLabel()` and
 * `timelineInspectorKey()` (plain methods with these names are enough, no
 * dependency needed). Otherwise the panel title and the shared
 * title-to-key rule apply, so third-party panels need no core changes.
 */
interface TimelineSourceInterface
{
    /**
     * Builds waterfall rows from already collected panel data.
     *
     * Each row links back to its inspector through `panel` plus `ref`
     * (for example `sqllog:1`, `log:log-0`); only the short `label`
     * (up to 120 chars) is copied, storage keeps no duplicates.
     *
     * `ref` must equal the inspector row key (`data-dk-<name>-key`) so
     * an "Open inspector" jump can select the exact record: `query-3`,
     * `model-1`, `event-2`, `http-1`, `mail-0`, `log-4`, `cache-7`,
     * `view-1`, `exception-1`, `authorization-3`, `queue-2` and so on.
     * Rows without a selectable counterpart use `ref: null`.
     *
     * @param array<string, mixed> $data Collected panel data for one request.
     * @param float $requestStart Request start as microtime(true).
     * @return list<array{at: float, duration_ms: float, type: string, label: string, panel: string, source: array{file: string, line: int}|null, ref: mixed}>
     */
    public function timelineEvents(array $data, float $requestStart): array;
}
