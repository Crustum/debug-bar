<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Collection\CollectionInterface;
use function collection;

/**
 * Query intelligence for the SQL inspector.
 *
 * Pure functions over captured query items: fingerprinting, repeated-group
 * detection, slow flags and N+1 hints. Presentation records feed both the
 * server template and the client filter/sort module.
 */
class QueryAnalyzer
{
    /**
     * Creates the analyzer from toolbar config.
     *
     * @param array $config Analyzer config with `slow_query_ms`.
     */
    public function __construct(array $config = [])
    {
        $this->slowMs = max(0.0, (float)($config['slow_query_ms'] ?? 100));
    }

    /**
     * Slow query threshold in milliseconds.
     *
     * @var float
     */
    protected float $slowMs;

    /**
     * Normalizes SQL for grouping: collapses whitespace, replaces
     * single-quoted strings and standalone numbers with placeholders,
     * shrinks IN lists. Double-quoted segments stay intact: the ORM
     * quotes identifiers (`"Articles"."id"`), so stripping them would
     * blank the list shape to `?.?` and merge unrelated tables.
     *
     * @param string $sql Raw SQL.
     * @return string Normalized SQL.
     */
    public static function normalize(string $sql): string
    {
        $normalized = preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql) ?? $sql;
        $normalized = preg_replace('/\b\d+(?:\.\d+)?\b/', '?', $normalized) ?? $normalized;
        $normalized = preg_replace('/\bIN\s*\(\s*\?(?:\s*,\s*\?)+\s*\)/i', 'IN (…)', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? $normalized;

        return trim($normalized);
    }

    /**
     * Fingerprints normalized SQL for repeated-group detection.
     *
     * @param string $sql Raw SQL.
     * @return string Fingerprint hash.
     */
    public static function fingerprint(string $sql): string
    {
        return md5(static::normalize($sql));
    }

    /**
     * Classifies the statement: SELECT/WITH reads, everything else writes.
     *
     * @param string $sql Raw SQL.
     * @return string `read` or `write`.
     */
    public static function queryType(string $sql): string
    {
        $head = strtoupper(ltrim($sql));

        return str_starts_with($head, 'SELECT') || str_starts_with($head, 'WITH') ? 'read' : 'write';
    }

    /**
     * Formats milliseconds like the shell `duration.js` helper.
     *
     * @param float $ms Duration in milliseconds.
     * @return string Formatted duration.
     */
    public static function formatDuration(float $ms): string
    {
        if ($ms < 0 || !is_finite($ms)) {
            return '—';
        }

        if ($ms >= 3600000) {
            $hours = (int)floor($ms / 3600000);
            $minutes = (int)floor(($ms - $hours * 3600000) / 60000);
            if ($minutes > 0) {
                return $hours . ' h ' . $minutes . ' min';
            }

            return $hours . ' h';
        }

        if ($ms >= 60000) {
            $minutes = (int)floor($ms / 60000);
            $seconds = (int)round(($ms - $minutes * 60000) / 1000);
            if ($seconds > 0) {
                return $minutes . ' min ' . $seconds . ' s';
            }

            return $minutes . ' min';
        }

        if ($ms >= 1000) {
            return number_format($ms / 1000, 2) . ' s';
        }

        if ($ms >= 1) {
            return ($ms >= 100 ? number_format($ms, 0) : number_format($ms, 1)) . ' ms';
        }

        if ($ms > 0) {
            $microseconds = $ms * 1000;

            return number_format($microseconds, $microseconds >= 10 ? 0 : 1) . ' µs';
        }

        return '—';
    }

    /**
     * Presents captured items as inspector records (grouped, flagged).
     *
     * @param array<int, array<string, mixed>> $items Raw captured items.
     * @return array<int, array<string, mixed>> Records sorted by execution.
     */
    public function present(array $items): array
    {
        /** @var \Cake\Collection\CollectionInterface<int, array<string, mixed>> $executions */
        $executions = collection($items)
            ->map(fn(array $item, int $index): array => $this->execution($item, $index + 1));

        $total = max(0.0, (float)$executions->sumOf('duration_ms'));

        /** @var \Cake\Collection\CollectionInterface<string, \Cake\Collection\CollectionInterface<int, array<string, mixed>>> $groups */
        $groups = $executions->groupBy(fn(array $execution): string => (string)$execution['fingerprint']);

        /** @var array<int, array<string, mixed>> $records */
        $records = $groups
            ->map(fn(mixed $group): array => $this->record(collection((array)$group)->toList(), $total))
            ->toList();

        usort($records, static fn(array $left, array $right): int => $left['execution'] <=> $right['execution']);

        return $records;
    }

    /**
     * Filter tabs with execution counts for the inspector controls.
     *
     * @param array<int, array<string, mixed>> $records Presented records.
     * @return array<string, array{string, int}>
     */
    public function filters(array $records): array
    {
        /** @var \Cake\Collection\CollectionInterface<int, array<string, mixed>> $list */
        $list = collection($records);
        $count = static fn(CollectionInterface $rows): int => (int)$rows->sumOf('count');

        return [
            'all' => ['All', $count($list)],
            'attention' => [
                'Needs attention',
                $count($list->filter(fn(array $record): bool => (bool)$record['attention'])),
            ],
            'read' => [
                'Reads',
                $count($list->filter(fn(array $record): bool => $record['query_type'] === 'read')),
            ],
            'write' => [
                'Writes',
                $count($list->filter(fn(array $record): bool => $record['query_type'] === 'write')),
            ],
        ];
    }

