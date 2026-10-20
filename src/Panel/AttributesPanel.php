<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use BackedEnum;
use Cake\AttributeResolver\AttributeResolver;
use Cake\Core\Plugin;
use Crustum\DebugBar\DebugPanel;
use Throwable;
use UnitEnum;

/**
 * Attributes Panel - Snapshot of discovered PHP attributes.
 *
 * Reads `Cake\AttributeResolver` directly in-process (no shell, no
 * `bin/cake attributes ...` subprocess): the same static
 * `AttributeResolver::collection()` API the `attributes list` /
 * `attributes inspect` / `attributes warm` commands use, but bounded for
 * toolbar storage. On-demand drill-down lives in
 * `AttributesController::query()` / `::inspect()` / `::warm()`,
 * mirroring how `ComposerController` owns the on-demand `outdated` /
 * `audit` runs for the Packages panel.
 *
 * The dependency is optional: when the resolver class or no configuration
 * is present the panel stores an `available => false` row instead of
 * failing serialization.
 */
class AttributesPanel extends DebugPanel
{
    /**
     * Maximum snapshot rows stored per resolver configuration.
     *
     * @var int
     */
    public const SNAPSHOT_PER_CONFIG = 100;

    /**
     * Maximum snapshot rows stored across all configurations.
     *
     * @var int
     */
    public const SNAPSHOT_LIMIT = 500;

    /**
     * Last collected total, reused by `summary()`.
     *
     * `ToolbarService` calls `summary()` and `data()` separately; the
     * scan is cache-backed but there is no reason to run it twice.
     *
     * @var int|null
     */
    protected static ?int $lastTotal = null;

    /**
     * Plugin path prefixes for file-based fallback detection.
     *
     * The resolver's own `pluginName` can be null (mixed separators or
     * vendor-vs-app layout confuse its prefix check): this map is
     * longest-prefix-first so nested paths resolve correctly.
     *
     * @var array<string, string>|null Prefix (trailing separator
     *   included) to plugin name.
     */
    protected static ?array $pluginPaths = null;

    /**
     * Get the panel data.
     *
     * Snapshots every configured resolver so the scope dropdown can
     * filter without a server round-trip. Rows carry their config name
     * plus target type as space-separated `data-dk-filter-group` tokens,
     * which the generic `applyListFilter()` matches one token at a time.
     *
     * @return array
     */
    public function data(): array
    {
        if (!class_exists(AttributeResolver::class)) {
            static::$lastTotal = null;

            return $this->unavailable('AttributeResolver is not installed.');
        }

        $configured = $this->configuredNames();
        if ($configured === []) {
            static::$lastTotal = null;

            return $this->unavailable('No AttributeResolver configuration found.');
        }

        $items = [];
        $configs = [];
        $byType = [];
        $total = 0;

        foreach ($configured as $configName) {
            try {
                $list = AttributeResolver::collection($configName)->toList();
            } catch (Throwable $e) {
                $configs[$configName] = [
                    'total' => 0,
                    'retained' => 0,
                    'dropped' => 0,
                    'error' => 'Attribute scan failed: ' . $e->getMessage(),
                ];

                continue;
            }

            $configTotal = count($list);
            $total += $configTotal;

            foreach ($list as $attr) {
                $type = $attr->target->type->value;
                $byType[$type] = ($byType[$type] ?? 0) + 1;
            }

            $retained = [];
            foreach ($list as $attr) {
                if (
                    count($retained) >= static::SNAPSHOT_PER_CONFIG ||
                    count($items) + count($retained) >= static::SNAPSHOT_LIMIT
                ) {
                    break;
                }

                $row = $attr->toArray();
                $row['config'] = $configName;
                if (($row['pluginName'] ?? null) === null) {
                    $row['pluginName'] = static::detectPlugin($attr->filePath);
                }

                $fileTime = $attr->fileTime;
                $row['fileTimeDisplay'] = $fileTime > 0 ? date('Y-m-d H:i:s', $fileTime) : '';
                $row['shortAttribute'] = $this->shortName($attr->attributeName);
                $row['shortClass'] = $this->shortName($attr->className);
                $row['targetType'] = $attr->target->type->value;
                $row['targetName'] = $attr->target->name;
                $row['argumentsDisplay'] = self::displayArguments($attr->arguments);
                $row['key'] = static::rowKey($row);
                $retained[] = $row;
            }

            $configs[$configName] = [
                'total' => $configTotal,
                'retained' => count($retained),
                'dropped' => max(0, $configTotal - count($retained)),
                'error' => null,
            ];
            array_push($items, ...$retained);

            if (count($items) >= static::SNAPSHOT_LIMIT) {
                break;
            }
        }

        // Facet counts cover every scanned row, including dropped ones.
        $byConfig = [];
        foreach ($configured as $configName) {
            $byConfig[$configName] = (int)($configs[$configName]['total'] ?? 0);
        }

        static::$lastTotal = $total;

        return [
            'available' => true,
            'reason' => null,
            'configs' => $configs,
            'total' => $total,
            'retained' => count($items),
            'dropped' => max(0, $total - count($items)),
            'truncated' => $total > count($items),
            'items' => $items,
            'byType' => $byType,
            'byConfig' => $byConfig,
        ];
    }

