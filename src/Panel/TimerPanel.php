<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\I18n\Number;
use Closure;
use Crustum\DebugBar\DebugMemory;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\DebugTimer;

/**
 * Provides debug information on all timers used in a request.
 *
 * @ported-from \DebugKit\Panel\TimerPanel
 */
class TimerPanel extends DebugPanel
{
    /**
     * Return an array of events to listen to.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        $before = (fn($name): Closure => function () use ($name): void {
            DebugTimer::start($name);
        });
        $after = (fn($name): Closure => function () use ($name): void {
            DebugTimer::stop($name);
        });
        $both = (fn($name): array => [
            ['priority' => 0, 'callable' => $before('Event: ' . $name)],
            ['priority' => 999, 'callable' => $after('Event: ' . $name)],
        ]);

        return [
            'Controller.initialize' => [
                ['priority' => 0, 'callable' => function (): void {
                    DebugMemory::record('Controller initialization');
                }],
                ['priority' => 0, 'callable' => $before('Event: Controller.initialize')],
                ['priority' => 999, 'callable' => $after('Event: Controller.initialize')],
            ],
            'Controller.startup' => [
                ['priority' => 0, 'callable' => $before('Event: Controller.startup')],
                ['priority' => 999, 'callable' => $after('Event: Controller.startup')],
                ['priority' => 999, 'callable' => function (): void {
                    DebugMemory::record('Controller action start');
                    DebugTimer::start('Controller: action');
                }],
            ],
            'Controller.beforeRender' => [
                ['priority' => 0, 'callable' => function (): void {
                    DebugTimer::stop('Controller: action');
                }],
                ['priority' => 0, 'callable' => $before('Event: Controller.beforeRender')],
                ['priority' => 999, 'callable' => $after('Event: Controller.beforeRender')],
                ['priority' => 999, 'callable' => function (): void {
                    DebugMemory::record('View Render start');
                    DebugTimer::start('View: Render');
                }],
            ],
            'View.beforeRender' => $both('View.beforeRender'),
            'View.afterRender' => $both('View.afterRender'),
            'View.beforeLayout' => $both('View.beforeLayout'),
            'View.afterLayout' => $both('View.afterLayout'),
            'View.beforeRenderFile' => [
                ['priority' => 0, 'callable' => function ($event, $filename): void {
                    DebugTimer::start('Render File: ' . $filename);
                }],
            ],
            'View.afterRenderFile' => [
                ['priority' => 0, 'callable' => function ($event, $filename): void {
                    DebugTimer::stop('Render File: ' . $filename);
                }],
            ],
            'Controller.shutdown' => [
                ['priority' => 0, 'callable' => $before('Event: Controller.shutdown')],
                ['priority' => 0, 'callable' => function (): void {
                    DebugTimer::stop('View: Render');
                    DebugMemory::record('Controller shutdown');
                }],
                ['priority' => 999, 'callable' => $after('Event: Controller.shutdown')],
            ],
        ];
    }

    /**
     * Get the data for the panel.
     *
     * @return array
     */
    public function data(): array
    {
        return [
            'requestTime' => DebugTimer::requestTime(),
            'timers' => DebugTimer::getAll(),
            'memory' => DebugMemory::getAll(),
            'peakMemory' => DebugMemory::getPeak(),
        ];
    }

    /**
     * Get the summary for the panel.
     *
     * @return string
     */
    public function summary(): string
    {
        $time = Number::precision(DebugTimer::requestTime(), 2) . ' s';
        $memory = Number::toReadableSize(DebugMemory::getPeak());

        return "$time / $memory";
    }
}
