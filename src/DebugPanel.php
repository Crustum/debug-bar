<?php
declare(strict_types=1);

namespace Crustum\DebugBar;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Cake\Utility\Inflector;
use function Cake\Core\namespaceSplit;

/**
 * Base class for debug panels.
 *
 * @ported-from \DebugKit\DebugPanel
 */
class DebugPanel implements EventListenerInterface
{
    /**
     * Defines which plugin this panel is from so the element can be located.
     *
     * @var string
     */
    public string $plugin = 'Crustum/DebugBar';

    /**
     * The data collected about a given request.
     *
     * @var array
     */
    protected array $data = [];

    /**
     * Event managers already subscribed in this process, keyed by panel class.
     *
     * Managers are shared process-wide under long-lived servers but
     * replaced between PHPUnit tests, so the guard keys on identity
     * instead of a plain flag.
     *
     * @var array<string, \Cake\Event\EventManager>
     */
    private static array $subscribedManagers = [];

    /**
     * Get the title for the panel.
     *
     * @return string
     */
    public function title(): string
    {
        // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
        [$ns, $name] = namespaceSplit(static::class);
        $name = substr($name, 0, strlen('Panel') * -1);

        return Inflector::humanize(Inflector::underscore($name));
    }

    /**
     * Get the element name for the panel.
     *
     * @return string
     */
    public function elementName(): string
    {
        // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
        [$ns, $name] = namespaceSplit(static::class);
        if ($this->plugin) {
            return $this->plugin . '.' . Inflector::underscore($name);
        }

        return Inflector::underscore($name);
    }

    /**
     * Get the runtime contexts this panel supports.
     *
     * The CLI capture loads only panels listing `cli`, so web-only
     * panels never render dummy sections into worker or command rows.
     *
     * @return array<int, string> `web`, `cli`, or both.
     */
    public function contexts(): array
    {
        return ['web', 'cli'];
    }

    /**
     * Get the data a panel has collected.
     *
     * @return array
     */
    public function data(): array
    {
        return $this->data;
    }

    /**
     * Get the summary data for a panel.
     *
     * This data is displayed in the toolbar even when the panel is collapsed.
     *
     * @return string
     */
    public function summary(): string
    {
        return '';
    }

    /**
     * Initialize hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
    }

    /**
     * Reset per-request state accumulated since the previous request.
     *
     * The middleware runs this on every loaded panel before handling a
     * request. Override when the panel keeps request state outside of
     * `data` (static inboxes, loggers, engine buffers); panels without
     * carried state inherit the no-op.
     *
     * @return void
     */
    public static function clear(): void
    {
    }

    /**
     * Subscribe listeners once per event manager.
     *
     * Re-subscribing the same panel on every request duplicates captures
     * under long-lived servers, while a plain once-flag would deafen
     * tests when the manager is replaced. The guard keys on manager
     * identity so both worlds work.
     *
     * @param \Cake\Event\EventManager $manager Event manager to subscribe on.
     * @param array<string, callable> $events Event name to listener map.
     * @return void
     */
    final protected function subscribeOnce(EventManager $manager, array $events): void
    {
        $key = static::class;
        if (
            isset(self::$subscribedManagers[$key]) &&
            self::$subscribedManagers[$key] === $manager
        ) {
            return;
        }

        foreach ($events as $name => $listener) {
            $manager->on($name, $listener);
        }

        self::$subscribedManagers[$key] = $manager;
    }

    /**
     * Shutdown callback
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event The event.
     * @return void
     */
    public function shutdown(EventInterface $event): void
    {
    }

    /**
     * Get the events this panels supports.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Controller.shutdown' => 'shutdown',
        ];
    }
}
