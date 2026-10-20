<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Collection\CollectionInterface;
use function collection;

/**
 * Findings service.
 *
 * Pure functions over stored sibling panel data: slow/repeated/N+1 queries
 * (via fingerprint groups), cache high-miss rate, captured errors, error
 * status, deprecations and truncation notices. Each finding carries
 * `rule_id|severity|panel|summary|why|location|next|action`, where `panel`
 * is the inspector key its source section renders under.
 *
 * Error-level log entries stand in for `exception.captured` until the
 * exceptions collector exists.
 *
 * @phpstan-type Finding array{rule_id: string, severity: string, panel: string, summary: string, why: string, location: string, next: string, action: string}
 */
class FindingsService
{
    /**
     * Slow query threshold in milliseconds.
     *
     * @var float
     */
    protected float $slowQueryMs;

    /**
     * Slow request threshold in milliseconds.
     *
     * @var float
     */
    protected float $slowRequestMs;

    /**
     * Minimum cache operations before a miss-rate finding fires.
     *
     * @var int
     */
    protected int $minimumCacheOperations;

    /**
     * Cache miss rate triggering a finding.
     *
     * @var float
     */
    protected float $highCacheMissRate;

    /**
     * Maximum findings kept per request.
     *
     * @var int
     */
    protected int $maxFindings;

    /**
     * Creates the service from toolbar config.
     *
     * @param array<string, mixed> $config Service config with `slow_query_ms`,
     *   `slow_request_ms` and the `findings` section (`minimum_cache_operations`,
     *   `high_cache_miss_rate`, `max_findings`).
     */
    public function __construct(array $config = [])
    {
        $findings = (array)($config['findings'] ?? []);
        $this->slowQueryMs = max(0.0, (float)($config['slow_query_ms'] ?? 100));
        $this->slowRequestMs = max(0.0, (float)($config['slow_request_ms'] ?? 1000));
        $this->minimumCacheOperations = max(1, (int)($findings['minimum_cache_operations'] ?? 5));
        $this->highCacheMissRate = min(1.0, max(0.0, (float)($findings['high_cache_miss_rate'] ?? 0.8)));
        $this->maxFindings = max(1, (int)($findings['max_findings'] ?? 50));
    }

    /**
     * Builds the capped finding list for one stored request.
     *
     * @param array<string, mixed> $collected Sibling panel data keyed by registry alias.
     * @param array<string, mixed> $request Request context with `status_code`, `method`,
     *   `url` and `duration_ms`.
     * @param array<string, string> $titles Registry alias to stored panel title (for inspector keys).
     * @return array{findings: array<int, Finding>, retained: int, omitted: int}
     */
    public function build(array $collected, array $request = [], array $titles = []): array
    {
        /** @var \Cake\Collection\CollectionInterface<int, Finding> $findings */
        $findings = collection($this->queryFindings((array)($collected['SqlLog'] ?? []), $this->key($titles, 'SqlLog')))
            ->append($this->cacheFindings((array)($collected['Cache'] ?? []), $this->key($titles, 'Cache')))
            ->append($this->exceptionFindings((array)($collected['Log'] ?? []), $this->key($titles, 'Log')))
            ->append($this->requestFindings(
                $request,
                $this->key($titles, 'Request'),
                $this->key($titles, 'Timeline'),
            ))
            ->append($this->deprecationFindings((array)($collected['Deprecations'] ?? []), $this->key($titles, 'Deprecations')))
            ->append($this->truncationFindings((array)($collected['Timeline'] ?? []), $this->key($titles, 'Timeline')));

        /** @var array<int, Finding> $ordered */
        $ordered = $findings
            ->sortBy(fn(array $finding): int => $this->severityRank((string)$finding['severity']), SORT_ASC, SORT_NUMERIC)
            ->toList();

        $retained = count($ordered);
        $omitted = max(0, $retained - $this->maxFindings);

        return [
            'findings' => array_slice($ordered, 0, $this->maxFindings),
            'retained' => $retained,
            'omitted' => $omitted,
        ];
    }

    /**
     * Resolves the inspector key for a registry alias.
     *
     * @param array<string, string> $titles Registry alias to stored title.
     * @param string $alias Registry alias, e.g. `SqlLog`.
     * @return string Inspector key the section renders under.
     */
    protected function key(array $titles, string $alias): string
    {
        return RequestFacts::inspectorKey((string)($titles[$alias] ?? $alias));
    }

    /**
     * Ranks one severity for sorting, errors first.
     *
     * @param string $severity `error`, `warning` or `info`.
     * @return int Sort rank, errors first.
     */
    protected function severityRank(string $severity): int
    {
        return match ($severity) {
            'error' => 0,
            'warning' => 1,
            default => 2,
        };
    }

