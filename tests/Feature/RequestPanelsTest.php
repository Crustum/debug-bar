<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\QueryAnalyzer;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the panels endpoint behind the on-the-fly request switch.
 */
class RequestPanelsTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
    }

    /**
     * One round trip carries facts plus every rendered panel body.
     *
     * @return void
     */
    public function testPanelsServeRenderedBodies(): void
    {
        $analyzer = new QueryAnalyzer(['slow_query_ms' => 100]);
        $records = $analyzer->present([
            [
                'sql' => 'SELECT * FROM switched WHERE id = ?',
                'took' => 5,
                'params' => [7],
                'bindings' => [7],
                'bindings_complete' => true,
                'runnable_sql' => 'SELECT * FROM switched WHERE id = 7',
                'runnable_available' => true,
                'connection' => 'test',
                'driver' => 'Sqlite',
                'callsite' => ['file' => 'src/Controller/TasksController.php', 'line' => 12],
                'stack' => [],
            ],
        ]);
        $request = $this->seedRequest(
            [
                'SqlLog' => [
                    'tables' => [],
                    'loggers' => [],
                    'items' => [],
                    'records' => $records,
                    'summary' => $analyzer->summarize($records, 5.0),
                ],
            ],
            ['url' => '/switched', 'method' => 'POST', 'status_code' => 201],
        );

        $this->get('/debug-bar/requests/panels/' . $request->id . '.json');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);

        $this->assertSame('/switched', $body['request']['url']);
        $this->assertSame('POST', $body['request']['method']);
        $this->assertSame(201, $body['request']['status_code']);
        $this->assertArrayHasKey('duration_label', $body['request']);
        $this->assertArrayHasKey('query_count', $body['request']);

        $keys = array_column($body['panels'], 'key');
        $this->assertContains('queries', $keys);
        foreach ($body['panels'] as $panel) {
            $this->assertArrayHasKey('id', $panel);
            $this->assertArrayHasKey('title', $panel);
            $this->assertArrayHasKey('summary', $panel);
            $this->assertArrayHasKey('count', $panel);
            $this->assertArrayHasKey('html', $panel);
        }

        $sqlPanel = $body['panels'][array_search('queries', $keys, true)];
        $this->assertSame(1, $sqlPanel['count']);
        // Rendered with the same element vars as the toolbar page.
        $this->assertStringContainsString('data-dk-queries', $sqlPanel['html']);
        $this->assertStringContainsString('SELECT * FROM switched WHERE id = ?', $sqlPanel['html']);
        $this->assertStringContainsString('TasksController.php:12', $sqlPanel['html']);
        $this->assertIsArray($body['recent']);
        // Dist freshness handshake for the long-lived iframe.
        $this->assertArrayHasKey('asset_version', $body);
        $this->assertIsInt($body['asset_version']);
    }

    /**
     * Unknown requests 404 instead of rendering an empty shell.
     *
     * @return void
     */
    public function testPanelsMissingRequest(): void
    {
        $this->get('/debug-bar/requests/panels/missing.json');
        $this->assertResponseCode(404);
    }

    /**
     * The embedded recent list follows the storage cap, not a literal.
     *
     * @return void
     */
    public function testRecentListFollowsStorageMaxRequests(): void
    {
        Configure::write('DebugBar.storage.max_requests', 30);
        try {
            $first = null;
            for ($i = 0; $i < 25; $i++) {
                $request = $this->seedRequest([], ['url' => '/page-' . $i]);
                $first ??= $request;
            }

            $this->get('/debug-bar/requests/panels/' . $first->id . '.json');
            $this->assertResponseOk();
            $body = json_decode((string)$this->_response->getBody(), true);
            $this->assertCount(25, $body['recent']);
        } finally {
            Configure::delete('DebugBar.storage.max_requests');
        }
    }
}
