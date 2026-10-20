<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Core\Configure;
use Cake\Error\Debugger;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\View\View;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Service\AppPath;

/**
 * Views panel.
 *
 * Records rendered templates in order plus one snapshot of the view
 * variables (exported nodes, like the request panel). The inspector lists
 * renders light; `GET panels/view-data` serves a single render with the
 * other views stripped. The inbox drains on `data()` for workers.
 */
class ViewsPanel extends DebugPanel
{
    /**
     * Rendered files in order.
     *
     * @var array<int, array<string, mixed>>
     */
    protected static array $renders = [];

    /**
     * Exported view variables snapshot.
     *
     * @var mixed
     */
    protected static mixed $vars = null;

    /**
     * Subscribes to view rendering events.
     *
     * @return void
     */
    public function initialize(): void
    {
        $manager = EventManager::instance();
        $manager->on('View.beforeRender', $this->captureVars(...));
        $manager->on('View.beforeRenderFile', $this->captureFile(...));
    }

    /**
     * Resets captured renders (long-lived workers and tests).
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$renders = [];
        static::$vars = null;
    }

    /**
     * Snapshots the view variables once per request.
     *
     * `View::getVars()` returns names only — values resolve per name so the
     * inspector shows data, not a key list.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     */
    public function captureVars(EventInterface $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof View || static::$vars !== null) {
            return;
        }

        $vars = [];
        foreach ($subject->getVars() as $name) {
            $key = (string)$name;
            $vars[$key] = $subject->get($key);
        }

        $maxDepth = (int)Configure::read('DebugBar.maxDepth', 10);
        static::$vars = Debugger::exportVarAsNodes($vars, max(1, $maxDepth));
    }

    /**
     * Records one rendered template file.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @param string $filename Rendered file.
     * @return void
     */
    public function captureFile(EventInterface $event, string $filename = ''): void
    {
        if ($filename === '') {
            return;
        }

        static::$renders[] = [
            'file' => $filename,
            'app' => !AppPath::isVendor($filename),
            'origin' => AppPath::origin($filename),
            'plugin' => AppPath::pluginName($filename),
            'order' => count(static::$renders) + 1,
        ];
    }

    /**
     * Get the data for this panel.
     *
     * @return array
     */
    public function data(): array
    {
        $data = [
            'renders' => array_values(static::$renders),
            'vars' => static::$vars,
            'summary' => ['count' => count(static::$renders)],
        ];
        static::clear();

        return $data;
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        $count = count(static::$renders);

        return $count === 0 ? '' : "{$count} views";
    }
}
