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

    /**
     * Placeholders render with context values instead of raw braces.
     *
     * Query logs arrive as `connection={connection} role={role} …` with the
     * values in context — the title must show the interpolated line.
     *
     * @return void
     */
    public function testLogInterpolatesPlaceholders(): void
    {
        $logger = new DebugBarLog();
        $logger->log(
            'debug',
            'connection={connection} role={role} duration={took} rows={numRows} SELECT 1',
            ['connection' => 'default', 'role' => 'write', 'took' => 8.1, 'numRows' => 5],
        );

        $rows = $logger->all()['debug'];
        $this->assertSame(
            'connection=default role=write duration=8.1 rows=5 SELECT 1',
            (string)$rows[0][1],
        );
    }

    /**
     * Log context is captured exported with sensitive leaves masked.
     *
     * @return void
     */
    public function testLogCapturesContext(): void
    {
        $logger = new DebugBarLog();
        $logger->log('error', 'Boom', ['user_id' => 7, 'password' => 'secret']);

        $rows = $logger->all()['error'];
        $this->assertCount(1, $rows);
        $this->assertArrayHasKey(3, $rows[0]);

        $context = (array)$rows[0][3];
        $this->assertNotSame([], $context);
        $this->assertStringNotContainsString('secret', (string)json_encode($context));
    }

    /**
     * Empty contexts stay empty so rows without context cost nothing.
     *
     * @return void
     */
    public function testLogEmptyContextStaysEmpty(): void
    {
        $logger = new DebugBarLog();
        $logger->log('info', 'Hello');

        $rows = $logger->all()['info'];
        $this->assertSame([], (array)$rows[0][3]);
    }
}
