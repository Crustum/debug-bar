<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Hash;

/**
 * Request summary facts shared by the toolbar page and the panels endpoint.
 *
 * Single home for the duration/memory/query-count math so the full page
 * (`templates/Requests/view.php`) and the on-the-fly request switch
 * (`RequestsController::panels()`) render identical header facts.
 */
class RequestFacts
{
    /**
     * Builds the header facts for one stored request row.
     *
     * @param \Cake\Datasource\EntityInterface $toolbar Request with panels.
     * @return array<string, mixed>
     */
    public static function summarize(EntityInterface $toolbar): array
    {
        $durationMs = 0.0;
        $peakMemory = 0;
        $queryCount = 0;
        $queryTimeMs = 0.0;
        $responseSizeBytes = null;
        /** @var array $storedPanels */
        $storedPanels = $toolbar->get('panels');
        foreach ($storedPanels as $panel) {
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $stored = @unserialize((string)$panel->content);
            if (!is_array($stored)) {
                continue;
            }

            if (self::isPanel($panel->panel ?? '', 'Timer')) {
                $durationMs = max(0.0, (float)($stored['requestTime'] ?? 0) * 1000);
                $peakMemory = max(0, (int)($stored['peakMemory'] ?? 0));
            }

            if (self::isPanel($panel->panel ?? '', 'SqlLog')) {
                foreach ((array)($stored['loggers'] ?? []) as $logger) {
                    if (is_object($logger) && method_exists($logger, 'queries')) {
                        $queryCount += count($logger->queries());
                    }

                    if (is_object($logger) && method_exists($logger, 'totalTime')) {
                        $queryTimeMs += max(0.0, (float)$logger->totalTime());
                    }
                }
            }

            if (self::isPanel($panel->panel ?? '', 'Request')) {
                $candidate = $stored['responseSize'] ?? null;
                if (is_int($candidate) && $candidate >= 0) {
                    $responseSizeBytes = $candidate;
                }
            }
        }

        $durationLabel = self::formatDuration($durationMs);

        $peakLabel = $peakMemory > 0
            ? number_format($peakMemory / 1048576, $peakMemory / 1048576 >= 100 ? 0 : 1) . ' MB'
            : '—';
        $statusCode = (int)$toolbar->get('status_code');

        return [
            'id' => (string)$toolbar->get('id'),
            'url' => (string)$toolbar->get('url'),
            'method' => (string)$toolbar->get('method'),
            'status_code' => $statusCode,
            'content_type' => (string)$toolbar->get('content_type'),
            'requested_at' => (string)$toolbar->get('requested_at'),
            'environment' => (bool)Configure::read('debug') ? 'local' : 'production',
            'duration_ms' => round($durationMs, 2),
            'duration_label' => $durationLabel,
            'peak_memory_mb' => $peakMemory > 0 ? round($peakMemory / 1048576, 2) : 0,
            'peak_memory_label' => $peakLabel,
            'query_count' => $queryCount,
            'query_time_ms' => round($queryTimeMs, 2),
            'query_time_label' => QueryAnalyzer::formatDuration($queryTimeMs),
            'response_size_bytes' => self::responseSizeFor((string)$toolbar->get('method'), $responseSizeBytes),
            'response_size' => self::formatBytes(
                self::responseSizeFor((string)$toolbar->get('method'), $responseSizeBytes),
            ),
            'warning' => $statusCode >= 400,
            'max_requests' => max(1, (int)Configure::read('DebugBar.storage.max_requests', 20)),
        ];
    }

    /**
     * Matches a stored panel alias with or without its plugin prefix.
     *
     * The registry stores short aliases (`Request`) while older rows may
     * carry `Crustum/DebugBar.Request`; both mean the same panel.
     *
     * @param mixed $alias Stored `panel` value.
     * @param string $short Short alias, e.g. `Request`.
     * @return bool
     */
    public static function isPanel(mixed $alias, string $short): bool
    {
        $alias = (string)$alias;
        if ($alias === $short) {
            return true;
        }

        return str_ends_with($alias, '/' . $short) || str_ends_with($alias, '.' . $short);
    }

