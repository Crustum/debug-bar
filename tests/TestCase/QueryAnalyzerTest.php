<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\QueryAnalyzer;

/**
 * Covers query fingerprinting, grouping and flags.
 */
class QueryAnalyzerTest extends TestCase
{
    /**
     * @return void
     */
    public function testNormalizeReplacesLiterals(): void
    {
        $this->assertSame(
            'SELECT * FROM users WHERE id = ? AND name = ?',
            QueryAnalyzer::normalize("SELECT  *  FROM users WHERE id = 42 AND name = 'adm'"),
        );
        $this->assertSame(
            'SELECT * FROM t WHERE id IN (…)',
            QueryAnalyzer::normalize('SELECT * FROM t WHERE id IN (1, 2, 3)'),
        );
    }

    /**
     * Quoted identifiers survive normalization; unrelated tables never merge.
     *
     * @return void
     */
    public function testNormalizeKeepsQuotedIdentifiers(): void
    {
        $this->assertSame(
            'SELECT "CourseTypes"."id" AS "CourseTypes__id" FROM "course_types" "CourseTypes" WHERE "CourseTypes"."id" = ?',
            QueryAnalyzer::normalize('SELECT "CourseTypes"."id" AS "CourseTypes__id" FROM "course_types" "CourseTypes" WHERE "CourseTypes"."id" = ?'),
        );
        $this->assertNotSame(
            QueryAnalyzer::fingerprint('SELECT "a"."id" FROM "a" "A" WHERE "A"."id" = 1'),
            QueryAnalyzer::fingerprint('SELECT "b"."id" FROM "b" "B" WHERE "B"."id" = 1'),
        );
    }

    /**
     * @return void
     */
    public function testFingerprintGroupsVariants(): void
    {
        $this->assertSame(
            QueryAnalyzer::fingerprint('SELECT * FROM users WHERE id = 1'),
            QueryAnalyzer::fingerprint('SELECT * FROM users WHERE id = 2'),
        );
        $this->assertNotSame(
            QueryAnalyzer::fingerprint('SELECT * FROM users WHERE id = 1'),
            QueryAnalyzer::fingerprint('SELECT * FROM orders WHERE id = 1'),
        );
    }

    /**
     * @return void
     */
    public function testQueryTypeReadsAndWrites(): void
    {
        $this->assertSame('read', QueryAnalyzer::queryType('SELECT 1'));
        $this->assertSame('read', QueryAnalyzer::queryType('  with x as (select 1) select * from x'));
        $this->assertSame('write', QueryAnalyzer::queryType('UPDATE users SET name = ?'));
        $this->assertSame('write', QueryAnalyzer::queryType('BEGIN'));
    }

    /**
     * @return void
     */
    public function testPresentGroupsRepeatedAndFlagsSlow(): void
    {
        $analyzer = new QueryAnalyzer(['slow_query_ms' => 100]);
        $records = $analyzer->present([
            ['sql' => 'SELECT * FROM users WHERE id = 1', 'took' => 5, 'params' => [1]],
            ['sql' => 'SELECT * FROM users WHERE id = 2', 'took' => 250, 'params' => [2]],
            ['sql' => 'UPDATE users SET name = ? WHERE id = 3', 'took' => 5, 'params' => ['x', 3]],
        ]);

        $this->assertCount(2, $records);
        [$group, $single] = $records;
        $this->assertTrue($group['repeated']);
        $this->assertSame(2, $group['count']);
        $this->assertTrue($group['slow']);
        $this->assertTrue($group['attention']);
        $this->assertFalse($group['likely_n_plus_one']);
        $this->assertCount(2, $group['executions']);
        $this->assertFalse($single['repeated']);
        $this->assertSame('write', $single['query_type']);
        $this->assertSame('read', $group['query_type']);
        // List rows show the normalized shape; executions keep raw SQL.
        $this->assertSame('SELECT * FROM users WHERE id = ?', $group['sql']);
        $this->assertSame('SELECT * FROM users WHERE id = 1', $group['executions'][0]['sql']);
    }

