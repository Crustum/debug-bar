<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Cache\Engine\DebugEngine;
use Crustum\DebugBar\Panel\CachePanel;

/**
 * Class CachePanelTest
 *
 * @ported-from \DebugKit\Test\TestCase\Panel\CachePanelTest
 */
class CachePanelTest extends TestCase
{
    /**
     * @var CachePanel
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
        $this->panel = new CachePanel();
        Cache::setConfig('debug_bar_test', ['className' => 'Null', 'path' => TMP]);
    }

    /**
     * Teardown method.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Cache::drop('debug_bar_test');
        Cache::drop('incomplete');
    }

    /**
     * test initialize
     *
     * @return void
     */
    public function testInitialize(): void
    {
        $this->panel->initialize();

        $result = $this->panel->data();
        $this->assertArrayHasKey('debug_bar_test', $result['metrics']);
        $this->assertArrayHasKey('_cake_model_', $result['metrics']);
    }

    /**
     * test initialize incomplete data
     *
     * @return void
     */
    public function testInitializeNoProxyIncompleteConfig(): void
    {
        $data = ['duration' => '+2 seconds'];
        Cache::setConfig('incomplete', $data);
        $this->panel->initialize();

        $config = Cache::getConfig('incomplete');
        $this->assertSame($data, $config);
    }

    /**
     * test initialize incomplete data
     *
     * @return void
     */
    public function testInitializeIncompleteData(): void
    {
        Cache::setConfig('incomplete', ['duration' => '+2 seconds']);
        $this->panel->initialize();

        $result = $this->panel->data();
        $this->assertArrayHasKey('incomplete', $result['metrics']);
    }

    /**
     * Ensure that subrequests don't double proxy the cache engine.
     *
     * @return void
     */
    public function testInitializeTwiceNoDoubleProxy(): void
    {
        $this->panel->initialize();
        $result = Cache::pool('debug_bar_test');
        $this->assertInstanceOf(DebugEngine::class, $result);

        $this->panel->initialize();
        $result2 = Cache::pool('debug_bar_test');
        $this->assertSame($result2, $result);
    }

    public function testInitializePreserveGlobalConfig(): void
    {
        $this->panel->initialize();
        $result = Cache::getConfig('debug_bar_test');
        $this->assertEquals($result['path'], TMP);
    }
}
