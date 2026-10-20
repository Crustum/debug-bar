<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Core\Configure;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Database\Log\DebugLog;
use Crustum\DebugBar\Panel\SqlLogPanel;
use ReflectionProperty;
use TestApp\Stub\SimpleConnectionStub;

/**
 * Class SqlLogPanelTest
 *
 * @ported-from \DebugKit\Test\TestCase\Panel\SqlLogPanelTest
 */
class SqlLogPanelTest extends TestCase
{
    /**
     * @var SqlLogPanel
     */
    protected $panel;

    /**
     * @var
     */
    protected $logger;

    /**
     * Setup
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->panel = new SqlLogPanel();
        $this->logger = ConnectionManager::get('test')->getDriver()->getLogger();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->logger) {
            ConnectionManager::get('test')->getDriver()->setLogger($this->logger);
        }
    }

    /**
     * Ensure that subrequests don't double proxy the logger.
     *
     * @return void
     */
    public function testInitializeTwiceNoDoubleProxy(): void
    {
        $this->panel->initialize();
        $db = ConnectionManager::get('test');
        $logger = $db->getDriver()->getLogger();
        $this->assertInstanceOf(DebugLog::class, $logger);

        $this->panel->initialize();
        $second = $db->getDriver()->getLogger();
        $this->assertSame($second, $logger);
    }

    /**
     * Ensure that subrequests don't double proxy the logger.
     *
     * @return void
     */
    public function testInitializePassesIncludeSchema(): void
    {
        Configure::write('DebugBar.includeSchemaReflection', true);
        $this->panel->initialize();
        $db = ConnectionManager::get('test');
        $logger = $db->getDriver()->getLogger();
        $this->assertInstanceOf(DebugLog::class, $logger);

        $property = new ReflectionProperty($logger, '_includeSchema');
        $this->assertTrue($property->getValue($logger));
    }

    /**
     * test the parsing of source list.
     *
     * @return void
     */
    public function testData(): void
    {
        $this->panel->initialize();

        /** @var Table $articles */
        $articles = $this->getTableLocator()->get('Articles');
        $articles->findById(1)->first();

        $result = $this->panel->data();
        $this->assertArrayHasKey('loggers', $result);
        $this->assertNotEmpty($result['items']);
        foreach ($result['items'] as $item) {
            $this->assertArrayHasKey('start', $item);
            $this->assertArrayHasKey('end', $item);
            $this->assertGreaterThan(0.0, (float)$item['start']);
            $this->assertGreaterThanOrEqual((float)$item['start'], (float)$item['end']);
        }
    }

    /**
     * Test getting summary data.
     *
     * @return void
     */
    public function testSummary(): void
    {
        $this->panel->initialize();

        /** @var Table $articles */
        $articles = $this->getTableLocator()->get('Articles');
        $articles->findById(1)->first();

        $result = $this->panel->summary();
        $this->assertMatchesRegularExpression('/\d+ \\/ \d+(\.\d+)? ms/', $result);
    }

    /**
     * Testing a simple connection (no set/getLogger on driver).
     *
     * @return void
     */
    public function testWithSimpleConnection(): void
    {
        ConnectionManager::setConfig('simple', [
            'className' => SimpleConnectionStub::class,
        ]);

        $this->panel->addConnection('simple'); // should not throw an error
        $this->assertTrue(true);

        ConnectionManager::drop('simple');
    }
}