    /**
     * Slow, repeated and N+1 query findings from fingerprint groups.
     *
     * @param array<string, mixed> $sqlData SqlLog panel data with flattened `items`.
     * @param string $panel Inspector key of the queries section.
     * @return \Cake\Collection\CollectionInterface<int, Finding>
     */
    protected function queryFindings(array $sqlData, string $panel): CollectionInterface
    {
        $analyzer = new QueryAnalyzer(['slow_query_ms' => $this->slowQueryMs]);
        /** @var \Cake\Collection\CollectionInterface<int, array<string, mixed>> $records */
        $records = collection($analyzer->present(array_values((array)($sqlData['items'] ?? []))));

        /** @var \Cake\Collection\CollectionInterface<int, Finding> $findings */
        $findings = $records->map(function (array $record) use ($panel): ?array {
            $count = (int)$record['count'];
            $isNPlusOne = (bool)$record['likely_n_plus_one'];
            $isRepeated = (bool)$record['repeated'];
            $isSlow = (bool)$record['slow'];
            if (!$isNPlusOne && !$isRepeated && !$isSlow) {
                return null;
            }

            if ($isNPlusOne) {
                return [
                    'rule_id' => 'query.n_plus_one',
                    'severity' => 'error',
                    'panel' => $panel,
                    'summary' => "Possible N+1: {$count} similar queries",
                    'why' => 'Same fingerprint ran with varying bindings — likely one query per row in a loop.',
                    'location' => (string)$record['source_label'],
                    'next' => 'Eager-load the association or batch the lookup.',
                    'action' => "Review in {$panel}",
                ];
            }

            if ($isRepeated) {
                return [
                    'rule_id' => 'query.repeated',
                    'severity' => 'warning',
                    'panel' => $panel,
                    'summary' => "Repeated query × {$count}",
                    'why' => 'Identical statements ran more than once in a single request.',
                    'location' => (string)$record['source_label'],
                    'next' => 'Cache the result or collapse the calls into one query.',
                    'action' => "Review in {$panel}",
                ];
            }

            return [
                'rule_id' => 'query.slow',
                'severity' => 'warning',
                'panel' => $panel,
                'summary' => "Slow query ({$record['duration_label']})",
                'why' => "A single statement took {$record['duration_label']} (threshold " .
                    QueryAnalyzer::formatDuration($this->slowQueryMs) . ').',
                'location' => (string)$record['source_label'],
                'next' => 'Run EXPLAIN on the query and check for missing indexes.',
                'action' => "Review in {$panel}",
            ];
        })->filter(fn(mixed $finding): bool => is_array($finding));

        return $findings;
    }

    /**
     * High cache-miss-rate finding from debug-engine metrics.
     *
     * @param array<string, mixed> $cacheData Cache panel data with `metrics`.
     * @param string $panel Inspector key of the cache section.
     * @return \Cake\Collection\CollectionInterface<int, Finding>
     */
    protected function cacheFindings(array $cacheData, string $panel): CollectionInterface
    {
        /** @var \Cake\Collection\CollectionInterface<int, array<string, int>> $metrics */
        $metrics = collection(array_values((array)($cacheData['metrics'] ?? [])));
        $hits = (int)$metrics->sumOf(fn(array $row): int => (int)($row['get hit'] ?? 0));
        $misses = (int)$metrics->sumOf(fn(array $row): int => (int)($row['get miss'] ?? 0));
        $reads = $hits + $misses;
        if ($reads < $this->minimumCacheOperations) {
            return collection([]);
        }

        $rate = $reads > 0 ? $misses / $reads : 0.0;
        if ($rate < $this->highCacheMissRate) {
            return collection([]);
        }

        $percent = number_format($rate * 100, 0);

        return collection([[
            'rule_id' => 'cache.high_miss_rate',
            'severity' => 'warning',
            'panel' => $panel,
            'summary' => "High cache miss rate ({$percent}% of {$reads} reads)",
            'why' => 'Most cache reads missed — keys churn, TTLs are too short, or the cache is cold.',
            'location' => "{$misses} misses / {$reads} reads",
            'next' => 'Warm the hot keys, raise TTLs, or stop caching per-request values.',
            'action' => "Review in {$panel}",
        ]]);
    }

    /**
     * Captured-error finding from error-level log entries.
     *
     * @param array<string, mixed> $logData Log panel data with `logger`.
     * @param string $panel Inspector key of the logs section.
     * @return \Cake\Collection\CollectionInterface<int, Finding>
     */
    protected function exceptionFindings(array $logData, string $panel): CollectionInterface
    {
        $logger = $logData['logger'] ?? null;
        $levels = [];
        if (is_object($logger) && method_exists($logger, 'all')) {
            $levels = (array)$logger->all();
        } elseif (is_array($logger)) {
            $levels = $logger;
        }

        /** @var \Cake\Collection\CollectionInterface<string, mixed> $map */
        $map = collection($levels);
        $count = (int)$map
            ->filter(fn(mixed $entries, string $level): bool => in_array(strtolower($level), [
                'emergency',
                'alert',
                'critical',
                'error',
            ], true))
            ->map(fn(mixed $entries): int => count(array_values((array)$entries)))
            ->sumOf(fn(int $rows): int => $rows);
        if ($count < 1) {
            return collection([]);
        }

        return collection([[
            'rule_id' => 'exception.captured',
            'severity' => 'error',
            'panel' => $panel,
            'summary' => $count === 1 ? '1 error captured in logs' : "{$count} errors captured in logs",
            'why' => 'Error-level entries were logged during the request.',
            'location' => "{$count} error entries",
            'next' => 'Open the related exception or fix the failing call.',
            'action' => "Review in {$panel}",
        ]]);
    }

