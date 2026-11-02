<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\IgnoredOperations;

/**
 * IgnoredOperations test case.
 */
class IgnoredOperationsTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    protected array $backup = [];

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->backup = [
            'tables' => Configure::read('DebugBar.ignored_query_tables'),
            'models' => Configure::read('DebugBar.ignored_model_namespaces'),
        ];
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::write('DebugBar.ignored_query_tables', $this->backup['tables']);
        Configure::write('DebugBar.ignored_model_namespaces', $this->backup['models']);
        parent::tearDown();
    }

    /**
     * Tables behind the configured prefixes are ignored.
     *
     * @return void
     */
    public function testIgnoredQueryTables(): void
    {
        Configure::write('DebugBar.ignored_query_tables', ['speculum_']);

        $this->assertTrue(IgnoredOperations::isIgnoredQuery(
            "INSERT INTO speculum_entries (uuid) VALUES ('abc')",
        ));
        $this->assertTrue(IgnoredOperations::isIgnoredQuery(
            'SELECT * FROM `speculum_entries_tags` WHERE entry_uuid = ?',
        ));
        $this->assertTrue(IgnoredOperations::isIgnoredQuery(
            'UPDATE "SPECULUM_MONITORING" SET count = 1',
        ));
        $this->assertTrue(IgnoredOperations::isIgnoredQuery(
            'INSERT INTO app.speculum_entries (uuid) VALUES (1)',
        ));

        $this->assertFalse(IgnoredOperations::isIgnoredQuery('BEGIN'));
        $this->assertFalse(IgnoredOperations::isIgnoredQuery('COMMIT'));
        $this->assertFalse(IgnoredOperations::isIgnoredQuery(
            'SELECT * FROM posts WHERE id = 1',
        ));
        $this->assertFalse(IgnoredOperations::isIgnoredQuery(
            "INSERT INTO notifications (title) VALUES ('speculum digest')",
        ));
    }

    /**
     * Empty configuration ignores nothing.
     *
     * @return void
     */
    public function testEmptyConfigurationIgnoresNothing(): void
    {
        Configure::write('DebugBar.ignored_query_tables', []);
        Configure::write('DebugBar.ignored_model_namespaces', []);

        $this->assertFalse(IgnoredOperations::isIgnoredQuery(
            'INSERT INTO speculum_entries (uuid) VALUES (1)',
        ));
        $this->assertFalse(IgnoredOperations::isIgnoredModel(
            'Crustum\\Speculum\\Model\\Table\\SpeculumEntriesTable',
        ));
    }

    /**
     * Models under ignored namespaces are ignored.
     *
     * @return void
     */
    public function testIgnoredModelNamespaces(): void
    {
        Configure::write('DebugBar.ignored_model_namespaces', ['Crustum\\Speculum\\']);

        $this->assertTrue(IgnoredOperations::isIgnoredModel(
            'Crustum\\Speculum\\Model\\Table\\SpeculumEntriesTable',
        ));
        $this->assertTrue(IgnoredOperations::isIgnoredModel(
            '\\Crustum\\Speculum\\Model\\Table\\PostsTable',
        ));
        $this->assertFalse(IgnoredOperations::isIgnoredModel(
            'App\\Model\\Table\\PostsTable',
        ));
        $this->assertFalse(IgnoredOperations::isIgnoredModel(''));
    }
}
