<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Core\Configure;
use Cake\Database\Driver;
use Cake\Datasource\ConnectionManager;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\ORM\Locator\TableLocator;
use Cake\ORM\Table;
use Crustum\DebugBar\Database\Log\DebugLog;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Service\QueryAnalyzer;
use Crustum\DebugBar\Service\QueryExplainer;
use Crustum\DebugBar\Service\RedactionService;

/**
 * Provides debug information on the SQL logs and provides links to an ajax explain interface.
 *
 * @ported-from \DebugKit\Panel\SqlLogPanel
 */
class SqlLogPanel extends DebugPanel
{
    use LocatorAwareTrait;

    /**
     * Loggers connected
     *
     * @var array
     */
    protected static array $_loggers = [];

    /**
     * Reads the connected loggers (deduplicated).
     *
     * Test support for the assertions trait — panels keep owning the
     * wiring, tests only read.
     *
     * @return array<int, \Crustum\DebugBar\Database\Log\DebugLog>
     */
    public static function loggers(): array
    {
        $unique = [];
        foreach (static::$_loggers as $logger) {
            if ($logger instanceof DebugLog) {
                $unique[spl_object_id($logger)] = $logger;
            }
        }

        return array_values($unique);
    }

    /**
     * Initialize hook - configures logger.
     *
     * This will unfortunately build all the connections, but they
     * won't connect until used.
     *
     * @return void
     */
    public function initialize(): void
    {
        $configs = ConnectionManager::configured();

        foreach ($configs as $name) {
            static::addConnection($name);
        }
    }

    /**
     * Add a connection to the list of loggers.
     *
     * @param string $name The name of the connection to add.
     * @return void
     */
    public static function addConnection(string $name): void
    {
        $includeSchemaReflection = (bool)Configure::read('DebugBar.includeSchemaReflection');

        $connection = ConnectionManager::get($name);
        if ($connection->configName() === 'debug_bar') {
            return;
        }

        $driver = $connection->getDriver();

        if (!method_exists($driver, 'setLogger')) {
            return;
        }

        $logger = null;
        if ($driver instanceof Driver) {
            $logger = $driver->getLogger();
        } elseif (method_exists($connection, 'getLogger')) {
            // ElasticSearch connection holds the logger, not the Elastica Driver
            $logger = $connection->getLogger();
        }

        if ($logger instanceof DebugLog) {
            $logger->setIncludeSchema($includeSchemaReflection);
            if (!in_array($logger, static::$_loggers, true)) {
                static::$_loggers[] = $logger;
            }

            return;
        }

        $logger = new DebugLog($logger, $name, $includeSchemaReflection);

        /** @var \Cake\Database\Driver $driver */
        $driver->setLogger($logger);

        if (!in_array($logger, static::$_loggers, true)) {
            static::$_loggers[] = $logger;
        }
    }

    /**
     * Resets captured query buffers for the next request.
     *
     * Registrations stay attached to their drivers; buffers drop so
     * long-lived workers never bleed across requests.
     *
     * @return void
     */
    public static function clear(): void
    {
        foreach (static::$_loggers as $logger) {
            $logger->clearQueries();
        }
    }

    /**
     * Get the data this panel wants to store.
     *
     * Besides the legacy `tables`/`loggers` shape, every captured query is
     * flattened into `items` so the inspector, the JSON endpoint and
     * the timeline share one source. Bindings are redacted at capture time
     * per the `DebugBar.query_bindings` policy.
     *
     * @return array
     */
    public function data(): array
    {
        $redaction = new RedactionService((array)Configure::read('DebugBar'));
        $explainer = new QueryExplainer();
        $items = [];
        foreach (static::$_loggers as $logger) {
            foreach ($logger->queries() as $query) {
                $sql = (string)($query['query'] ?? '');
                $rawParams = array_values((array)($query['params'] ?? []));
                $bindings = $redaction->redactBindings($rawParams);

                $complete = $redaction->bindingsComplete($bindings);
                $runnable = $complete
                    ? $explainer->runnableStatement($logger->name(), $sql, $rawParams)
                    : null;
                $items[] = [
                    'sql' => $sql,
                    'params' => $bindings,
                    'bindings' => $bindings,
                    'bindings_complete' => $complete,
                    'runnable_sql' => $runnable,
                    'runnable_available' => is_string($runnable) && $runnable !== '',
                    'took' => (float)($query['took'] ?? 0),
                    'rows' => (int)($query['rows'] ?? 0),
                    'role' => (string)($query['role'] ?? ''),
                    'driver' => $query['driver'] ?? null,
                    'connection' => $logger->name(),
                    'inTransaction' => (bool)($query['inTransaction'] ?? false),
                    'isCommitOrRollback' => (bool)($query['isCommitOrRollback'] ?? false),
                    'callsite' => $query['callsite'] ?? null,
                    'stack' => $query['stack'] ?? [],
                    'start' => (float)($query['start'] ?? 0),
                    'end' => (float)($query['end'] ?? 0),
                ];
            }
        }

        $analyzer = new QueryAnalyzer((array)Configure::read('DebugBar'));
        $records = $analyzer->present($items);
        $totalMs = 0.0;
        foreach ($records as $record) {
            $totalMs += (float)$record['duration_ms'];
        }

        return [
            'tables' => $this->genericTableAliases(),
            'loggers' => static::$_loggers,
            'items' => $items,
            'records' => $records,
            'summary' => $analyzer->summarize($records, $totalMs),
        ];
    }

    /**
     * Lists aliases of generically-instantiated tables.
     *
     * `genericInstances()` only exists on the concrete `TableLocator`;
     * custom locators implementing `LocatorInterface` have no fallback
     * registry, so there is nothing to list instead of guessing.
     *
     * @return array<int, string>
     */
    protected function genericTableAliases(): array
    {
        $locator = $this->getTableLocator();
        if (!$locator instanceof TableLocator) {
            return [];
        }

        return array_map(
            static fn(Table $table): string => $table->getAlias(),
            $locator->genericInstances(),
        );
    }

    /**
     * Get summary data from the queries run.
     *
     * @return string
     */
    public function summary(): string
    {
        $count = 0;
        $time = 0;
        foreach (static::$_loggers as $logger) {
            $count += count($logger->queries());
            $time += $logger->totalTime();
        }

        if (!$count) {
            return '0';
        }

        return "$count / $time ms";
    }
}
