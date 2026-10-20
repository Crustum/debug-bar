<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Datasource\ConnectionManager;
use Cake\ORM\Query\SelectQuery;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\DebugSql;
use TestApp\Stub\DebugSqlStub;

/**
 * Test the debugging SQL
 *
 * @ported-from \DebugKit\Test\TestCase\DebugSqlTest
 */
class DebugSqlTest extends TestCase
{
    /**
     * @var \Cake\Database\Connection
     */
    public $connection;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = ConnectionManager::get('test');
    }

    /**
     * Tests that a SQL string is outputted in a formatted and
     * highlighted fashion in a CLI environment.
     */
    public function testSqlCli(): void
    {
        $query = $this->newQuery()->select(['panels.id']);

        ob_start();
        $this->assertSame($query, DebugSql::sql($query));
        $result = ob_get_clean();

        $expected = <<<EXPECTED
%s (line %d)
########## DEBUG ##########
[37mSELECT[0m
EXPECTED;
        $expected = sprintf($expected, str_replace(ROOT, '', __FILE__), __LINE__ - 8);
        $this->assertTextContains($expected, $result);
    }

    /**
     * Tests that a SQL string is outputted as HTML in a CLI
     * environment.
     */
    public function testSqlHtmlOnCli(): void
    {
        $query = $this->newQuery()->select(['panels.id']);

        ob_start();
        $this->assertSame($query, DebugSql::sql($query, true, true));
        $result = strip_tags(ob_get_clean());
        $result = preg_replace("/[\n\r]/", '', $result);

        $this->assertStringContainsString(sprintf('%s (line %s)', str_replace(ROOT, '', __FILE__), __LINE__ - 4), $result);
        $this->assertStringContainsString('SELECT  panels.id AS', $result);
        $this->assertStringContainsString('panels__id', $result);
        $this->assertStringContainsString('FROM  panels panels', $result);
    }

    /**
     * Tests that a SQL string is outputted as HTML in a non-CLI
     * environment.
     */
    public function testSqlHtml(): void
    {
        $query = $this->newQuery()->select(['panels.id']);

        ob_start();
        DebugSqlStub::$isCli = false;
        $this->assertSame($query, DebugSqlStub::sql($query, true, true));
        DebugSqlStub::$isCli = true;
        $result = ob_get_clean();

        $expected = <<<EXPECTED
<div class="cake-debug-output">
<span><strong>%s</strong> (line <strong>%d</strong>)</span>
<pre class="cake-debug">
<span style="font-weight:bold;">SELECT</span>
EXPECTED;
        $expected = sprintf($expected, str_replace(ROOT, '', __FILE__), __LINE__ - 10);
        $this->assertTextContains(str_replace("\r", '', $expected), str_replace("\r", '', $result));
    }

    /**
     * Tests that a SQL string is outputted as plain text in a non-CLI
     * environment.
     */
    public function testSqlPlain(): void
    {
        $query = $this->newQuery()->select(['panels.id']);

        ob_start();
        DebugSqlStub::$isCli = false;
        $this->assertSame($query, DebugSqlStub::sql($query, true, false));
        DebugSqlStub::$isCli = true;
        $result = ob_get_clean();

        $expectedHtml = <<<EXPECTED
%s (line %s)
########## DEBUG ##########
SELECT
EXPECTED;

        $expected = sprintf($expectedHtml, str_replace(ROOT, '', __FILE__), __LINE__ - 10);
        $this->assertTextContains(str_replace("\r", '', $expected), str_replace("\r", '', $result));
    }

    /**
     * Creates a Query object for testing.
     *
     * @return \Cake\ORM\Query\SelectQuery
     */
    private function newQuery(): SelectQuery
    {
        return $this->fetchTable('panels')->selectQuery();
    }
}