    /**
     * Error-status and slow-request findings from the request context.
     *
     * The duration comes from the derived timeline window so the threshold
     * matches the waterfall the inspector shows.
     *
     * @param array<string, mixed> $request Request context with `status_code`,
     *   `method`, `url` and `duration_ms`.
     * @param string $panel Inspector key of the request section.
     * @param string $timelinePanel Inspector key of the timeline section.
     * @return \Cake\Collection\CollectionInterface<int, Finding>
     */
    protected function requestFindings(array $request, string $panel, string $timelinePanel): CollectionInterface
    {
        $status = (int)($request['status_code'] ?? 0);
        /** @var array<int, Finding> $findings */
        $findings = [];
        if ($status >= 400) {
            $serverError = $status >= 500;
            $findings[] = [
                'rule_id' => 'request.error',
                'severity' => $serverError ? 'error' : 'warning',
                'panel' => $panel,
                'summary' => "Request failed with HTTP {$status}",
                'why' => $serverError
                    ? 'The server errored while handling the request.'
                    : 'The request was rejected (client error).',
                'location' => trim(($request['method'] ?? '') . ' ' . ($request['url'] ?? '')),
                'next' => $serverError ? 'Check the logs and the failing callsite.' : 'Check params, auth and validation.',
                'action' => "Review in {$panel}",
            ];
        }

        $duration = max(0.0, (float)($request['duration_ms'] ?? 0));
        if ($this->slowRequestMs > 0 && $duration >= $this->slowRequestMs) {
            $label = QueryAnalyzer::formatDuration($duration);
            $findings[] = [
                'rule_id' => 'request.slow',
                'severity' => 'warning',
                'panel' => $timelinePanel,
                'summary' => "Slow request ({$label})",
                'why' => 'This is above the configured ' .
                    QueryAnalyzer::formatDuration($this->slowRequestMs) . ' threshold.',
                'location' => trim(($request['method'] ?? '') . ' ' . ($request['url'] ?? '')),
                'next' => 'Inspect Timeline to find where the request spent time.',
                'action' => "Review in {$timelinePanel}",
            ];
        }

        return collection($findings);
    }

    /**
     * Deprecations-present finding from grouped deprecation errors.
     *
     * @param array<string, mixed> $deprecations Deprecations panel data.
     * @param string $panel Inspector key of the deprecations section.
     * @return \Cake\Collection\CollectionInterface<int, Finding>
     */
    protected function deprecationFindings(array $deprecations, string $panel): CollectionInterface
    {
        /** @var \Cake\Collection\CollectionInterface<string, mixed> $groups */
        $groups = collection($deprecations);
        $count = (int)$groups
            ->map(function (mixed $group): int {
                if (!is_array($group) || $group === []) {
                    return 0;
                }

                $flat = 0;
                foreach (array_values($group) as $entries) {
                    $flat += is_array($entries) && array_is_list($entries) ? count($entries) : 1;
                }

                return $flat;
            })
            ->sumOf(fn(int $rows): int => $rows);
        if ($count < 1) {
            return collection([]);
        }

        return collection([[
            'rule_id' => 'deprecations.present',
            'severity' => 'warning',
            'panel' => $panel,
            'summary' => $count === 1 ? '1 deprecation triggered' : "{$count} deprecations triggered",
            'why' => 'Deprecated APIs will break on the next major upgrade.',
            'location' => "{$count} deprecation notices",
            'next' => 'Migrate the flagged calls to their replacements.',
            'action' => "Review in {$panel}",
        ]]);
    }

    /**
     * Truncation finding from the derived timeline omission count.
     *
     * @param array<string, mixed> $timelineData Timeline panel data.
     * @param string $panel Inspector key of the timeline section.
     * @return \Cake\Collection\CollectionInterface<int, Finding>
     */
    protected function truncationFindings(array $timelineData, string $panel): CollectionInterface
    {
        $omitted = (int)($timelineData['omitted'] ?? 0);
        if ($omitted < 1) {
            return collection([]);
        }

        return collection([[
            'rule_id' => 'collector.truncated',
            'severity' => 'warning',
            'panel' => $panel,
            'summary' => "Timeline incomplete: {$omitted} events omitted",
            'why' => 'Collection caps dropped events — the waterfall is partial.',
            'location' => "{$omitted} omitted events",
            'next' => 'Raise collection caps or narrow the captured scope.',
            'action' => "Review in {$panel}",
        ]]);
    }
}
