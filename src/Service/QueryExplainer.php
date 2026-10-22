<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Database\Connection;
use Cake\Database\Driver\Mysql;
use Cake\Database\Driver\Postgres;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use ReflectionClass;
use Throwable;

/**
 * Live-only read-only EXPLAIN runner.
 *
 * Never stored, never persisted: explains the given read statement on the
 * same connection it was captured on. Anything that is not a plain
 * SELECT/WITH read is refused with an `unavailable_reason` instead of an
 * error so the UI can render the reason inline.
 */
class QueryExplainer
{
    /**
     * Explains a captured statement.
     *
     * @param string $connection Connection name from the captured item.
     * @param string $sql Captured SQL.
     * @param array $params Captured bindings.
     * @return array{plan?: array<int, mixed>, mode?: string, driver?: string, unavailable_reason?: string, error?: string}
     */
    public function explain(string $connection, string $sql, array $params = []): array
    {
        $head = strtoupper(ltrim($sql));
        if (!str_starts_with($head, 'SELECT') && !str_starts_with($head, 'WITH')) {
            return ['unavailable_reason' => 'EXPLAIN is available for read queries only.'];
        }

        try {
            $connectionObject = ConnectionManager::get($connection, false);
        } catch (Throwable $throwable) {
            return ['error' => 'Connection unavailable: ' . $throwable->getMessage()];
        }

        if (!$connectionObject instanceof Connection) {
            return ['error' => 'Connection unavailable for EXPLAIN.'];
        }

        $driver = $connectionObject->getDriver();
        $driverName = new ReflectionClass($driver)->getShortName();

        $statement = $this->runnableStatement($connection, $sql, $params);
        if ($statement === null) {
            return ['unavailable_reason' => 'EXPLAIN needs preserved SQL and complete bindings.'];
        }

        $prefix = match (true) {
            $driver instanceof Sqlite => 'EXPLAIN QUERY PLAN ',
            $driver instanceof Mysql => 'EXPLAIN ',
            $driver instanceof Postgres => 'EXPLAIN ',
            default => null,
        };
        if ($prefix === null) {
            return ['unavailable_reason' => 'EXPLAIN is not supported for driver ' . $driverName . '.'];
        }

        try {
            /** @var array<int, mixed> $rows */
            $rows = $connectionObject->execute($prefix . $statement)->fetchAll('assoc');
        } catch (Throwable $throwable) {
            return ['error' => 'EXPLAIN could not run: ' . $throwable->getMessage()];
        }

        return [
            'plan' => $rows,
            'pretty' => $this->prettyPlan($rows),
            'mode' => $driver instanceof Sqlite ? 'query-plan' : 'explain',
            'driver' => $driverName,
        ];
    }

    /**
     * Builds the runnable statement for display and EXPLAIN.
     *
     * Interpolates bindings through the connection driver quoting so the
     * inspector can show the bound query. Returns null when the connection
     * is unavailable or placeholders and bindings do not line up; callers
     * then surface `unavailable_reason` instead of guessing.
     *
     * @param string $connection Connection name from the captured item.
     * @param string $sql Captured SQL.
     * @param array $params Captured (unredacted) bindings.
     * @return string|null Runnable statement or null.
     */
    public function runnableStatement(string $connection, string $sql, array $params = []): ?string
    {
        try {
            $connectionObject = ConnectionManager::get($connection, false);
        } catch (Throwable) {
            return null;
        }

        if (!$connectionObject instanceof Connection) {
            return null;
        }

        return $this->interpolate($connectionObject, $sql, $params);
    }

    /**
     * Renders single-column plans as an indented text tree.
     *
     * Postgres text plans arrive as one `QUERY PLAN` string per row; joining
     * them keeps the server-side indentation instead of burying it in JSON.
     * Multi-column plans (MySQL, structured Postgres, SQLite) return null
     * and stay JSON in the inspector.
     *
     * @param array<int, mixed> $rows Raw plan rows.
     * @return string|null Pretty text or null.
     */
    public function prettyPlan(array $rows): ?string
    {
        if ($rows === []) {
            return null;
        }

        $lines = [];
        foreach ($rows as $row) {
            if (!is_array($row) || count($row) !== 1) {
                return null;
            }

            $value = reset($row);
            if (!is_scalar($value)) {
                return null;
            }

            $lines[] = (string)$value;
        }

        return implode("\n", $lines);
    }

    /**
     * Interpolates bindings for EXPLAIN display only.
     *
     * Returns null when placeholders and bindings do not line up so the
     * caller surfaces `unavailable_reason` instead of guessing.
     *
     * @param mixed $connection Connection object.
     * @param string $sql Captured SQL.
     * @param array $params Captured bindings.
     * @return string|null Runnable statement or null.
     */
    protected function interpolate(mixed $connection, string $sql, array $params): ?string
    {
        $positional = substr_count($sql, '?');
        if ($positional > 0) {
            $values = array_values($params);
            if (count($values) < $positional) {
                return null;
            }

            $driver = $connection->getDriver();
            $index = 0;
            $result = preg_replace_callback(
                '/\?/',
                static function () use (&$index, $values, $driver): string {
                    $value = $values[$index] ?? null;
                    $index++;

                    return $driver->quote((string)$value);
                },
                $sql,
            );

            return is_string($result) ? $result : null;
        }

        if (preg_match_all('/:([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches) > 0) {
            $driver = $connection->getDriver();
            foreach (array_unique($matches[1]) as $name) {
                if (!array_key_exists($name, $params)) {
                    return null;
                }

                $sql = str_replace(':' . $name, $driver->quote((string)$params[$name]), $sql);
            }
        }

        return $sql;
    }
}
