<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the HTTP client inspector workspace.
 */
class HttpClientPanelTest extends TestCase
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
     * Rows filter by state and the detail shows request/response tabs.
     *
     * @return void
     */
    public function testHttpRowsWithDetailTabs(): void
    {
        $this->seedRequest([
            'HttpClient' => [
                'requests' => [
                    [
                        'key' => 'http-1',
                        'method' => 'GET',
                        'scheme' => 'https',
                        'host' => 'example.com',
                        'port' => null,
                        'path' => '/api/items',
                        'query' => 'page=2',
                        'request_headers' => ['Accept' => 'application/json'],
                        'request_body' => '',
                        'request_truncated' => false,
                        'failed' => false,
                        'slow' => false,
                        'attention' => false,
                        'status' => 200,
                        'status_label' => '200 OK',
                        'list_status_label' => '200',
                        'duration_ms' => 12.5,
                        'duration_label' => '12.5 ms',
                        'timing_summary' => '12.50 ms',
                        'content_type' => 'application/json',
                        'response_size' => 11,
                        'response_size_label' => '11 B',
                        'request_body_size_label' => '—',
                        'response_body_size_label' => '11 B',
                        'request_body_pretty' => '',
                        'request_body_language' => 'http',
                        'response_body_pretty' => "{\n    \"ok\": true\n}",
                        'response_body_language' => 'json',
                        'url' => 'https://example.com/api/items?page=2',
                        'search' => 'get https://example.com/api/items?page=2 200',
                        'source' => 'src/Service/Catalog.php:30',
                        'callsite_label' => 'src/Service/Catalog.php:30',
                        'has_source' => true,
                        'stack' => [],
                        'curl' => "curl -X GET 'https://example.com/api/items?page=2'",
                        'response' => [
                            'status' => 200,
                            'headers' => ['Content-Type' => 'application/json'],
                            'body' => '{"ok":true}',
                            'truncated' => false,
                        ],
                    ],
                    [
                        'key' => 'http-2',
                        'method' => 'POST',
                        'scheme' => 'https',
                        'host' => 'example.com',
                        'port' => null,
                        'path' => '/down',
                        'query' => '',
                        'request_headers' => [],
                        'request_body' => '',
                        'request_truncated' => false,
                        'failed' => true,
                        'slow' => false,
                        'attention' => true,
                        'status' => 0,
                        'status_label' => 'Failed',
                        'list_status_label' => 'Failed',
                        'duration_ms' => 0.0,
                        'duration_label' => '0 µs',
                        'timing_summary' => '0.00 ms',
                        'content_type' => '',
                        'response_size' => 0,
                        'response_size_label' => '0 B',
                        'request_body_size_label' => '—',
                        'response_body_size_label' => '—',
                        'url' => 'https://example.com/down',
                        'search' => 'post https://example.com/down failed',
                        'source' => '',
                        'callsite_label' => null,
                        'has_source' => false,
                        'stack' => [],
                        'curl' => "curl -X POST 'https://example.com/down'",
                        'response' => null,
                    ],
                ],
                'summary' => ['count' => 2, 'slow' => 0, 'failed' => 1, 'total_ms' => 12.5, 'total_label' => '12.5 ms'],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'HttpClient'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('data-dk-http-panel', $body);
        $this->assertStringContainsString('data-dk-http-entry', $body);
        $this->assertStringContainsString('data-dk-http-detail="http-1"', $body);
        $this->assertStringContainsString('data-dk-select="http-client-filter"', $body);
        $this->assertStringContainsString('data-dk-sort="http-client:duration"', $body);
        $this->assertStringContainsString('200 OK', $body);
        $this->assertStringContainsString('Copy URL', $body);
        $this->assertStringContainsString('Copy body', $body);
        $this->assertStringContainsString('Copy safe cURL', $body);
        $this->assertStringContainsString('Response headers', $body);
        $this->assertStringContainsString('Content type', $body);
        $this->assertStringContainsString('Response size', $body);
        $this->assertStringContainsString('Failed', $body);
        $this->assertStringContainsString('data-dk-tab-panel="http-1:request"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="http-1:response"', $body);
    }

    /**
     * @return void
     */
    public function testEmptyHttpRendersEmptyState(): void
    {
        $this->seedRequest([
            'HttpClient' => ['requests' => [], 'summary' => ['count' => 0, 'slow' => 0, 'failed' => 0]],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'HttpClient'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $this->assertStringContainsString('No HTTP client requests', (string)$this->_response->getBody());
    }
}
