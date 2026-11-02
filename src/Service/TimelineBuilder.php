<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Log\Log;
use Crustum\DebugBar\Panel\PanelRegistry;
use Crustum\DebugBar\Panel\TimelineSourceInterface;
use Throwable;
use function collection;

/**
 * Timeline builder.
 *
 * Merges timestamped sibling panel data onto one time scale at `saveData()`
 * time. Every loaded panel implementing the event contract appears on the
 * waterfall in registry order with no configuration; a panel without
 * timestamps simply stays out by not implementing the contract.
 */
class TimelineBuilder
{
    /**
     * Builds the merged event list.
     *
     * Sources resolve in registry load order. A throwing source is skipped
     * with a warning: waterfall assembly is toolbar-internal and never
     * reported as a host-application finding.
     *
     * @param array<string, mixed> $collected Sibling panel data keyed by registry alias.
     * @param float $requestStart Request start as microtime(true).
     * @param float $requestEnd Request end as microtime(true).
     * @param \Crustum\DebugBar\Panel\PanelRegistry $registry Loaded panels.
     * @param int $maxItems Retained event cap.
     * @return array{events: array<int, array<string, mixed>>, retained: int, omitted: int, start_ms: float, end_ms: float, inspectors: array<string, array{key: string, label: string}>}
     */
    public function build(
        array $collected,
        float $requestStart,
        float $requestEnd,
        PanelRegistry $registry,
        int $maxItems = 500,
    ): array {
        /** @var array<int, array<string, mixed>> $chunks */
        $chunks = [];
        /** @var array<string, array{key: string, label: string}> $inspectors */
        $inspectors = [];
        foreach ($this->providers($registry) as $alias => $provider) {
            $data = (array)($collected[$alias] ?? []);
            try {
                // Duck-typed sources cannot carry the interface (no
                // dependency on this plugin), so dispatch dynamically.
                // providers() only admits panels exposing the method.
                $callable = [$provider, 'timelineEvents'];
                $events = is_callable($callable)
                    ? call_user_func($callable, $data, $requestStart)
                    : [];
            } catch (Throwable $throwable) {
                Log::warning(sprintf(
                    'Timeline source "%s" failed: %s',
                    $alias,
                    $throwable->getMessage(),
                ));

                continue;
            }

            if (!is_array($events) || $events === []) {
                continue;
            }

            $chunks[] = $events;
            foreach ($events as $row) {
                $panelKey = $row['panel'] !== '' ? $row['panel'] : strtolower($alias);
                $inspectors[$panelKey] ??= $this->inspectorInfo($alias, $provider);
            }
        }

        /** @var \Cake\Collection\CollectionInterface<int, array<string, mixed>> $sorted */
        $sorted = collection($chunks)
            ->unfold(fn(array $rows): array => array_values($rows))
            ->sortBy(fn(array $event): float => (float)$event['at'], SORT_ASC, SORT_NUMERIC);

        /** @var array<int, array<string, mixed>> $ordered */
        $ordered = $sorted->toList();

        $retained = count($ordered);
        $omitted = max(0, $retained - max(1, $maxItems));

        return [
            'events' => array_slice($ordered, 0, max(1, $maxItems)),
            'retained' => $retained,
            'omitted' => $omitted,
            'start_ms' => 0.0,
            'end_ms' => max(0.0, ($requestEnd - $requestStart) * 1000),
            'inspectors' => $inspectors,
        ];
    }

    /**
     * Resolves the inspector link and filter label for one source.
     *
     * Fully duck-typed on purpose: providers arrive through the
     * `method_exists($panel, 'timelineEvents')` gate, so legacy adapters
     * and dependency-free third-party panels reach here without
     * implementing the interface. Only plain method calls below.
     *
     * @param string $alias Registry alias.
     * @param object $provider Event source.
     * @return array{key: string, label: string}
     */
    protected function inspectorInfo(string $alias, object $provider): array
    {
        $title = method_exists($provider, 'title') ? (string)$provider->title() : '';
        if ($title === '') {
            $title = ucfirst($alias);
        }

        $key = method_exists($provider, 'timelineInspectorKey')
            ? trim((string)$provider->timelineInspectorKey())
            : '';
        if ($key === '') {
            $key = RequestFacts::inspectorKey($title);
        }

        $label = method_exists($provider, 'timelineLabel')
            ? trim((string)$provider->timelineLabel())
            : '';

        return [
            'key' => $key !== '' ? $key : strtolower($alias),
            'label' => $label !== '' ? $label : $title,
        ];
    }

    /**
     * Resolves event providers in registry load order.
     *
     * Duck-typed sources count: legacy adapters and third-party panels
     * exposing `timelineEvents()` join with no core changes and no
     * interface dependency.
     *
     * @param \Crustum\DebugBar\Panel\PanelRegistry $registry Loaded panels.
     * @return array<string, object>
     */
    protected function providers(PanelRegistry $registry): array
    {
        /** @var array<string, object> $ordered */
        $ordered = [];
        foreach ($registry->loaded() as $alias) {
            $panel = $registry->{$alias} ?? null;
            if ($panel instanceof TimelineSourceInterface) {
                $ordered[$alias] = $panel;

                continue;
            }

            if (is_object($panel) && method_exists($panel, 'timelineEvents')) {
                $ordered[$alias] = $panel;
            }
        }

        return $ordered;
    }
}