    /**
     * @return void
     */
    public function testPresentBuildsBoundDisplaySql(): void
    {
        $analyzer = new QueryAnalyzer(['slow_query_ms' => 100]);
        $records = $analyzer->present([
            [
                'sql' => 'SELECT * FROM users WHERE id = ?',
                'took' => 5,
                'params' => [42],
                'bindings' => [42],
                'bindings_complete' => true,
                'runnable_sql' => 'SELECT * FROM users WHERE id = 42',
                'runnable_available' => true,
            ],
            [
                'sql' => 'UPDATE users SET name = ?',
                'took' => 5,
                'params' => ['x'],
                'bindings' => ['[redacted]'],
                'bindings_complete' => false,
                'runnable_sql' => null,
                'runnable_available' => false,
            ],
        ]);

        $this->assertCount(2, $records);
        [$read, $write] = $records;
        $execution = $read['executions'][0];
        $this->assertSame('SELECT * FROM users WHERE id = 42', $execution['display_sql']);
        $this->assertStringContainsString("\n", (string)$execution['formatted_sql']);
        $this->assertStringContainsString('id = 42', (string)$execution['formatted_sql']);
        $this->assertTrue($execution['display_sql_complete']);
        $this->assertTrue($execution['explain_available']);
        $this->assertSame('read', $read['query_type']);

        $writeExecution = $write['executions'][0];
        $this->assertSame('UPDATE users SET name = ?', $writeExecution['display_sql']);
        $this->assertFalse($writeExecution['display_sql_complete']);
        $this->assertFalse($writeExecution['explain_available']);
        $this->assertSame(
            'EXPLAIN is available for read queries only.',
            $writeExecution['explain_unavailable_reason'],
        );
    }

    /**
     * @return void
     */
    public function testPresentWithoutRunnableFallsBackToRawSql(): void
    {
        $analyzer = new QueryAnalyzer(['slow_query_ms' => 100]);
        $records = $analyzer->present([
            ['sql' => 'SELECT * FROM t WHERE id = ?', 'took' => 5, 'params' => [1]],
        ]);

        $execution = $records[0]['executions'][0];
        $this->assertSame('SELECT * FROM t WHERE id = ?', $execution['display_sql']);
        $this->assertFalse($execution['display_sql_complete']);
        $this->assertFalse($execution['explain_available']);
        $this->assertSame(
            'EXPLAIN needs preserved SQL and complete bindings.',
            $execution['explain_unavailable_reason'],
        );
    }

    /**
     * @return void
     */
    public function testPresentFlagsNPlusOne(): void
    {
        $analyzer = new QueryAnalyzer(['slow_query_ms' => 10000]);
        $records = $analyzer->present([
            ['sql' => 'SELECT * FROM orders WHERE user_id = 1', 'took' => 5, 'params' => [1]],
            ['sql' => 'SELECT * FROM orders WHERE user_id = 2', 'took' => 5, 'params' => [2]],
            ['sql' => 'SELECT * FROM orders WHERE user_id = 3', 'took' => 5, 'params' => [3]],
        ]);

        $this->assertCount(1, $records);
        $this->assertTrue($records[0]['repeated']);
        $this->assertTrue($records[0]['likely_n_plus_one']);
        $this->assertFalse($records[0]['slow']);
        $this->assertTrue($records[0]['attention']);
    }

    /**
     * @return void
     */
    public function testFiltersAndSummaryCountExecutions(): void
    {
        $analyzer = new QueryAnalyzer(['slow_query_ms' => 100]);
        $records = $analyzer->present([
            ['sql' => 'SELECT 1', 'took' => 5],
            ['sql' => 'SELECT 2', 'took' => 5],
            ['sql' => 'DELETE FROM t', 'took' => 500],
        ]);
        $filters = $analyzer->filters($records);

        $this->assertSame(3, $filters['all'][1]);
        $this->assertSame(3, $filters['attention'][1]);
        $this->assertSame(2, $filters['read'][1]);
        $this->assertSame(1, $filters['write'][1]);

        $summary = $analyzer->summarize($records, 510.0);
        $this->assertSame(3, $summary['count']);
        $this->assertSame(1, $summary['slow']);
        $this->assertSame('510 ms', $summary['duration_label']);
    }

    /**
     * @return void
     */
    public function testFormatDurationUnits(): void
    {
        $this->assertSame('—', QueryAnalyzer::formatDuration(0));
        $this->assertSame('1.0 ms', QueryAnalyzer::formatDuration(1.0));
        $this->assertSame('1.50 s', QueryAnalyzer::formatDuration(1500.0));
        $this->assertSame('500 µs', QueryAnalyzer::formatDuration(0.5));
        $this->assertSame('0.9 µs', QueryAnalyzer::formatDuration(0.0009));
        $this->assertSame('0.2 µs', QueryAnalyzer::formatDuration(0.00021));
        $this->assertSame('0.0 µs', QueryAnalyzer::formatDuration(0.00004));
    }
}
