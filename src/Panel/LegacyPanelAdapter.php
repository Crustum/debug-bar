<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Event\EventInterface;
use Crustum\DebugBar\DebugPanel;
use RuntimeException;

/**
 * Adapts panels built for the previous toolbar generation.
 *
 * Third-party panels written against the former base class keep loading
 * without rewrites: the adapter delegates the panel contract to the wrapped
 * instance. Event subscriptions stay on the wrapped instance itself, so this
 * adapter exposes no events of its own.
 */
final class LegacyPanelAdapter extends DebugPanel
{
    /**
     * Wrapped legacy panel instance.
     *
     * @var object
     */
    private object $inner;

    /**
     * Wraps a legacy panel after checking its contract.
     *
     * @param object $inner Panel built for the previous toolbar generation.
     * @throws \RuntimeException When the instance misses the panel contract.
     */
    public function __construct(object $inner)
    {
        foreach (['title', 'elementName', 'data', 'summary', 'initialize'] as $method) {
            if (!method_exists($inner, $method)) {
                throw new RuntimeException(sprintf(
                    'Legacy panel %s must implement %s().',
                    $inner::class,
                    $method,
                ));
            }
        }

        $this->inner = $inner;
        $vars = get_object_vars($inner);
        if (isset($vars['plugin']) && is_string($vars['plugin']) && $vars['plugin'] !== '') {
            $this->plugin = $vars['plugin'];
        }
    }

    /**
     * The wrapped legacy panel instance.
     *
     * @return object
     */
    public function inner(): object
    {
        return $this->inner;
    }

    /**
     * Calls a panel contract method on the wrapped instance.
     *
     * @param string $method Method name from the panel contract.
     * @return mixed
     * @throws \RuntimeException When the wrapped instance misses the method.
     */
    private function callInner(string $method): mixed
    {
        $callback = [$this->inner, $method];
        if (!is_callable($callback)) {
            throw new RuntimeException(sprintf(
                'Legacy panel %s must implement %s().',
                $this->inner::class,
                $method,
            ));
        }

        return $callback();
    }

    /**
     * @inheritDoc
     */
    public function title(): string
    {
        return (string)$this->callInner('title');
    }

    /**
     * @inheritDoc
     */
    public function elementName(): string
    {
        return (string)$this->callInner('elementName');
    }

    /**
     * Panel contexts, delegated when the wrapped panel declares them.
     *
     * Third-party panels without the method stay visible everywhere.
     *
     * @return array<int, string>
     */
    public function contexts(): array
    {
        if (method_exists($this->inner, 'contexts')) {
            $contexts = $this->inner->contexts();
            if (is_array($contexts)) {
                return array_values($contexts);
            }
        }

        return ['web', 'cli'];
    }

    /**
     * @inheritDoc
     */
    public function data(): array
    {
        return (array)$this->callInner('data');
    }

    /**
     * @inheritDoc
     */
    public function summary(): string
    {
        return (string)$this->callInner('summary');
    }

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        $this->callInner('initialize');
    }

    /**
     * @inheritDoc
     */
    public function shutdown(EventInterface $event): void
    {
    }

    /**
     * No events: the wrapped instance is attached to the event manager itself.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [];
    }

    /**
     * Forwards third-party push calls to the wrapped instance.
     *
     * Captures attach panels while their own start event is already
     * dispatching, so recorders push missed payloads directly through the
     * registry panel — this hop keeps duck-typed panels reachable
     * without unwrapping them at every call site.
     *
     * @param string $method Method name on the wrapped instance.
     * @param array<int, mixed> $args Call arguments.
     * @return mixed
     * @throws \RuntimeException When the wrapped instance misses the method.
     */
    public function __call(string $method, array $args): mixed
    {
        $callback = [$this->inner, $method];
        if (!is_callable($callback)) {
            throw new RuntimeException(sprintf(
                'Legacy panel %s must implement %s().',
                $this->inner::class,
                $method,
            ));
        }

        return $callback(...$args);
    }
}
