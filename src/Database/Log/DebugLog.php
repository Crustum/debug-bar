<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Database\Log;

use Cake\Database\Log\LoggedQuery;
use Crustum\DebugBar\Service\AppPath;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Stringable;

/**
 * DebugBar Query logger.
 *
 * This logger decorates the existing logger if it exists,
 * and stores log messages internally so they can be displayed
 * or stored for future use.
 *
 * @ported-from \DebugKit\Database\Log\DebugLog
 */
class DebugLog extends AbstractLogger
{
    /**
     * Logs from the current request.
     *
     * @var array
     */
    protected array $_queries = [];

    /**
     * Decorated logger.
     *
     * @var \Psr\Log\LoggerInterface|null
     */
    protected ?LoggerInterface $_logger = null;

    /**
     * Name of the connection being logged.
     *
     * @var string
     */
    protected string $_connectionName;

    /**
     * Total time (ms) of all queries
     *
     * @var float
     */
    protected float $_totalTime = 0;

    /**
     * Set to true to capture schema reflection queries
     * in the SQL log panel.
     *
     * @var bool
     */
    protected bool $_includeSchema = false;

    /**
     * Whether a transaction is currently open or not.
     *
     * @var bool
     */
    protected bool $inTransaction = false;

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface|null $logger The logger to decorate and spy on.
     * @param string $name The name of the connection being logged.
     * @param bool $includeSchema Whether or not schema reflection should be included.
     */
    public function __construct(?LoggerInterface $logger, string $name, bool $includeSchema = false)
    {
        $this->_logger = $logger;
        $this->_connectionName = $name;
        $this->_includeSchema = $includeSchema;
    }

    /**
     * Set the schema include flag.
     *
     * @param bool $value Set
     * @return $this
     */
    public function setIncludeSchema(bool $value)
    {
        $this->_includeSchema = $value;

        return $this;
    }

    /**
     * Get the connection name.
     *
     * @return string
     */
    public function name(): string
    {
        return $this->_connectionName;
    }

    /**
     * Get the stored logs.
     *
     * @return array
     */
    public function queries(): array
    {
        return $this->_queries;
    }

    /**
     * Drops captured queries (long-lived workers and tests).
     *
     * @return void
     */
    public function clearQueries(): void
    {
        $this->_queries = [];
        $this->_totalTime = 0;
        $this->inTransaction = false;
    }

    /**
     * Get the total time
     *
     * @return float
     */
    public function totalTime(): float
    {
        return $this->_totalTime;
    }

    /**
     * @inheritDoc
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        /** @var \Cake\Database\Log\LoggedQuery|object|null $query */
        $query = $context['query'] ?? null;

        if ($this->_logger instanceof LoggerInterface) {
            $this->_logger->log($level, $message, $context);
        }

        // This specific to Elastic Search
        if (!$query instanceof LoggedQuery && isset($context['request']) && isset($context['response'])) {
            $took = $context['response']['took'] ?? 0;
            $this->_totalTime += $took;

            $this->_queries[] = [
                'query' => json_encode([
                    'method' => $context['request']['method'],
                    'path' => $context['request']['path'],
                    'data' => $context['request']['data'],
                ], JSON_PRETTY_PRINT),
                'took' => $took,
                'rows' => $context['response']['hits']['total']['value'] ?? $context['response']['hits']['total'] ?? 0,
                'inTransaction' => $this->inTransaction,
                'isCommitOrRollback' => false,
                'role' => '',
            ];

            return;
        }

        if (
            !$query instanceof LoggedQuery ||
            ($this->_includeSchema === false && $this->isSchemaQuery($query))
        ) {
            return;
        }

        $data = $query->jsonSerialize();

        $this->_totalTime += $data['took'];

        $sql = (string)$query;
        $isBegin = $sql === 'BEGIN';
        $isCommitOrRollback = $sql === 'COMMIT' || $sql === 'ROLLBACK';

        if ($isBegin) {
            $this->inTransaction = true;
        }

        $now = microtime(true);
        $context = $query->getContext();
        $driver = $context['driver'] ?? null;
        $callsite = $this->captureCallsite();

        $this->_queries[] = [
            'query' => $sql,
            'took' => $data['took'],
            'rows' => $data['numRows'],
            'inTransaction' => $this->inTransaction,
            'isCommitOrRollback' => $isCommitOrRollback,
            'role' => $context['role'] ?? '',
            'params' => $data['params'] ?? [],
            'driver' => is_object($driver) ? (new ReflectionClass($driver))->getShortName() : null,
            'connection' => $this->_connectionName,
            'start' => $now - ((float)$data['took'] / 1000),
            'end' => $now,
            'error' => $data['error'] ?? null,
            'callsite' => $callsite['callsite'],
            'stack' => $callsite['stack'],
        ];

        if ($isCommitOrRollback) {
            $this->inTransaction = false;
        }
    }

    /**
     * Captures the first application frame plus a short stack.
     *
     * Vendor frames are recognized slash-agnostically (`AppPath`) so Windows
     * hosts do not promote logger/framework frames to callsites. The trace
     * goes deep enough to reach past the ORM/driver chain; the stored stack
     * keeps application frames (innermost first) with a raw fallback when a
     * request never leaves vendor code. Arguments are never captured and
     * everything returned is serialization-safe.
     *
     * @return array{callsite: array{file: string|null, line: int|null}, stack: array<int, array{file: string|null, line: int|null, function: string, app: bool}>}
     */
    protected function captureCallsite(): array
    {
        $frames = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 40);
        $ordered = [];
        $callsite = ['file' => null, 'line' => null];
        foreach ($frames as $frame) {
            $file = $frame['file'] ?? null;
            if ($file === __FILE__) {
                continue;
            }

            $isApp = AppPath::isApp(is_string($file) ? $file : null);
            if ($callsite['file'] === null && $isApp) {
                $callsite = ['file' => $file, 'line' => isset($frame['line']) ? (int)$frame['line'] : null];
            }

            $function = (string)$frame['function'];
            if (isset($frame['class'])) {
                $function = $frame['class'] . ($frame['type'] ?? '::') . $function;
            }

            $ordered[] = [
                'file' => $file,
                'line' => isset($frame['line']) ? (int)$frame['line'] : null,
                'function' => $function,
                'app' => $isApp,
            ];
        }

        $appFrames = array_values(array_filter(
            $ordered,
            static fn(array $frame): bool => $frame['app'],
        ));

        $stack = $appFrames !== [] ? array_slice($appFrames, 0, 12) : array_slice($ordered, 0, 6);

        return ['callsite' => $callsite, 'stack' => $stack];
    }

    /**
     * Sniff SQL statements for things only found in schema reflection.
     *
     * @param \Cake\Database\Log\LoggedQuery $query The query to check.
     * @return bool
     */
    protected function isSchemaQuery(LoggedQuery $query): bool
    {
        $querystring = $query->jsonSerialize()['query'];

        return // Multiple engines
            str_contains($querystring, 'FROM information_schema') ||
            // Postgres
            str_contains($querystring, 'FROM pg_catalog') ||
            // MySQL
            str_starts_with($querystring, 'SHOW TABLE') ||
            str_starts_with($querystring, 'SHOW FULL COLUMNS') ||
            str_starts_with($querystring, 'SHOW INDEXES') ||
            // Sqlite
            str_contains($querystring, 'FROM sqlite_master') ||
            str_starts_with($querystring, 'PRAGMA') ||
            // Sqlserver
            str_contains($querystring, 'FROM INFORMATION_SCHEMA') ||
            str_contains($querystring, 'FROM sys.');
    }
}
