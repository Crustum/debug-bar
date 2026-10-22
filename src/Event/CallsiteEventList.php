<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Event;

use Cake\Event\EventInterface;
use Cake\Event\EventList;
use Cake\Event\EventManager;
use ReflectionClass;
use Throwable;

/**
 * Event list that remembers the real dispatch site of every event.
 *
 * The core list keeps event objects only, so panels can only guess the
 * dispatch location from the subject class file — which points at the
 * subject definition (an application controller, an entity) instead of
 * the code that actually dispatched (often a plugin inside vendor).
 * Capturing a short backtrace inside `add()` records the true caller of
 * `dispatch()` per event while dispatch still runs.
 */
class CallsiteEventList extends EventList
{
    /**
     * Dispatch sites keyed by event object id.
     *
     * @var array<int, array{file: string|null, line: int|null}>
     */
    protected array $sites = [];

    /**
     * @inheritDoc
     */
    public function add(EventInterface $event): void
    {
        parent::add($event);
        $this->sites[spl_object_id($event)] = self::dispatchSite();
    }

    /**
     * @inheritDoc
     */
    public function flush(): void
    {
        $this->sites = [];
        parent::flush();
    }

    /**
     * Reads the dispatch site of one tracked event.
     *
     * @param \Cake\Event\EventInterface<object> $event Tracked event.
     * @return array{file: string|null, line: int|null}|null Site or null when unknown.
     */
    public function siteFor(EventInterface $event): ?array
    {
        return $this->sites[spl_object_id($event)] ?? null;
    }

    /**
     * Finds the true dispatch caller above the manager internals.
     *
     * Frames are skipped by file: anything inside the manager or list
     * sources (core dispatch, list accumulation, this capture itself)
     * belongs to the machinery. The first frame from any other file is
     * the code that called `dispatch()`, wherever it lives (application,
     * plugin or core). Skipping by class would be wrong — a call frame
     * carries the callee's class, so the dispatcher's own frame looks
     * like the manager.
     *
     * @return array{file: string|null, line: int|null}
     */
    protected static function dispatchSite(): array
    {
        static $internal = null;
        if ($internal === null) {
            $internal = [];
            foreach ([EventManager::class, EventList::class, self::class] as $class) {
                try {
                    $file = new ReflectionClass($class)->getFileName();
                } catch (Throwable) {
                    continue;
                }

                if (is_string($file)) {
                    $internal[$file] = true;
                }
            }
        }

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 25) as $frame) {
            $file = $frame['file'] ?? null;
            if (!is_string($file) || isset($internal[$file])) {
                continue;
            }

            return ['file' => $file, 'line' => isset($frame['line']) ? (int)$frame['line'] : null];
        }

        return ['file' => null, 'line' => null];
    }
}
