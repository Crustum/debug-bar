<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Cache\Engine;

use BadMethodCallException;
use Cake\Cache\Engine\ArrayEngine;
use Cake\Cache\Engine\FileEngine;
use Cake\Log\Engine\ArrayLog;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Cache\Engine\DebugEngine;
use Crustum\DebugBar\DebugTimer;

/**
 * Class DebugEngine
 *
 * @ported-from \DebugKit\Test\TestCase\Cache\Engine\DebugEngineTest
 */
class DebugEngineTest extends TestCase
{
    /**
     * @var DebugEngine
     */
    protected $engine;

    /**
     * @var \Cake\Cache\Engine\ArrayEngine
     */
    private ArrayEngine $wrapped;

    /**
     * @var \Cake\Log\Engine\ArrayLog
     */
    private ArrayLog $logger;

    /**
     * setup
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->wrapped = new ArrayEngine();
        $this->wrapped->init(['prefix' => '']);

        $this->logger = new ArrayLog();

        $this->engine = new DebugEngine($this->wrapped, 'test', $this->logger);
        $this->engine->init();
        DebugTimer::clear();
    }

    /**
     * Test that init() builds engines based on config.
     *
     * @return void
     */
    public function testInitEngineBasedOnConfig(): void
    {
        $engine = new DebugEngine([
            'className' => 'File',
            'path' => TMP,
            'prefix' => '',
        ], 'test', $this->logger);
        $this->assertTrue($engine->init());
        $this->assertInstanceOf(FileEngine::class, $engine->engine());
    }

    /**
     * Test that the normal errors bubble up still.
     *
     * @return void
     */
    public function testInitErrorOnInvalidConfig(): void
    {
        $this->expectException(BadMethodCallException::class);
        $engine = new DebugEngine([
            'className' => 'Derpy',
            'path' => TMP,
            'prefix' => '',
        ], 'test', $this->logger);
        $engine->init();
    }

    /**
     * Test that methods are proxied.
     *
     * @return void
     */
    public function testProxyMethodsTracksMetrics(): void
    {
        $this->engine->get('key');
        $this->engine->has('key');
        $this->engine->set('key', 'value');
        $this->engine->get('key');
        $this->engine->has('key');
        $this->engine->add('new-key', 'value');
        $this->engine->delete('key');
        $this->engine->increment('key');
        $this->engine->decrement('key');

        $result = $this->engine->metrics();
        $this->assertSame(4, $result['set']);
        $this->assertSame(1, $result['delete']);
        $this->assertSame(2, $result['get miss']);
        $this->assertSame(2, $result['get hit']);
    }

    /**
     * Test that methods are proxied.
     *
     * @return void
     */
    public function testProxyMethodLogs(): void
    {
        $this->engine->get('key');
        $this->engine->has('key');
        $this->engine->set('key', 'value');
        $this->engine->add('new-key', 'value');
        $this->engine->delete('key');
        $this->engine->increment('key');
        $this->engine->decrement('key');
        $this->engine->setMultiple(['key' => 'value']);
        $this->engine->getMultiple(['key']);
        $this->engine->deleteMultiple(['key']);
        $this->engine->clearGroup('group');

        $logs = $this->logger->read();
        $this->assertCount(11, $logs);
        $this->assertMatchesRegularExpression('/^info: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6} [+-]\d{2}:\d{2} :test: get `key` - \d+\.\d{3}ms$/', (string)$logs[0]);
        $this->assertMatchesRegularExpression('/^info: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6} [+-]\d{2}:\d{2} :test: has `key`/', (string)$logs[1]);
        $this->assertMatchesRegularExpression('/^info: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6} [+-]\d{2}:\d{2} :test: set `key`/', (string)$logs[2]);
        $this->assertMatchesRegularExpression('/^info: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6} [+-]\d{2}:\d{2} :test: add `new-key`/', (string)$logs[3]);
    }

    /**
     * Test that groups proxies
     *
     * @return void
     */
    public function testGroupsProxies(): void
    {
        $engine = new DebugEngine([
            'className' => 'File',
            'path' => TMP,
            'prefix' => '',
            'groups' => ['test', 'test2'],
        ], 'test', $this->logger);
        $engine->init();

        $result = $engine->groups();
        $this->assertEquals(['test', 'test2'], $result);
    }

    /**
     * Test that config methods proxy the config data.
     *
     * @return void
     */
    public function testConfigProxies(): void
    {
        $engine = new DebugEngine([
            'className' => 'File',
            'path' => TMP,
            'prefix' => '',
        ], 'test', $this->logger);
        $engine->init();

        $data = $engine->getConfig();
        $this->assertArrayHasKey('path', $data);
        $this->assertArrayHasKey('prefix', $data);
    }

    /**
     * Test to string
     *
     * @return void
     */
    public function testToString(): void
    {
        $engine = new DebugEngine([
            'className' => 'File',
            'path' => TMP,
            'groups' => ['test', 'test2'],
        ], 'test', $this->logger);
        $this->assertSame('File', (string)$engine);
    }

    /**
     * Spy lines parse with the engine formatter prefix (no date).
     *
     * @return void
     */
    public function testParseLineWithLevelPrefix(): void
    {
        $this->engine->get('key');

        $logs = $this->logger->read();
        $this->assertNotEmpty($logs);
        $parsed = DebugEngine::parseLine((string)$logs[0]);

        $this->assertSame('info', $parsed['level']);
        $this->assertSame('test', $parsed['engine']);
        $this->assertSame('get', $parsed['operation']);
        $this->assertSame('key', $parsed['key']);
        $this->assertNotSame('', $parsed['duration']);
    }

    /**
     * Legacy date-prefixed and bare payloads still parse.
     *
     * @return void
     */
    public function testParseLineLegacyShapes(): void
    {
        $dated = DebugEngine::parseLine('2026-10-13 12:00:00 info: :test: set `key` - 0.00123ms');
        $this->assertSame('set', $dated['operation']);
        $this->assertSame('key', $dated['key']);
        $this->assertSame('test', $dated['engine']);

        $bare = DebugEngine::parseLine(':test: delete `gone` - 0.00010ms');
        $this->assertSame('delete', $bare['operation']);
        $this->assertSame('gone', $bare['key']);

        $keyless = DebugEngine::parseLine('info: :test: setMultiple - 0.00010ms');
        $this->assertSame('setMultiple', $keyless['operation']);
        $this->assertSame('', $keyless['key']);

        $garbage = DebugEngine::parseLine('not a spy line');
        $this->assertSame('', $garbage['operation']);
        $this->assertSame('', $garbage['engine']);
    }

    /**
     * @return void
     */
    public function testParseLineFractionalTimestamp(): void
    {
        $this->engine->get('key');

        $logs = $this->logger->read();
        $this->assertNotEmpty($logs);
        $line = (string)$logs[0];
        $this->assertMatchesRegularExpression('/^info: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6} [+-]\d{2}:\d{2} :test: /', $line);

        $parsed = DebugEngine::parseLine($line);
        $this->assertSame('info', $parsed['level']);
        $this->assertSame('test', $parsed['engine']);
        $this->assertSame('get', $parsed['operation']);
        $this->assertSame('key', $parsed['key']);
        $this->assertGreaterThan(0.0, (float)$parsed['duration']);
    }
}
