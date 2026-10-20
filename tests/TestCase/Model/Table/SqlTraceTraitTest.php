<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Model\Table;

use Cake\Core\Configure;
use Cake\ORM\Locator\LocatorAwareTrait;
use Cake\TestSuite\TestCase;

/**
 * Tests for SqlTraceTrait debugging comments.
 *
 * @ported-from \DebugKit\Test\TestCase\Model\Table\SqlTraceTraitTest
 */
class SqlTraceTraitTest extends TestCase
{
    use LocatorAwareTrait;

    /**
     * Fixtures
     */
    public array $fixtures = [
        'plugin.Crustum/DebugBar.Requests',
        'plugin.Crustum/DebugBar.Panels',
    ];

    /**
     * Table names.
     */
    public array $tables = [
        'Crustum/DebugBar.Panels',
        'Crustum/DebugBar.Requests',
    ];

    protected bool $debug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->debug = Configure::read('App.debug', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Configure::write('App.debug', $this->debug);
    }

    /**
     * Verify file name when calling find()
     */
    public function testFind(): void
    {
        foreach ($this->tables as $table) {
            $table = $this->fetchTable($table);
            $sql = $table->find()->select(['id'])->sql();
            $this->assertTrue(str_contains($sql, basename(__FILE__)), 'Expected file: ' . $sql);
        }
    }

    /**
     * Verify file name when calling query()/select()
     */
    public function testQuery(): void
    {
        foreach ($this->tables as $table) {
            $table = $this->fetchTable($table);
            $sql = $table->query()->sql();
            $this->assertTrue(str_contains($sql, basename(__FILE__)), 'Expected file: ' . $sql);
        }
    }

    /**
     * Verify file name when calling update()
     */
    public function testUpdate(): void
    {
        foreach ($this->tables as $table) {
            $table = $this->fetchTable($table);
            $sql = $table->updateQuery()->set(['title' => 'fooBar'])->sql();
            $this->assertTrue(str_contains($sql, basename(__FILE__)), 'Expected file: ' . $sql);
        }
    }

    /**
     * Verify file name when calling delete()
     */
    public function testDelete(): void
    {
        foreach ($this->tables as $table) {
            $table = $this->fetchTable($table);
            $sql = $table->deleteQuery()->where(['title' => 'fooBar'])->sql();
            $this->assertTrue(str_contains($sql, basename(__FILE__)), 'Expected file: ' . $sql);
        }
    }
}
