<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Service;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Crustum\DebugBar\Command\McpCommand;
use Crustum\DebugBar\Panel\RequestPanel;

/**
 * Captures console command runs as toolbar rows.
 *
 * Bridges `Command.beforeExecute`/`Command.afterExecute` to the generic
 * CLI capture: opens on start, stores on finish with the exit code as
 * the row status. Long-running commands never produce rows — the queue
 * worker owns per-job rows and the MCP server runs indefinitely — so
 * both stay out explicitly. Noisy cron commands (metrics snapshots every
 * few minutes) stay out too: they would flood the 20-row store and bury
 * real web rows in the switcher.
 */
class CommandCapture implements EventListenerInterface
{
    /**
     * Command classes that never produce rows.
     *
     * Explicit list so regular commands keep recording: entries are
     * class strings, never instantiated here, so missing optional
     * plugins simply never match.
     *
     * @var array<int, string>
     */
    protected static array $skipped = [
        McpCommand::class,
        'Cake\\Queue\\Command\\WorkerCommand',
        'Monitor\\Command\\SnapshotCommand',
    ];

    /**
     * Decides whether a command run is captured.
     *
     * @param object $command Command instance.
     * @return bool
     */
    public static function shouldSkip(object $command): bool
    {
        return array_any(static::$skipped, fn(string $skipped): bool => $command instanceof $skipped);
    }

    /**
     * Subscribes the capture to console lifecycle events.
     *
     * Start runs before panels record, finish runs after they complete.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Command.beforeExecute' => ['callable' => 'captureStart', 'priority' => 1],
            'Command.afterExecute' => ['callable' => 'captureFinish', 'priority' => 1000],
        ];
    }

    /**
     * Opens one toolbar capture for a command run.
     *
     * Pushes the invocation into the request panel directly: the panel
     * attaches to the event manager while this very start event is
     * dispatching, so its own listener would miss it.
     *
     * @param \Cake\Event\EventInterface $event Command start event.
     * @return void
     */
    public function captureStart(EventInterface $event): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        $command = $event->getSubject();
        if (!is_object($command)) {
            return;
        }

        if (static::shouldSkip($command)) {
            return;
        }

        CliRecorder::open(static::url($command));

        $panel = CliRecorder::panel('Request');
        if ($panel instanceof RequestPanel) {
            $panel->startCommand($command, $event->getData('args'));
        }
    }

    /**
     * Stores one command run.
     *
     * @param \Cake\Event\EventInterface $event Command finish event.
     * @return void
     */
    public function captureFinish(EventInterface $event): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        $command = $event->getSubject();
        if (!is_object($command)) {
            return;
        }

        if (static::shouldSkip($command)) {
            return;
        }

        $result = $event->getData('result');
        $panel = CliRecorder::panel('Request');
        if ($panel instanceof RequestPanel) {
            $panel->finishCommand($result);
        }

        CliRecorder::finish(is_int($result) && $result !== 0 ? 500 : 200);
    }

    /**
     * Builds the synthetic row URL for a command run.
     *
     * The list already shows the `CLI` method, so the URL keeps only
     * the command slug — no path prefix, no binary. A leading `cake`
     * slug (binary name leaking in from custom runners) is stripped.
     *
     * @param object $command Command instance.
     * @return string
     */
    protected static function url(object $command): string
    {
        $name = method_exists($command, 'getName') ? (string)$command->getName() : '';
        if ($name === '') {
            $short = $command::class;
            $position = strrpos(ltrim($short, '\\'), '\\');
            $name = $position === false ? $short : substr($short, $position + 1);
        }

        $slug = trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-');
        $slug = (string)preg_replace('/^cake[-_]+/i', '', $slug);

        return $slug !== '' ? $slug : 'command';
    }
}
