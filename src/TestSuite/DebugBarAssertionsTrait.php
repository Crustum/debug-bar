<?php
declare(strict_types=1);

namespace Crustum\DebugBar\TestSuite;

use Cake\Datasource\ConnectionManager;
use Crustum\DebugBar\Database\Log\DebugLog;
use Crustum\DebugBar\Panel\ExceptionsPanel;
use Crustum\DebugBar\Panel\SqlLogPanel;
use Crustum\DebugBar\Service\QueryAnalyzer;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Throwable;

/**
 * DebugBar assertions trait.
 *
 * Makes performance assertions on queries captured through the SQL
 * panel loggers, wall-clock assertions on a callable, and error
 * assertions on throwables recorded through the exceptions panel.
 *
 * After adding the trait to your test case, query logging is armed for
 * every configured connection (except the toolbar store itself) and
 * only queries logged after the setup count toward the assertions,
 * allowing you to make assertions like the broadcasting trait does
 * for captured broadcasts. Note the setup hook runs before `setUp()` —
 * keep schema DDL in `setUpBeforeClass()` (or seed before measuring)
 * so setup queries do not leak into the measured window.
 *
 * Usage:
 * ```
 * class MyTest extends TestCase
 * {
 *     use DebugBarAssertionsTrait;
 *
 *     public function testIndexStaysLean(): void
 *     {
 *         $this->Articles->find()->all();
 *
 *         $this->assertQueryCountAtMost(3);
 *         $this->assertNoNPlusOne();
 *     }
 * }
 * ```
 */
trait DebugBarAssertionsTrait
{
    /**
     * Logged-query offsets per logger at setup, so only queries logged
     * after the setup count toward the assertions.
     *
     * @var array<int, int>
     */
    protected array $debugBarQueryOffsets = [];

    /**
     * Arms query capture on every configured connection.
     *
     * Reuses the SQL panel loggers (wrapping is idempotent — an already
     * wrapped driver keeps its logger) and snapshots per-logger offsets.
     * Recorded throwables are cleared too. Connections that fail to open
     * are skipped.
     *
     * @return void
     */
    #[Before]
    public function setupDebugBarAssertions(): void
    {
        foreach (ConnectionManager::configured() as $name) {
            try {
                SqlLogPanel::addConnection($name);
            } catch (Throwable) {
                continue;
            }
        }

        foreach (SqlLogPanel::loggers() as $logger) {
            $this->debugBarQueryOffsets[spl_object_id($logger)] = count($logger->queries());
        }

        ExceptionsPanel::clear();
    }

    /**
     * Drops the per-test query offsets.
     *
     * Loggers stay wrapped (reuse is idempotent); the next setup takes
     * fresh offsets.
     *
     * @return void
     */
    #[After]
    public function cleanupDebugBarAssertions(): void
    {
        $this->debugBarQueryOffsets = [];
        ExceptionsPanel::clear();
    }

    /**
     * Asserts at most `$max` queries were logged after the setup.
     *
     * @param int $max Maximum accepted query count.
     * @param string|null $connection Connection name or null for all connections.
     * @param string $message Optional assertion message.
     * @return void
     */
    public function assertQueryCountAtMost(int $max, ?string $connection = null, string $message = ''): void
    {
        $queries = $this->debugBarNewQueries($connection);
        $actual = count($queries);
        $this->assertLessThanOrEqual(
            $max,
            $actual,
            $message ?: "Expected at most {$max} queries, but {$actual} were logged"
                . ($connection !== null ? " on connection `{$connection}`" : ''),
        );
    }

    /**
     * Asserts total logged query time stays within `$ms` milliseconds.
     *
     * @param float $ms Maximum accepted total time in milliseconds.
     * @param string|null $connection Connection name or null for all connections.
     * @param string $message Optional assertion message.
     * @return void
     */
    public function assertQueryTimeAtMost(float $ms, ?string $connection = null, string $message = ''): void
    {
        $total = 0.0;
        foreach ($this->debugBarNewQueries($connection) as $query) {
            $total += (float)($query['took'] ?? 0);
        }

        $this->assertLessThanOrEqual(
            $ms,
            $total,
            $message ?: 'Expected total query time of at most ' . QueryAnalyzer::formatDuration($ms)
                . ', but took ' . QueryAnalyzer::formatDuration($total),
        );
    }

    /**
     * Asserts no query shape repeats more than `$maxRepeats` times.
     *
     * Shapes group by fingerprint (literals normalized), so a loop
     * fetching relations one by one trips the assertion while a couple
     * of legitimate repeats pass.
     *
     * @param int $maxRepeats Maximum accepted repeats per query shape.
     * @param string|null $connection Connection name or null for all connections.
     * @param string $message Optional assertion message.
     * @return void
     */
    public function assertNoNPlusOne(int $maxRepeats = 5, ?string $connection = null, string $message = ''): void
    {
        $groups = [];
        foreach ($this->debugBarNewQueries($connection) as $query) {
            $sql = (string)($query['query'] ?? '');
            if ($sql === '') {
                continue;
            }

            $fingerprint = QueryAnalyzer::fingerprint($sql);
            $groups[$fingerprint]['count'] = ($groups[$fingerprint]['count'] ?? 0) + 1;
            $groups[$fingerprint]['sql'] = $sql;
        }

        $worst = null;
        foreach ($groups as $group) {
            if ($group['count'] > $maxRepeats && ($worst === null || $group['count'] > $worst['count'])) {
                $worst = $group;
            }
        }

        $this->assertNull(
            $worst,
            $message ?: ($worst === null ? '' : "Possible N+1: query repeated {$worst['count']} times: {$worst['sql']}"),
        );
    }

    /**
     * Asserts the callable runs within `$ms` milliseconds.
     *
     * @template T
     * @param float $ms Maximum accepted duration in milliseconds.
     * @param callable(): T $callback Code under measurement.
     * @param string $message Optional assertion message.
     * @return T The callback result.
     */
    public function assertDurationAtMost(float $ms, callable $callback, string $message = ''): mixed
    {
        $start = microtime(true);
        $result = $callback();
        $took = (microtime(true) - $start) * 1000;
        $this->assertLessThanOrEqual(
            $ms,
            $took,
            $message ?: 'Expected duration of at most ' . QueryAnalyzer::formatDuration($ms)
                . ', but took ' . QueryAnalyzer::formatDuration($took),
        );

        return $result;
    }

    /**
     * Asserts no throwables were recorded after the setup.
     *
     * @param string $message Optional assertion message.
     * @return void
     */
    public function assertNoErrors(string $message = ''): void
    {
        $recorded = ExceptionsPanel::recorded();
        $classes = [];
        foreach ($recorded as $error) {
            $classes[] = $error::class;
        }

        $this->assertSame(
            [],
            $classes,
            $message ?: 'Expected no recorded errors, but got: ' . implode(', ', $classes),
        );
    }

    /**
     * Reads queries logged after the setup, optionally per connection.
     *
     * @param string|null $connection Connection name or null for all connections.
     * @return array<int, array<string, mixed>>
     */
    public function debugBarNewQueries(?string $connection = null): array
    {
        $queries = [];
        foreach (SqlLogPanel::loggers() as $logger) {
            if (!$logger instanceof DebugLog) {
                continue;
            }

            if ($connection !== null && $logger->name() !== $connection) {
                continue;
            }

            $offset = $this->debugBarQueryOffsets[spl_object_id($logger)] ?? 0;
            foreach (array_slice($logger->queries(), $offset) as $query) {
                $queries[] = (array)$query;
            }
        }

        return $queries;
    }
}
