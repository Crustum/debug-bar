<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Core\App;
use Cake\Core\ObjectRegistry;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;
use Cake\Event\EventListenerInterface;
use Cake\Event\EventManager;
use Crustum\DebugBar\DebugPanel;
use RuntimeException;

/**
 * Registry object for panels.
 *
 * @extends \Cake\Core\ObjectRegistry<\Crustum\DebugBar\DebugPanel>
 * @ported-from \DebugKit\Panel\PanelRegistry
 */
class PanelRegistry extends ObjectRegistry implements EventDispatcherInterface
{
    use EventDispatcherTrait;

    /**
     * Constructor
     *
     * @param \Cake\Event\EventManager $eventManager Event Manager that panels should bind to.
     *   Typically this is the global manager.
     */
    public function __construct(EventManager $eventManager)
    {
        $this->setEventManager($eventManager);
    }

    /**
     * Resolve a panel class name.
     *
     * Part of the template method for Cake\Utility\ObjectRegistry::load()
     *
     * @param string $class Partial class name to resolve.
     * @return string|null Either the correct class name, null if the class is not found.
     */
    protected function resolveClassName(string $class): ?string
    {
        return App::className($class, 'Panel', 'Panel');
    }

    /**
     * Throws an exception when a component is missing.
     *
     * Part of the template method for Cake\Utility\ObjectRegistry::load()
     *
     * @param string $class The classname that is missing.
     * @param string $plugin The plugin the component is missing in.
     * @return void
     * @throws \RuntimeException
     */
    protected function throwMissingClassError(string $class, ?string $plugin): void
    {
        throw new RuntimeException(sprintf("Unable to find '%s' panel.", $class));
    }

    /**
     * Create the panels instance.
     *
     * Part of the template method for Cake\Utility\ObjectRegistry::load()
     *
     * @param \Crustum\DebugBar\DebugPanel|object|class-string<\Crustum\DebugBar\DebugPanel> $class The classname to create.
     * @param string $alias The alias of the panel.
     * @param array $config An array of config to use for the panel.
     * @return \Crustum\DebugBar\DebugPanel The constructed panel class.
     * @throws \RuntimeException When the class is not a panel.
     */
    protected function create(object|string $class, string $alias, array $config): DebugPanel
    {
        $instance = is_string($class) ? new $class() : $class;

        if ($instance instanceof DebugPanel) {
            $this->getEventManager()->on($instance);

            return $instance;
        }

        if (
            method_exists($instance, 'title') &&
            method_exists($instance, 'elementName') &&
            method_exists($instance, 'data') &&
            method_exists($instance, 'summary') &&
            method_exists($instance, 'initialize')
        ) {
            if ($instance instanceof EventListenerInterface) {
                $this->getEventManager()->on($instance);
            }

            return new LegacyPanelAdapter($instance);
        }

        throw new RuntimeException(sprintf(
            "Panel '%s' must extend %s or implement its panel contract.",
            $instance::class,
            DebugPanel::class,
        ));
    }
}
