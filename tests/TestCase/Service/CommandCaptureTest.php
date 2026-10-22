<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Service;

use Cake\Console\Arguments;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Event\Event;
use Cake\Queue\Command\WorkerCommand;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Command\McpCommand;
use Crustum\DebugBar\Panel\RequestPanel;
use Crustum\DebugBar\Service\CliRecorder;
use Crustum\DebugBar\Service\CommandCapture;
use Monitor\Command\SnapshotCommand;
use ReflectionProperty;
use TestApp\Command\RequestProbeCommand;

/**
 * Covers console capture guards and ordering.
 */
class CommandCaptureTest extends TestCase
{
    /**
     * Tables to reset each test.
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Crustum/DebugBar.Requests',
        'plugin.Crustum/DebugBar.Panels',
    ];

    /**
     * Long-running commands never produce rows.
     *
     * @return void
     */
    public function testSkipsWorkerAndServerCommands(): void
    {
        $this->assertTrue(CommandCapture::shouldSkip(new McpCommand()));

        if (class_exists(WorkerCommand::class)) {
            $worker = new WorkerCommand();
            $this->assertTrue(CommandCapture::shouldSkip($worker));
        } else {
            $this->assertFalse(CommandCapture::shouldSkip(new RequestProbeCommand()));
        }

        $this->assertFalse(CommandCapture::shouldSkip(new RequestProbeCommand()));
    }

    /**
     * Noisy cron commands never produce rows.
     *
     * The entry is a plain class string, so it is checked without
     * instantiating (or even loading) the optional Monitor plugin.
     *
     * @return void
     */
    public function testSkipsMetricsSnapshotCommand(): void
    {
        $property = new ReflectionProperty(CommandCapture::class, 'skipped');

        $this->assertContains(SnapshotCommand::class, $property->getValue());
    }

    /**
     * Capture opens before panels record and finishes after they complete.
     *
     * @return void
     */
    public function testEventPriorities(): void
    {
        $events = new CommandCapture()->implementedEvents();

        $this->assertSame(1, $events['Command.beforeExecute']['priority']);
        $this->assertSame(1000, $events['Command.afterExecute']['priority']);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        CliRecorder::reset();
        parent::tearDown();
    }

    /**
     * Start pushes the invocation into the request panel directly.
     *
     * The panel attaches while the start event is already dispatching,
     * so its own listener would miss it — the capture must not rely on
     * the event. Skips sqlite stores, same as the recorder tests.
     *
     * @return void
     */
    public function testCapturePushesCommandIntoRequestPanel(): void
    {
        $connection = ConnectionManager::get('test');
        $this->skipIf(
            $connection->getDriver() instanceof Sqlite,
            'Command capture needs a real database',
        );
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = true;

        try {
            $capture = new CommandCapture();
            $command = new RequestProbeCommand();
            $args = new Arguments(['Projects'], ['dry-run' => true], ['name']);
            $capture->captureStart(new Event('Command.beforeExecute', $command, ['args' => $args]));

            $panel = CliRecorder::panel('Request');
            $this->assertInstanceOf(RequestPanel::class, $panel);
            $data = $panel->data();
            $this->assertSame('cli', $data['context']);
            $this->assertSame('request-probe', $data['name']);
            $this->assertSame([['name' => 'name', 'value' => 'Projects']], $data['arguments']);

            $capture->captureFinish(new Event('Command.afterExecute', $command, ['result' => 0]));
            $finished = $panel->data();
            $this->assertSame(0, $finished['exit_code']);
        } finally {
            $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = false;
            CliRecorder::reset();
        }
    }
}
