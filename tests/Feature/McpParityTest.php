<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\Container\Container;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Mcp\DebugBarServer;
use Crustum\DebugBar\Mcp\Tools\GetFindingsTool;
use Crustum\DebugBar\Mcp\Tools\GetPanelDataTool;
use Crustum\DebugBar\Mcp\Tools\InspectQueriesTool;
use Crustum\DebugBar\Mcp\Tools\ListRequestsTool;
use Crustum\DebugBar\Test\DebugBarTestTrait;
use Crustum\Mcp\Server\ContainerInvoker;
use Crustum\Mcp\Support\ContainerRegistry;

/**
 * Covers the MCP diagnostic tools: everything visible in the
 * browser inspector stays reachable through a bounded tool call.
 */
class McpParityTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(DebugBarServer::class) || !class_exists(ListRequestsTool::class)) {
            $this->markTestSkipped('crustum/mcp is not installed.');
        }

        // Tool invocation resolves through the MCP container invoker —
        // mirror the plugin services wiring without booting the plugin.
        $container = new Container();
        $container->addShared(ContainerInvoker::class, fn(): ContainerInvoker => new ContainerInvoker($container));
        ContainerRegistry::setInstance($container);
        $this->resetToolbarStore();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        ContainerRegistry::setInstance(null);
        parent::tearDown();
    }

    /**
     * @return string Seeded request id.
     */
    protected function seedDebugRequest(): string
    {
        $request = $this->seedRequest([
            'SqlLog' => [
                'records' => [
                    [
                        'key' => 'query-1',
                        'execution' => 1,
                        'sql' => 'SELECT * FROM articles WHERE id = 1',
                        'normalized_sql' => 'SELECT * FROM articles WHERE id = ?',
                        'connection' => 'default',
                        'driver' => 'sqlite',
                        'query_type' => 'read',
                        'duration_ms' => 12.5,
                        'duration_label' => '12.5 ms',
                        'count' => 1,
                        'repeated' => false,
                        'slow' => false,
                        'attention' => false,
                        'likely_n_plus_one' => false,
                        'search' => 'select * from articles where id = 1',
                        'source_available' => true,
                        'source_label' => 'src/Controller/ArticlesController.php:41',
                        'executions' => [
                            [
                                'execution' => 1,
                                'duration_ms' => 12.5,
                                'query_time_percent' => 100.0,
                                'bindings' => [1],
                                'rows' => 1,
                                'explain_available' => true,
                            ],
                        ],
                    ],
                ],
                'summary' => ['count' => 1, 'groups' => 1, 'repeated' => 0, 'slow' => 0],
            ],
            'Findings' => [
                'findings' => [
                    [
                        'rule_id' => 'query.slow',
                        'severity' => 'warning',
                        'panel' => 'SqlLog',
                        'summary' => 'Slow query (12.5 ms)',
                        'why' => 'A single statement took 12.5 ms.',
                        'location' => 'src/Controller/ArticlesController.php:41',
                        'next' => 'Add an index.',
                        'action' => 'Review in SqlLog',
                    ],
                ],
                'retained' => 1,
                'omitted' => 0,
            ],
        ], ['url' => '/articles/view/1', 'method' => 'GET', 'status_code' => 200]);

        return (string)$request->id;
    }

    /**
     * @return void
     */
    public function testListRequestsParity(): void
    {
        $id = $this->seedDebugRequest();

        DebugBarServer::tool(ListRequestsTool::class, ['path' => '/articles'])
            ->assertOk()
            ->assertSee('/articles/view/1')
            ->assertSee($id);
    }

    /**
     * @return void
     */
    public function testPanelDataPointerParity(): void
    {
        $id = $this->seedDebugRequest();

        DebugBarServer::tool(GetPanelDataTool::class, ['request_id' => $id, 'path' => '/panels'])
            ->assertOk()
            ->assertSee('SqlLog')
            ->assertSee('Findings');

        DebugBarServer::tool(GetPanelDataTool::class, [
            'request_id' => $id,
            'path' => '/panels/SqlLog/records/0/sql',
        ])
            ->assertOk()
            ->assertSee('SELECT * FROM articles WHERE id = 1');
    }

    /**
     * Malformed ids fail validation with a precise error.
     *
     * @return void
     */
    public function testPanelDataMalformedRequestId(): void
    {
        DebugBarServer::tool(GetPanelDataTool::class, ['request_id' => 'missing', 'path' => ''])
            ->assertSee('UUID');
    }

    /**
     * Well-formed but absent ids answer honestly missing.
     *
     * @return void
     */
    public function testPanelDataMissingRequest(): void
    {
        DebugBarServer::tool(GetPanelDataTool::class, [
            'request_id' => '00000000-0000-4000-8000-000000000000',
            'path' => '',
        ])
            ->assertOk()
            ->assertSee('not_found')
            ->assertSee('no longer stored');
    }

    /**
     * @return void
     */
    public function testFindingsParity(): void
    {
        $id = $this->seedDebugRequest();

        DebugBarServer::tool(GetFindingsTool::class, ['request_id' => $id])
            ->assertOk()
            ->assertSee('query.slow')
            ->assertSee('Add an index.');
    }

    /**
     * @return void
     */
    public function testInspectQueriesParity(): void
    {
        $id = $this->seedDebugRequest();

        DebugBarServer::tool(InspectQueriesTool::class, ['request_id' => $id, 'filter' => 'all'])
            ->assertOk()
            ->assertSee('SELECT * FROM articles')
            ->assertSee('ArticlesController');

        DebugBarServer::tool(InspectQueriesTool::class, ['request_id' => $id, 'filter' => 'slow'])
            ->assertOk()
            ->assertSee('"total":0');
    }
}
