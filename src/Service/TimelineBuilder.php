<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Crustum\DebugBar\Cache\Engine\DebugEngine;
use function collection;

/**
 * Timeline builder.
 *
 * Merges framework-phase timers, SQL queries, log entries and cache
 * operations onto one time scale at `saveData()` time. Pure: takes already
 * collected sibling panel data plus the request window, returns ordered
 * events with omission counts. Mail has no captured timestamps and stays
 * out until its collector records them.
 */
class TimelineBuilder
{
    /**
     * Builds the merged event list.
     *
     * @param array<string, mixed> $collected Sibling panel data keyed by registry alias.
     * @param float $requestStart Request start as microtime(true).
     * @param float $requestEnd Request end as microtime(true).
     * @param int $maxItems Retained event cap.
     * @return array{events: array<int, array<string, mixed>>, retained: int, omitted: int, start_ms: float, end_ms: float}
     */
    public function build(array $collected, float $requestStart, float $requestEnd, int $maxItems = 500): array
    {
        /** @var \Cake\Collection\CollectionInterface<int, array<string, mixed>> $events */
        $events = collection($this->timerEvents((array)($collected['Timer'] ?? []), $requestStart))
            ->append($this->queryEvents((array)($collected['SqlLog'] ?? []), $requestStart))
            ->append($this->logEvents((array)($collected['Log'] ?? []), $requestStart))
            ->append($this->cacheEvents((array)($collected['Cache'] ?? []), $requestStart));

        /** @var array<int, array<string, mixed>> $ordered */
        $ordered = $events
            ->sortBy(fn(array $event): float => (float)$event['at'], SORT_ASC, SORT_NUMERIC)
            ->toList();

        $retained = count($ordered);
        $omitted = max(0, $retained - max(1, $maxItems));

        return [
            'events' => array_slice($ordered, 0, max(1, $maxItems)),
            'retained' => $retained,
            'omitted' => $omitted,
            'start_ms' => 0.0,
            'end_ms' => max(0.0, ($requestEnd - $requestStart) * 1000),
        ];
    }

    /**
     * Framework-phase timer events (offsets are already request-relative).
     *
     * @param array<string, mixed> $timerData Timer panel data.
     * @param float $requestStart Unused (offsets are relative); kept for symmetry.
     * @return array<int, array<string, mixed>>
     */
    protected function timerEvents(array $timerData, float $requestStart): array
    {
        $events = [];
        foreach ((array)($timerData['timers'] ?? []) as $name => $timer) {
            $timer = (array)$timer;
            $events[] = [
                'at' => max(0.0, (float)($timer['start'] ?? 0) * 1000),
                'duration_ms' => max(0.0, (float)($timer['time'] ?? 0) * 1000),
                'type' => 'timer',
                'label' => (string)$name,
                'panel' => 'timer',
                'source' => null,
                'ref' => null,
            ];
        }

        return $events;
    }

    /**
     * SQL query events from flattened items.
     *
     * @param array<string, mixed> $sqlData SqlLog panel data.
     * @param float $requestStart Request start as microtime(true).
     * @return array<int, array<string, mixed>>
     */
    protected function queryEvents(array $sqlData, float $requestStart): array
    {
        $events = [];
        foreach (array_values((array)($sqlData['items'] ?? [])) as $index => $item) {
            $item = (array)$item;
            $start = (float)($item['start'] ?? 0);
            $callsite = is_array($item['callsite'] ?? null) ? $item['callsite'] : null;
            $events[] = [
                'at' => $start > 0 ? max(0.0, ($start - $requestStart) * 1000) : 0.0,
                'duration_ms' => max(0.0, (float)($item['took'] ?? 0)),
                'type' => 'sql',
                'label' => mb_strimwidth((string)($item['sql'] ?? ''), 0, 120, '…'),
                'panel' => 'sqllog',
                'source' => $callsite && ($callsite['file'] ?? null)
                    ? ['file' => (string)$callsite['file'], 'line' => (int)($callsite['line'] ?? 0)]
                    : null,
                'ref' => $index + 1,
            ];
        }

        return $events;
    }

    /**
     * Log entry events (wall-clock entries, 2- or 3-tuples).
     *
     * @param array<string, mixed> $logData Log panel data.
     * @param float $requestStart Request start as microtime(true).
     * @return array<int, array<string, mixed>>
     */
    protected function logEvents(array $logData, float $requestStart): array
    {
        $events = [];
        $logger = $logData['logger'] ?? null;
        $levels = [];
        if (is_object($logger) && method_exists($logger, 'all')) {
            $levels = (array)$logger->all();
        } elseif (is_array($logger)) {
            $levels = $logger;
        }

        foreach ($levels as $level => $entries) {
            foreach (array_values((array)$entries) as $entry) {
                $entry = (array)$entry;
                $when = (float)($entry[2] ?? 0);
                if ($when <= 0 && isset($entry[0])) {
                    $parsed = strtotime((string)$entry[0]);
                    $when = $parsed === false ? 0.0 : (float)$parsed;
                }

                $events[] = [
                    'at' => $when > 0 ? max(0.0, ($when - $requestStart) * 1000) : 0.0,
                    'duration_ms' => 0.0,
                    'type' => 'log',
                    'label' => mb_strimwidth((string)($entry[1] ?? ''), 0, 120, '…'),
                    'panel' => 'log',
                    'source' => null,
                    'ref' => (string)$level,
                ];
            }
        }

        return $events;
    }

    /**
     * Cache operation events (no captured timestamps, 1s log resolution).
     *
     * @param array<string, mixed> $cacheData Cache panel data.
     * @param float $requestStart Request start as microtime(true).
     * @return array<int, array<string, mixed>>
     */
    protected function cacheEvents(array $cacheData, float $requestStart): array
    {
        $events = [];
        foreach (array_values((array)($cacheData['logs'] ?? [])) as $line) {
            $line = (string)$line;
            $parsed = DebugEngine::parseLine($line);
            $when = 0.0;
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?( [+-]\d{2}:?\d{2})?/', $line, $matches)) {
                $parsedTime = strtotime($matches[1] . ($matches[3] ?? ''));
                if ($parsedTime !== false) {
                    $fraction = isset($matches[2]) ? (float)('0.' . $matches[2]) : 0.0;
                    $when = (float)$parsedTime + $fraction;
                }
            }

            $label = $parsed['operation'] !== ''
                ? trim($parsed['operation'] . ($parsed['key'] !== '' ? ' `' . $parsed['key'] . '`' : ''))
                : mb_strimwidth($line, 0, 120, '…');
            $events[] = [
                'at' => $when > 0 ? max(0.0, ($when - $requestStart) * 1000) : 0.0,
                'duration_ms' => is_numeric($parsed['duration']) ? (float)$parsed['duration'] : 0.0,
                'type' => 'cache',
                'label' => mb_strimwidth($label, 0, 120, '…'),
                'panel' => 'cache',
                'source' => null,
                'ref' => null,
            ];
        }

        return $events;
    }
}
