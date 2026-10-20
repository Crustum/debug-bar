<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Controller;

use Cake\Cache\Cache;
use Cake\Cache\CacheEngine;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use TestApp\Application;

/**
 * Toolbar controller test.
 *
 * @ported-from \DebugKit\Test\TestCase\Controller\ToolbarControllerTest
 */
class ToolbarControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Setup method.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->configApplication(Application::class, []);
    }

    /**
     * Test clearing the cache does not work with GET
     *
     * @return void
     */
    public function testClearCacheNoGet(): void
    {
        $this->get('/debug-bar/toolbar/clear-cache?name=testing');
        $this->assertResponseCode(405);
    }

    /**
     * Test clearing the cache.
     *
     * @return void
     */
    public function testClearCache(): void
    {
        $mock = $this->getMockBuilder(CacheEngine::class)->getMock();
        $mock->expects($this->once())
            ->method('init')
            ->willReturn(true);
        $mock->expects($this->once())
            ->method('clear')
            ->willReturn(true);
        Cache::setConfig('testing', $mock);

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/debug-bar/toolbar/clear-cache', ['name' => 'testing']);
        $this->assertResponseOk();
        $this->assertResponseContains('success');

        Cache::drop('testing');
    }

    /**
     * Posting an unknown cache engine name 404s instead of returning a
     * misleading 200 with `success: false`.
     *
     * @return void
     */
    public function testClearCacheUnknownEngine(): void
    {
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/debug-bar/toolbar/clear-cache', ['name' => 'does-not-exist']);
        $this->assertResponseCode(404);
    }

    /**
     * Test clearing all caches does not work with GET
     *
     * @return void
     */
    public function testClearAllCachesNoGet(): void
    {
        $this->get('/debug-bar/toolbar/clear-all-caches');
        $this->assertResponseCode(405);
    }

    /**
     * Test clearing all caches.
     *
     * @return void
     */
    public function testClearAllCaches(): void
    {
        Cache::setConfig('clear_all_a', ['className' => 'Null']);
        Cache::setConfig('clear_all_b', ['className' => 'Null']);
        Cache::write('key', 'value', 'clear_all_a');

        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/debug-bar/toolbar/clear-all-caches');
        $this->assertResponseOk();
        $body = json_decode((string)$this->_response->getBody(), true);

        $this->assertTrue($body['success']);
        $this->assertMatchesRegularExpression('/All \d+ caches cleared\./', (string)$body['message']);
        $this->assertContains('clear_all_a', $body['cleared']);
        $this->assertContains('clear_all_b', $body['cleared']);
        $this->assertSame([], $body['failed']);
        $this->assertNull(Cache::read('key', 'clear_all_a'));

        Cache::drop('clear_all_a');
        Cache::drop('clear_all_b');
    }

    /**
     * Test clearing the session.
     *
     * @return void
     */
    public function testClearSession(): void
    {
        $this->session(['test' => 'value']);
        $this->configRequest(['headers' => ['Accept' => 'application/json']]);
        $this->post('/debug-bar/toolbar/clear-session');
        $this->assertResponseOk();
        $this->assertResponseContains('success');
        $this->assertResponseContains('Session cleared');
    }
}