    /**
     * Hides the response size for non-HTTP runs.
     *
     * CLI commands and queue jobs have no HTTP body; showing a byte
     * count there is noise, so they always resolve to null.
     *
     * @param string $method Stored method (`GET`, `CLI`, `JOB`, …).
     * @param mixed $bytes Captured size or null.
     * @return int|null Size for HTTP runs, null for CLI/JOB or unknown.
     */
    public static function responseSizeFor(string $method, mixed $bytes): ?int
    {
        if ($method === 'CLI' || $method === 'JOB') {
            return null;
        }

        if (!is_int($bytes) || $bytes < 0) {
            return null;
        }

        return $bytes;
    }

    /**
     * Formats milliseconds without false precision on long runs.
     *
     * Short runs keep ms/µs detail; seconds keep 2dp; minutes and hours
     * drop to coarse units (`61 s` → `1 min`, `3,656 s` → `1 h 1 min`)
     * so CLI jobs don't render `3,656,862.1 ms`.
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
            return number_format($ms, $ms >= 100 ? 0 : 1) . ' ms';
        }

        if ($ms > 0) {
            $microseconds = $ms * 1000;

            return number_format($microseconds, $microseconds >= 10 ? 0 : 1) . ' µs';
        }

        return '—';
    }

    /**
     * Formats a response size like upstream (`44.52 KB`, `2.50 MB`, `95 B`).
     *
     * Null when the body size was not captured (CLI runs, detached streams).
     *
     * @param mixed $bytes Byte count or null.
     * @return string|null Human-readable size or null when unknown.
     */
    public static function formatBytes(mixed $bytes): ?string
    {
        if (!is_int($bytes) || $bytes < 0) {
            return null;
        }

        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }

        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return number_format($bytes) . ' B';
    }

    /**
     * Derives the inspector key from a panel title.
     *
     * Mirrors the `$inspectorKey` closure in `templates/Requests/view.php`;
     * both must stay in sync so swapped panels land in their sections.
     * Chrome-referenced inspectors resolve to stable keys (upstream
     * `request`/`queries`) instead of title spellings, so header facts,
     * findings and timeline links always open a real section.
     *
     * @param string $title Panel title.
     * @return string Inspector key.
     */
    public static function inspectorKey(string $title): string
    {
        $key = (string)preg_replace('/[^a-z0-9]+/', '_', strtolower($title));
        $key = trim($key, '_');
        if ($key === '') {
            return 'panel';
        }

        return [
            'request_panel' => 'request',
            'sqllog' => 'queries',
            'sql_log' => 'queries',
        ][$key] ?? $key;
    }

    /**
     * Built-in inspector descriptions shown under the inspector heading.
     *
     * Keyed by panel registry alias, e.g. `Cache`. Third-party panels
     * register their own line via the `DebugBar.inspectorDescriptions`
     * Configure map instead of editing this list.
     *
     * @var array<string, string>
     */
    protected const DESCRIPTIONS = [
        'Request' => 'Request line, headers, input and middleware for this request.',
        'SqlLog' => 'Captured statements with bindings, source and EXPLAIN.',
        'Timeline' => 'Every captured event on one time scale.',
        'Cache' => 'Cache operations with hit rate and raw log lines.',
        'Log' => 'Log records grouped by level and channel.',
        'Mail' => 'Sent emails with preview, source and attachments.',
        'HttpClient' => 'Outgoing HTTP requests with response evidence.',
        'Events' => 'Dispatched events with payload and source.',
        'Models' => 'ORM activity grouped by model with records and writes.',
        'Exceptions' => 'Uncaught exceptions with source, stack and causes.',
        'Views' => 'Rendered templates with passed values and composers.',
        'Validation' => 'Validation errors grouped by field.',
        'Findings' => 'Attention points merged across every panel.',
        'Authorization' => 'Authorization decisions with policy and result.',
        'Timer' => 'Timers and memory checkpoints for this request.',
        'Environment' => 'Application and environment facts.',
        'Variables' => 'View and request variables.',
        'Routes' => 'Matched route with the compiled routes table.',
        'Packages' => 'Installed Composer packages.',
        'Attributes' => 'Discovered PHP attributes by resolver scope.',
        'Plugins' => 'Loaded plugins and their state.',
        'Deprecations' => 'Deprecation notices grouped by origin.',
    ];

    /**
     * Resolves the inspector description for one panel.
     *
     * Third-party panels (e.g. `HtmxWire.LiveWidget`) provide their line
     * through `Configure::write('DebugBar.inspectorDescriptions', [...])`
     * keyed by registry alias; built-ins fall back to the map above and
     * unknown panels stay empty so the heading hides the description.
     *
     * @param string $panel Panel registry name, e.g. `Cache`.
     * @return string Description or empty string.
     */
    public static function inspectorDescription(string $panel): string
    {
        $overrides = (array)Configure::read('DebugBar.inspectorDescriptions');
        if (isset($overrides[$panel]) && is_string($overrides[$panel])) {
            return $overrides[$panel];
        }

        return self::DESCRIPTIONS[$panel] ?? '';
    }

    /**
     * Counts the badge number for one panel's stored content.
     *
     * Panels without a meaningful count return null and render no badge.
     *
     * @param string $panel Panel registry name, e.g. `SqlLog`.
     * @param array<string, mixed> $stored Unserialized panel content.
     * @return int|null Badge count or null.
     */
    public static function panelCount(string $panel, array $stored): ?int
    {
        return match ($panel) {
            'SqlLog' => count($stored['records'] ?? $stored['items'] ?? []),
            'Log' => self::logCount($stored['logger'] ?? null),
            'Cache' => count(array_values((array)($stored['logs'] ?? []))),
            'Mail' => count(array_values((array)($stored['emails'] ?? []))),
            'Timeline' => (int)($stored['retained'] ?? count(array_values((array)($stored['events'] ?? [])))),
            'Validation' => self::listCount($stored['errors'] ?? null),
            'Models' => self::listCount($stored['groups'] ?? null),
            'HttpClient' => (int)($stored['summary']['count'] ?? self::listCount($stored['requests'] ?? null)),
            'Findings' => (int)($stored['retained'] ?? self::listCount($stored['findings'] ?? null)),
            'Exceptions' => (int)($stored['summary']['count'] ?? self::listCount($stored['exceptions'] ?? null)),
            'Events' => (int)($stored['summary']['count'] ?? self::listCount($stored['events'] ?? null)),
            'Authorization' => (int)($stored['summary']['count'] ?? self::listCount($stored['decisions'] ?? null)),
            'Deprecations' => self::deprecationsCount($stored),
            'Views' => (int)($stored['summary']['count'] ?? self::listCount($stored['renders'] ?? null)),
            'Variables' => self::listCount($stored['variables'] ?? null),
            'Plugins' => self::listCount($stored['plugins'] ?? null),
            'Packages' => self::listCount($stored['packages'] ?? null)
                + self::listCount($stored['devPackages'] ?? null),
            'Attributes' => (int)($stored['total'] ?? self::listCount($stored['items'] ?? null)),
            // Third-party panels badge from their own summary count without
            // a per-panel mapping.
            default => isset($stored['summary']['count']) ? (int)$stored['summary']['count'] : null,
        };
    }

    /**
     * Counts a stored list without assuming its shape.
     *
     * @param mixed $list Stored list or null.
     * @return int Entry count or zero.
     */
    protected static function listCount(mixed $list): int
    {
        return is_countable($list) ? count($list) : 0;
    }

    /**
     * Counts deprecations across the grouped buckets.
     *
     * Mirrors `DeprecationsPanel::summary()`: flat buckets count directly,
     * maps of groups (plugins, vendor) sum their groups.
     *
     * @param array<string, mixed> $stored Unserialized panel content.
     * @return int Deprecation count.
     */
    protected static function deprecationsCount(array $stored): int
    {
        $total = 0;
        foreach (['cake', 'app', 'plugins', 'vendor', 'other'] as $bucket) {
            $entries = $stored[$bucket] ?? null;
            if (empty($entries) || !is_array($entries)) {
                continue;
            }

            if (Hash::dimensions($entries) === 2) {
                $total += count($entries);
                continue;
            }

            foreach ($entries as $group) {
                $total += self::listCount($group);
            }
        }

        return $total;
    }

    /**
     * Counts entries across every level of the log engine.
     *
     * @param mixed $logger Stored log engine or level map.
     * @return int|null Entry count or null when unreadable.
     */
    protected static function logCount(mixed $logger): ?int
    {
        if (is_object($logger) && method_exists($logger, 'all')) {
            $levels = $logger->all();
        } elseif (is_array($logger)) {
            $levels = $logger;
        } else {
            return null;
        }

        $total = 0;
        foreach ((array)$levels as $entries) {
            $total += count(array_values((array)$entries));
        }

        return $total;
    }
}
