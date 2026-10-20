<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Datasource\Paging\PaginatedResultSet;
use Cake\Datasource\ResultSetDecorator;
use Cake\Event\Event;
use Cake\ORM\Entity;
use Cake\TestSuite\TestCase;
use Cake\View\ViewVarsTrait;
use Crustum\DebugBar\Panel\VariablesPanel;
use Crustum\DebugBar\Service\NodeFlattener;
use PDO;
use stdClass;
use TestApp\Form\TestForm;

/**
 * Class VariablesPanelTest
 *
 * @ported-from \DebugKit\Test\TestCase\Panel\VariablesPanelTest
 */
class VariablesPanelTest extends TestCase
{
    /**
     * @var VariablesPanel
     */
    protected $panel;

    /**
     * set up
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->panel = new VariablesPanel();
    }

    /**
     * Teardown method.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->panel);
    }

    /**
     * test shutdown
     *
     * @return void
     */
    public function testShutdown(): void
    {
        $requests = $this->getTableLocator()->get('Requests');
        $query = $requests->find('all');
        $result = $requests->find()->all();
        $update = $requests->updateQuery();
        $debugInfoException = $requests->selectQuery()->contain('NonExistentAssociation');

        $unserializableDebugInfo = new class extends stdClass {
            public function __debugInfo()
            {
                $unserializable = new stdClass();
                $unserializable->pdo = new PDO('sqlite::memory:');

                return ['unserializable' => $unserializable];
            }
        };

        $resource = fopen('data:text/plain;base64,', 'r');

        $controller = new class {
            use ViewVarsTrait;
        };
        $vars = [
            'resource' => $resource,
            'unserializableDebugInfo' => $unserializableDebugInfo,
            'debugInfoException' => $debugInfoException,
            'updateQuery' => $update,
            'query' => $query,
            'result set' => $result,
            'string' => 'yes',
            'array' => ['some' => 'key'],
            'notSerializableForm' => new TestForm(),
            'emptyEntity' => $requests->newEmptyEntity(),
        ];
        $controller->viewBuilder()->setVars($vars);
        $event = new Event('Controller.shutdown', $controller);
        $this->panel->shutdown($event);
        $output = $this->panel->data();

        $this->assertIsArray($output);
        $expected = array_keys($vars);
        sort($expected, SORT_NATURAL | SORT_FLAG_CASE);
        $this->assertEquals($expected, array_keys($output['variables']));
    }

    /**
     * Cyclic entity graphs (user->role->user) must not hang shutdown.
     *
     * The error walk used to recurse unboundedly over revisited
     * instances, stalling php-cgi until the memory limit killed it.
     *
     * @return void
     */
    public function testShutdownTerminatesOnCyclicEntities(): void
    {
        $user = new Entity(['name' => 'u']);
        $role = new Entity(['name' => 'r']);
        $user->set('roles', [$role]);
        $role->set('users', [$user]);
        $user->setError('name', ['Name is required']);

        $controller = new class {
            use ViewVarsTrait;
        };
        $controller->viewBuilder()->setVars(['user' => $user]);
        $event = new Event('Controller.shutdown', $controller);
        $this->panel->shutdown($event);
        $output = $this->panel->data();

        $this->assertIsArray($output);
        $this->assertArrayHasKey('user', $output['variables']);
        $this->assertSame(['name' => ['Name is required']], $output['errors']['user']);
    }

    /**
     * Paginated result sets must keep entity fields after normalization.
     *
     * The export walk spends one depth level per wrapper, so entities wrapped
     * in `PaginatedResultSet` → `ResultSet` landed exactly on the depth cap
     * and flattened to bare `__class` markers the tree renders as `[]`.
     *
     * @return void
     */
    public function testShutdownKeepsPaginatedEntityFields(): void
    {
        $entities = [
            new Entity(['id' => 1, 'title' => 'first'], ['markNew' => false]),
            new Entity(['id' => 2, 'title' => 'second'], ['markNew' => false]),
        ];
        $paginated = new PaginatedResultSet(
            new ResultSetDecorator($entities),
            [
                'count' => 2,
                'totalCount' => 2,
                'perPage' => 20,
                'pageCount' => 1,
                'currentPage' => 1,
                'hasPrevPage' => false,
                'hasNextPage' => false,
            ],
        );

        $controller = new class {
            use ViewVarsTrait;
        };
        $controller->viewBuilder()->setVars(['sources' => $paginated]);
        $event = new Event('Controller.shutdown', $controller);
        $this->panel->shutdown($event);
        $output = $this->panel->data();

        $plain = NodeFlattener::toPlain($output['variables']['sources']);
        $this->assertSame(1, $plain['items'][0]['id']);
        $this->assertSame('first', $plain['items'][0]['title']);
        $this->assertSame(2, $plain['pagingParams']['totalCount']);
    }
}