    /**
     * Summary counters for the panel header.
     *
     * @param array<int, array<string, mixed>> $records Presented records.
     * @param float $totalMs Total query time in milliseconds.
     * @return array<string, mixed>
     */
    public function summarize(array $records, float $totalMs): array
    {
        /** @var \Cake\Collection\CollectionInterface<int, array<string, mixed>> $list */
        $list = collection($records);

        return [
            'count' => (int)$list->sumOf('count'),
            'groups' => count($records),
            'repeated' => $list->filter(fn(array $record): bool => (bool)$record['repeated'])->count(),
            'slow' => $list->filter(fn(array $record): bool => (bool)$record['slow'])->count(),
            'duration_ms' => round($totalMs, 2),
            'duration_label' => static::formatDuration($totalMs),
        ];
    }

    /**
     * Builds one execution row with derived flags.
     *
     * @param array<string, mixed> $item Raw captured item.
     * @param int $execution 1-based execution number.
     * @return array<string, mixed>
     */
    protected function execution(array $item, int $execution): array
    {
        $sql = (string)($item['sql'] ?? '');
        $normalized = static::normalize($sql);
        $duration = max(0.0, (float)($item['took'] ?? $item['duration_ms'] ?? 0));
        $bindings = array_values((array)($item['params'] ?? $item['bindings'] ?? []));
        $queryType = static::queryType($sql);

        $bindingsComplete = ($item['bindings_complete'] ?? false) === true;
        $runnableAvailable = ($item['runnable_available'] ?? false) === true
            && is_string($item['runnable_sql'] ?? null)
            && $item['runnable_sql'] !== '';
        $displaySql = $runnableAvailable ? (string)$item['runnable_sql'] : $sql;
        $callsite = is_array($item['callsite'] ?? null) ? $item['callsite'] : null;
        $file = is_string($callsite['file'] ?? null) && $callsite['file'] !== '' ? $callsite['file'] : null;
        $line = is_numeric($callsite['line'] ?? null) ? (int)$callsite['line'] : null;

        return [
            'execution' => $execution,
            'sql' => $sql,
            'normalized_sql' => $normalized,
            'display_sql' => $displaySql,
            'formatted_sql' => QueryFormatter::format($displaySql),
            'display_sql_complete' => $runnableAvailable || ($bindingsComplete && $bindings === []),
            'explain_available' => $queryType === 'read' && $runnableAvailable,
            'explain_unavailable_reason' => $queryType !== 'read'
                ? 'EXPLAIN is available for read queries only.'
                : 'EXPLAIN needs preserved SQL and complete bindings.',
            'fingerprint' => static::fingerprint($sql),
            'connection' => (string)($item['connection'] ?? 'default'),
            'driver' => (string)($item['driver'] ?? 'unknown'),
            'query_type' => $queryType,
            'duration_ms' => $duration,
            'duration_label' => static::formatDuration($duration),
            'slow' => $duration >= $this->slowMs && $this->slowMs > 0,
            'bindings' => $bindings,
            'rows' => (int)($item['rows'] ?? 0),
            'role' => (string)($item['role'] ?? ''),
            'in_transaction' => (bool)($item['inTransaction'] ?? false),
            'source_available' => $file !== null && $line !== null,
            'source_label' => $file !== null && $line !== null ? $file . ':' . $line : 'Source unavailable',
            'stack' => array_values((array)($item['stack'] ?? [])),
            'search' => mb_strtolower(implode(' ', [
                $sql,
                $normalized,
                (string)($item['connection'] ?? ''),
                $file !== null && $line !== null ? $file . ':' . $line : '',
                json_encode($bindings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '',
            ])),
        ];
    }

    /**
     * Collapses one fingerprint group into a record.
     *
     * @param array<int, array<string, mixed>> $group Executions sharing a fingerprint.
     * @param float $totalMs Total query time for percentages.
     * @return array<string, mixed>
     */
    protected function record(array $group, float $totalMs): array
    {
        $first = $group[0];
        $repeated = count($group) > 1;
        /** @var \Cake\Collection\CollectionInterface<int, array<string, mixed>> $rows */
        $rows = collection($group);
        $slow = $rows->some(fn(array $execution): bool => (bool)$execution['slow']);
        $duration = max(0.0, (float)$rows->sumOf('duration_ms'));
        $bindingsVary = $rows
            ->map(fn(array $execution): string => (string)json_encode($execution['bindings']))
            ->unique()
            ->count() > 1;

        /** @var array<int, array<string, mixed>> $executions */
        $executions = $rows
            ->map(function (array $execution) use ($totalMs): array {
                $execution['query_time_percent'] = $totalMs > 0
                    ? round($execution['duration_ms'] / $totalMs * 100, 1)
                    : 0.0;
                $execution['explain'] = null;
                $execution['explain_error'] = null;

                return $execution;
            })
            ->toList();

        return [
            'key' => ($repeated ? 'group-' : 'query-') . ($repeated ? $first['fingerprint'] : $first['execution']),
            'execution' => (int)$first['execution'],
            'sql' => (string)$first['normalized_sql'],
            'normalized_sql' => (string)$first['normalized_sql'],
            'connection' => (string)$first['connection'],
            'driver' => (string)$first['driver'],
            'query_type' => (string)$first['query_type'],
            'duration_ms' => $duration,
            'duration_label' => static::formatDuration($duration),
            'query_time_percent' => $totalMs > 0 ? round($duration / $totalMs * 100, 1) : 0.0,
            'count' => count($executions),
            'repeated' => $repeated,
            'slow' => $slow,
            'attention' => $slow || $repeated,
            'likely_n_plus_one' => $repeated && count($executions) >= 3 && $bindingsVary,
            'search' => (string)$first['search'],
            'source_available' => (bool)$first['source_available'],
            'source_label' => (string)$first['source_label'],
            'stack' => $first['stack'],
            'executions' => $executions,
        ];
    }
}
