<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Core\Configure;

/**
 * Mutual exclusion with sibling monitoring tools.
 *
 * DebugBar and Speculum instrument the same host application, so each
 * must ignore the other's bookkeeping or they record each other into
 * megabytes of noise: Speculum writes entries and vardumps through
 * `speculum_*` tables on the same connection DebugBar logs, and
 * Speculum's own models fire DebugBar's model capture. This gate keeps
 * observer traffic out at capture time, so storage, the timeline, the
 * request switcher payload and the JSON endpoints all stay clean.
 *
 * Lists live in `DebugBar.ignored_query_tables` (table name prefixes)
 * and `DebugBar.ignored_model_namespaces` (class namespaces); hosts
 * extend them for their own observers.
 */
final class IgnoredOperations
{
    /**
     * Table keywords recognized around a table reference.
     */
    private const TABLE_KEYWORDS = 'from|into|update|join|table';

    /**
     * Whether the SQL touches an ignored table.
     *
     * Matches table names after FROM/INTO/UPDATE/JOIN (quoted,
     * schema-qualified and bracketed references included) against the
     * configured prefixes, case-insensitively. SQL without a
     * recognizable table (BEGIN/COMMIT/SET) is never ignored.
     *
     * @param string $sql Raw or interpolated statement text.
     * @return bool True when the statement must not be recorded.
     */
    public static function isIgnoredQuery(string $sql): bool
    {
        $tables = self::referencedTables($sql);
        if ($tables === []) {
            return false;
        }

        $ignored = [];
        foreach ((array)Configure::read('DebugBar.ignored_query_tables', []) as $prefix) {
            $prefix = strtolower(trim((string)$prefix, " \t\n\r\0\x0B\\"));
            if ($prefix !== '') {
                $ignored[] = $prefix;
            }
        }

        if ($ignored === []) {
            return false;
        }

        foreach ($tables as $table) {
            foreach ($ignored as $prefix) {
                if (str_starts_with($table, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Whether the model class belongs to an ignored observer namespace.
     *
     * @param string $class Fully-qualified model class name.
     * @return bool True when model operations must not be recorded.
     */
    public static function isIgnoredModel(string $class): bool
    {
        $class = ltrim(strtolower(trim($class)), '\\');
        if ($class === '') {
            return false;
        }

        $ignored = [];
        foreach ((array)Configure::read('DebugBar.ignored_model_namespaces', []) as $namespace) {
            $namespace = strtolower(trim((string)$namespace, " \t\n\r\0\x0B\\")) . '\\';
            if ($namespace !== '\\') {
                $ignored[] = $namespace;
            }
        }

        foreach ($ignored as $namespace) {
            if (str_starts_with($class . '\\', $namespace)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extracts lowercase table names referenced by the statement.
     *
     * @param string $sql Raw or interpolated statement text.
     * @return list<string> Unique table names, schema prefix stripped.
     */
    public static function referencedTables(string $sql): array
    {
        if (
            !preg_match_all(
                '/\b(?:' . self::TABLE_KEYWORDS . ')\s+(?:[`"\'\[]?)([a-zA-Z0-9_.]+)/i',
                $sql,
                $matches,
            )
        ) {
            return [];
        }

        $tables = [];
        foreach ($matches[1] as $name) {
            $parts = explode('.', trim($name, '`"\'[]'));
            $table = strtolower(end($parts));
            if ($table !== '') {
                $tables[$table] = true;
            }
        }

        return array_keys($tables);
    }
}
