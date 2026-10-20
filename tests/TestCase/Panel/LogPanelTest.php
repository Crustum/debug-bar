<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Log\Log;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Log\Engine\DebugBarLog;
use Crustum\DebugBar\Panel\LogPanel;

/**
 * Class LogPanelTest
 *
 * @ported-from \DebugKit\Test\TestCase\Panel\LogPanelTest
 */
class LogPanelTest extends TestCase
{
    /**
     * @var LogPanel
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
        $this->panel = new LogPanel();
    }

    /**
     * Teardown method.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        Log::drop('debug_bar_log_panel');
    }

    /**
     * Test that logging configs are created.
     *
     * @return void
     */
    public function testInitialize(): void
    {
        $this->panel->initialize();

        $result = Log::configured();
        $this->assertContains('debug_bar_log_panel', $result);
        $this->assertTrue(count($result) > 1, 'Default loggers were not added.');
    }

    /**
     * test shutdown
     *
     * @return void
     */
    public function testData(): void
    {
        $this->panel->initialize();
        Log::write('error', 'Test');

        $result = $this->panel->data();

        $this->assertArrayHasKey('logger', $result);
        /** @var \DebugBar\Log\Engine\DebugBarLog $logger */
        $logger = $result['logger'];

        $this->assertInstanceOf(DebugBarLog::class, $logger);
        $this->assertCount(1, $logger->all()['error']);
    }

    /**
     * Test that the log panel outputs a summary.
     *
     * @return void
     */
    public function testSummary(): void
    {
        $this->panel->initialize();

        Log::write('error', 'Test');
        $this->assertSame('1', $this->panel->summary());

        Log::write('error', 'Test 2');
        Log::write('notice', 'A thing');
        $this->assertSame('3', $this->panel->summary());
    }
}
