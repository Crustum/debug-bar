<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the cache inspector workspace.
 */
class CachePanelTest extends TestCase
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
     * Spy lines render as parsed rows with a selectable detail pane.
     *
     * @return void
     */
    public function testCacheRowsShowKeysWithDetail(): void
    {
        $this->seedRequest([
            'Cache' => [
                'metrics' => ['default' => ['set' => 0, 'delete' => 0, 'get hit' => 5, 'get miss' => 0]],
                'logs' => [
                    'info: :default: get `default_sessions` - 0.00069ms',
                    'info: :default: get `default_projects` - 0.00020ms',
                    'info: :default: set `default_count` - 0.00031ms',
                    'info: :default: get `default_count` - 0.00011ms',
                    'info: :default: delete `default_stale` - 0.00008ms',
                ],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Cache'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('data-dk-cache-panel', $body);
        $this->assertStringContainsString('data-dk-workspace', $body);
        $this->assertStringContainsString('default_sessions', $body);
        $this->assertStringContainsString('data-dk-cache-entry', $body);
        $this->assertStringContainsString('data-dk-cache-detail="cache-0"', $body);
        $this->assertStringContainsString('Reads', $body);
        $this->assertStringContainsString('hit rate', $body);
        $this->assertStringContainsString('data-dk-select="cache-filter"', $body);
        $this->assertStringContainsString('data-dk-search="cache"', $body);
        $this->assertStringContainsString('data-dk-list-empty', $body);
        $this->assertStringContainsString('Raw log line', $body);
    }

    /**
     * Cache stores keep the management actions.
     *
     * @return void
     */
    public function testCacheStoresExposeClearActions(): void
    {
        $this->seedRequest([
            'Cache' => [
                'metrics' => ['default' => ['set' => 0, 'delete' => 0, 'get hit' => 5, 'get miss' => 0]],
                'logs' => [],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Cache'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('Cache stores', $body);
        $this->assertStringContainsString('data-dk-cache-clear', $body);
        $this->assertStringContainsString('data-dk-cache-clear-name="default"', $body);
        $this->assertStringContainsString('toolbar/clear-cache', $body);
        $this->assertStringContainsString('>Clear data<', $body);
        $this->assertStringContainsString('data-dk-cache-clear-result', $body);
        $this->assertStringContainsString('data-dk-cache-clear-all', $body);
        $this->assertStringContainsString('toolbar/clear-all-caches', $body);
        $this->assertMatchesRegularExpression('/>\s*All\s*<\/span>.*>\s*default\s*</s', $body);
    }
}
