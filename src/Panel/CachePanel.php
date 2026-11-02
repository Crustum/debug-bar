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
class CachePanel extends DebugPanel implements TimelineSourceInterface
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

    /**
     * Builds cache operation rows for the waterfall.
     *
     * @param array<string, mixed> $data Cache panel data.
     * @param float $requestStart Request start as microtime(true).
     * @return list<array{at: float, duration_ms: float, type: string, label: string, panel: string, source: array{file: string, line: int}|null, ref: mixed}>
     */
    public function timelineEvents(array $data, float $requestStart): array
    {
        $events = [];
        foreach (array_values((array)($data['logs'] ?? [])) as $index => $line) {
            $line = (string)$line;
            $parsed = DebugEngine::parseLine($line);
            $when = 0.0;
            if (preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})(?:\.(\d{1,6}))?( [+-]\d{2}:?\d{2})?/', $line, $matches)) {
                $parsedTime = strtotime($matches[1] . ($matches[3] ?? ''));
                if ($parsedTime !== false) {
                    $fraction = isset($matches[2]) ? (float)('0.' . $matches[2]) : 0.0;
                    $when = (float)$parsedTime + $fraction;
                }
            }

            $label = $parsed['operation'] !== ''
                ? trim($parsed['operation'] . ($parsed['key'] !== '' ? ' `' . $parsed['key'] . '`' : ''))
                : mb_strimwidth($line, 0, 120, '…');
            $events[] = [
                'at' => $when > 0 ? max(0.0, ($when - $requestStart) * 1000) : 0.0,
                'duration_ms' => is_numeric($parsed['duration']) ? (float)$parsed['duration'] : 0.0,
                'type' => 'cache',
                'label' => mb_strimwidth($label, 0, 120, '…'),
                'panel' => 'cache',
                'source' => null,
                'ref' => 'cache-' . $index,
            ];
        }

        return $events;
    }
}