    /**
     * Get the summary data for the toolbar.
     *
     * @return string
     */
    public function summary(): string
    {
        if (static::$lastTotal === null) {
            return '';
        }

        return (string)static::$lastTotal;
    }

    /**
     * Resets the cached summary total (long-lived workers and tests).
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$lastTotal = null;
        static::$pluginPaths = null;
    }

    /**
     * Detect the owning plugin of a scanned file.
     *
     * Fallback for a null `AttributeInfo::$pluginName`: the resolver's own
     * prefix check compares raw configured paths against real paths, so
     * mixed separators (`...\search-doc/vendor/crustum/...`) or a
     * vendor-vs-app layout (composer path vs loaded path) silently miss.
     * Both sides are normalized here (separators + `realpath()`) and the
     * longest prefix wins. Returns null for app-level files.
     *
     * @param string $filePath Absolute file path.
     * @return string|null Plugin name or null for app-level files.
     */
    public static function detectPlugin(string $filePath): ?string
    {
        if ($filePath === '') {
            return null;
        }

        static::$pluginPaths ??= static::loadPluginPaths();

        $file = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $filePath), DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR;
        foreach (static::$pluginPaths as $prefix => $name) {
            if (str_starts_with($file, $prefix)) {
                return $name;
            }
        }

        return null;
    }

    /**
     * Load normalized plugin path prefixes, longest first.
     *
     * @return array<string, string> Prefix to plugin name.
     */
    protected static function loadPluginPaths(): array
    {
        $map = [];
        try {
            foreach (Plugin::getCollection() as $plugin) {
                $path = $plugin->getPath();
                if ($path === '') {
                    continue;
                }

                $real = realpath($path) ?: $path;
                $prefix = rtrim(
                    str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $real),
                    DIRECTORY_SEPARATOR,
                ) . DIRECTORY_SEPARATOR;
                $map[$prefix] = $plugin->getName();
            }
        } catch (Throwable) {
            return [];
        }

        uksort($map, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        return $map;
    }

    /**
     * Build the stored row for an unavailable resolver.
     *
     * @param string $reason Why no data is available.
     * @return array
     */
    protected function unavailable(string $reason): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'configs' => [],
            'total' => 0,
            'retained' => 0,
            'dropped' => 0,
            'truncated' => false,
            'items' => [],
            'byType' => [],
            'byConfig' => [],
        ];
    }

    /**
     * List configured resolver names.
     *
     * @return array<int, string>
     */
    protected function configuredNames(): array
    {
        /** @var array<int, string> $configured */
        $configured = AttributeResolver::configured();

        return array_values($configured);
    }

    /**
     * Build a stable row key for an attribute row.
     *
     * The key covers config, class, attribute, target and arguments so a
     * fresh inspect response matches the snapshot row (or proves it is
     * missing and must be appended). No request-local index is used.
     *
     * @param array $row Attribute row (panel snapshot or controller item).
     * @return string
     */
    public static function rowKey(array $row): string
    {
        $target = (array)($row['target'] ?? []);
        $args = $row['argumentsDisplay'] ?? [];
        $encoded = json_encode($args, JSON_UNESCAPED_SLASHES);
        $key = ($row['config'] ?? '') . '|' . ($row['className'] ?? '') . '|'
            . ($row['attributeName'] ?? '') . '|'
            . ($row['targetType'] ?? $target['type'] ?? '') . '|'
            . ($row['targetName'] ?? $target['name'] ?? '') . '|'
            . (is_string($encoded) ? $encoded : '');

        return md5($key);
    }

    /**
     * Shorten a fully-qualified class name to its last segment.
     *
     * @param string $name Class name.
     * @return string
     */
    protected function shortName(string $name): string
    {
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }

    /**
     * Format attribute arguments for display without leaking objects.
     *
     * Mirrors `AttributesInspectCommand::formatValue()` so panel rows and
     * CLI output agree: scalars as-is, bools/null as words, enums by
     * value/name, objects by class, arrays as JSON with a safe fallback.
     *
     * @param array<string, mixed> $arguments Attribute arguments.
     * @return array<string, string>
     */
    public static function displayArguments(array $arguments): array
    {
        $display = [];
        foreach ($arguments as $key => $value) {
            $display[(string)$key] = self::displayValue($value);
        }

        return $display;
    }

    /**
     * Format a single argument value.
     *
     * @param mixed $value Value to format.
     * @return string
     */
    public static function displayValue(mixed $value): string
    {
        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
            if (is_string($encoded)) {
                return $encoded;
            }

            $parts = [];
            foreach ($value as $item) {
                $parts[] = self::displayValue($item);
            }

            return '[' . implode(', ', $parts) . ']';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if ($value instanceof BackedEnum) {
            return (string)$value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            return $value::class;
        }

        return (string)$value;
    }
}
