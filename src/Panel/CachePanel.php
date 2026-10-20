<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Cache\Cache;
use Cake\Log\Engine\ArrayLog;
use Crustum\DebugBar\Cache\Engine\DebugEngine;
use Crustum\DebugBar\DebugPanel;

/**
 * A panel for spying on cache engines.
 *
 * @ported-from \DebugKit\Panel\CachePanel
 */
class CachePanel extends DebugPanel
{
    /**
     * Array log collecting cache operation traces.
     *
     * @var \Cake\Log\Engine\ArrayLog
     */
    protected ArrayLog $logger;

    /**
     * Wrapped cache engines keyed by config name.
     *
     * @var array<\Crustum\DebugBar\Cache\Engine\DebugEngine>
     */
    protected array $instances = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->logger = new ArrayLog();
    }

    /**
     * Initialize - install cache spies.
     *
     * @return void
     */
    public function initialize(): void
    {
        foreach (Cache::configured() as $name) {
            /** @var array $config */
            $config = Cache::getConfig($name);
            if (isset($config['className']) && $config['className'] instanceof DebugEngine) {
                $instance = $config['className'];
            } elseif (isset($config['className'])) {
                /** @var \Cake\Cache\CacheEngine $engine */
                $engine = Cache::pool($name);
                Cache::getRegistry()->unload($name);

                $instance = new DebugEngine($engine, $name, $this->logger);
                $instance->init();
                $config['className'] = $instance;

                Cache::drop($name);
                Cache::setConfig($name, $config);
            }

            if (isset($instance)) {
                $this->instances[$name] = $instance;
            }
        }
    }

    /**
     * Get the data for this panel
     *
     * @return array
     */
    public function data(): array
    {
        $metrics = [];
        foreach ($this->instances as $name => $instance) {
            $metrics[$name] = $instance->metrics();
        }

        $logs = $this->logger->read();
        $this->logger->clear();

        return [
            'metrics' => $metrics,
            'logs' => $logs,
        ];
    }
}
