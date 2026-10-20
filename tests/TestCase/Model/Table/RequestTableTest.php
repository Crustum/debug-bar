<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Model\Table;

use Cake\Core\Configure;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;

/**
 * Tests for request table.
 *
 * @ported-from \DebugKit\Test\TestCase\Model\Table\RequestTableTest
 */
class RequestTableTest extends TestCase
{
    /**
     * Setup
     *
     * Skip tests on SQLite as SQLite complains when tables are changed while a connection is open.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $connection = ConnectionManager::get('test');
        $this->skipIf($connection->getDriver() instanceof Sqlite, 'Schema insertion/removal breaks SQLite');
    }

    /**
     * test that schema is created on-demand.
     *
     * @return void
     */
    public function testInitializeCreatesSchema(): void
    {
        $connection = ConnectionManager::get('test');
        $stmt = $connection->execute('DROP TABLE IF EXISTS panels');
        $stmt->closeCursor();

        $stmt = $connection->execute('DROP TABLE IF EXISTS requests');
        $stmt->closeCursor();

        $this->fetchTable('Crustum/DebugBar.Requests');
        $this->fetchTable('Crustum/DebugBar.Panels');

        $schema = $connection->getSchemaCollection();
        $this->assertContains('requests', $schema->listTables());
        $this->assertContains('panels', $schema->listTables());
    }

    /**
     * Test the recent finder.
     *
     * @return void
     */
    public function testFindRecent(): void
    {
        $table = $this->fetchTable('Crustum/DebugBar.Requests');
        $query = $table->find('recent');
        $this->assertSame(10, $query->clause('limit'));
        $this->assertNotEmpty($query->clause('order'));
    }

    /**
     * Test the garbage collect.
     *
     * @return void
     */
    public function testGc(): void
    {
        /** @var \PHPUnit\Framework\MockObject\MockObject&\DebugBar\Model\Table\RequestsTable $requestsTableMock */
        $requestsTableMock = $this->getMockForModel('Crustum/DebugBar.Requests', ['shouldGc']);
        $requestsTableMock
            ->expects($this->atLeastOnce())
            ->method('shouldGc')
            ->willReturn(true);

        $data = array_fill(0, 10, [
            'url' => '/tasks/add',
            'content_type' => 'text/html',
            'status_code' => 200,
            'requested_at' => '2014-08-21 7:41:12',
        ]);
        $requests = $requestsTableMock->newEntities($data);
        $this->assertNotFalse($requestsTableMock->saveMany($requests));

        $count = $requestsTableMock->find()->count();
        $this->assertGreaterThanOrEqual(10, $count);

        Configure::write('DebugBar.requestCount', 5);
        $requestsTableMock->gc();

        $count = $requestsTableMock->find()->count();
        $this->assertSame(5, $count);
    }
}
