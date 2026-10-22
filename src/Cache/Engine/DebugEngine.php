<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Cache\Engine;

use Cake\Cache\CacheEngine;
use Cake\Cache\CacheRegistry;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Stringable;
use function Cake\Core\namespaceSplit;

/**
 * A spying proxy for cache engines.
 *
 * Used by the CachePanel to wrap and track metrics related to caching.
 *
 * @ported-from \DebugKit\Cache\Engine\DebugEngine
 */
class DebugEngine extends CacheEngine implements Stringable
{
    /**
     * Proxied engine
     *
     * @var \Cake\Cache\CacheEngine
     */
    protected CacheEngine $engine;

    /**
     * Logger receiving cache operation traces.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * Engine name this spy wraps.
     *
     * @var string
     */
    protected string $name;

    /**
     * Hit/miss metrics.
     *
     * @var array<string, int>
     */
    protected array $metrics = [
        'set' => 0,
        'delete' => 0,
        'get hit' => 0,
        'get miss' => 0,
    ];

    /**
     * Constructor
     *
     * @param \Cake\Cache\CacheEngine|array<string, mixed> $config Config data or the proxied adapter.
     * @param string $name The name of the proxied cache engine.
     * @param \Psr\Log\LoggerInterface $logger Logger for collecting cache operation logs.
     */
    public function __construct(CacheEngine|array $config, string $name, LoggerInterface $logger)
    {
        if ($config instanceof CacheEngine) {
            $this->engine = $config;
        } else {
            $this->config = $config;
        }

        $this->logger = $logger;
        $this->name = $name;
    }

    /**
     * Initialize the proxied Cache Engine
     *
     * @param array $config Array of setting for the engine.
     * @return bool True, this engine cannot fail to initialize.
     */
    public function init(array $config = []): bool
    {
        if (!isset($this->engine)) {
            $registry = new CacheRegistry();
            $this->engine = $registry->load('spies', $this->config);
            unset($registry);
        }

        return true;
    }

    /**
     * Get the internal engine
     *
     * @return \Cake\Cache\CacheEngine
     */
    public function engine(): CacheEngine
    {
        return $this->engine;
    }

    /**
     * Get the metrics for this object.
     *
     * @return array
     */
    public function metrics(): array
    {
        return $this->metrics;
    }

    /**
     * Parses one spy log line into its parts.
     *
     * Accepts the engine formatter prefix (`info: `), the legacy
     * date-prefixed shape (`2026-10-13 12:00:00 info: `) and the bare
     * `:engine: operation [`key`] - durationms` payload. Unparseable lines
     * report empty parts so callers render a generic row instead of failing.
     *
     * @param string $line Raw spy log line.
     * @return array{level: string, engine: string, operation: string, key: string, duration: string}
     */
    public static function parseLine(string $line): array
    {
        $empty = ['level' => '', 'engine' => '', 'operation' => '', 'key' => '', 'duration' => ''];
        $payload = (string)preg_replace(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?(?: [+-]\d{2}:?\d{2})? /',
            '',
            $line,
        );
        $level = '';
        if (preg_match('/^(\w+): (.*)$/s', $payload, $prefix)) {
            $level = $prefix[1];
            $payload = (string)preg_replace(
                '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?(?: [+-]\d{2}:?\d{2})? /',
                '',
                $prefix[2],
            );
        }

        if (!preg_match('/^:([^:]+): (.*) - ([\d.]+)ms$/s', $payload, $matches)) {
            return $empty;
        }

        $operation = (string)$matches[2];
        $key = '';
        if (preg_match('/^(.*) `([^`]*)`$/s', $operation, $keyMatches)) {
            $operation = (string)$keyMatches[1];
            $key = (string)$keyMatches[2];
        }

        return [
            'level' => $level,
            'engine' => $matches[1],
            'operation' => $operation,
            'key' => $key,
            'duration' => $matches[3],
        ];
    }

    /**
     * Track a metric.
     *
     * @param string $metric The metric to increment.
     * @return void
     */
    protected function track(string $metric): void
    {
        $this->metrics[$metric]++;
    }

    /**
     * Log a cache operation
     *
     * @param string $operation The operation performed.
     * @param float $duration The duration of the operation in seconds.
     * @param string|null $key The cache key.
     * @return void
     */
    protected function log(string $operation, float $duration, ?string $key = null): void
    {
        $key = $key ? " `{$key}`" : '';
        $durationMs = number_format($duration * 1000, 3);
        $stamp = DateTimeImmutable::createFromFormat('U.u', sprintf('%.6F', microtime(true)))
            ?: new DateTimeImmutable();
        $stamp = $stamp->format('Y-m-d H:i:s.u P');

        $this->logger->log('info', "{$stamp} :{$this->name}: {$operation}{$key} - {$durationMs}ms");
    }

    /**
     * @inheritDoc
     */
    public function set($key, $value, $ttl = null): bool
    {
        $start = microtime(true);
        $result = $this->engine->set($key, $value, $ttl);
        $duration = microtime(true) - $start;

        $this->track('set');
        $this->log('set', $duration, $key);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function add(string $key, mixed $value): bool
    {
        $start = microtime(true);
        $result = $this->engine->add($key, $value);
        $duration = microtime(true) - $start;

        $this->track('set');
        $this->log('add', $duration, $key);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $start = microtime(true);
        $result = $this->engine->setMultiple($values);
        $duration = microtime(true) - $start;

        $this->track('set');
        $this->log('setMultiple', $duration);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $start = microtime(true);
        $result = $this->engine->get($key, $default);
        $duration = microtime(true) - $start;
        $metric = 'hit';
        if ($result === null) {
            $metric = 'miss';
        }

        $this->track("get {$metric}");
        $this->log('get', $duration, $key);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function has(string $key): bool
    {
        $start = microtime(true);
        $result = $this->engine->has($key);
        $duration = microtime(true) - $start;
        $metric = 'hit';
        if (!$result) {
            $metric = 'miss';
        }

        $this->track("get {$metric}");
        $this->log('has', $duration, $key);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function getMultiple($keys, $default = null): iterable
    {
        $start = microtime(true);
        $result = $this->engine->getMultiple($keys, $default);
        $duration = microtime(true) - $start;

        $this->track('get hit');
        $this->log('getMultiple', $duration);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function increment(string $key, int $offset = 1): int|false
    {
        $start = microtime(true);
        $result = $this->engine->increment($key, $offset);
        $duration = microtime(true) - $start;

        $this->track('set');
        $this->log('increment', $duration, $key);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function decrement(string $key, int $offset = 1): int|false
    {
        $start = microtime(true);
        $result = $this->engine->decrement($key, $offset);
        $duration = microtime(true) - $start;

        $this->track('set');
        $this->log('decrement', $duration, $key);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function delete($key): bool
    {
        $start = microtime(true);
        $result = $this->engine->delete($key);
        $duration = microtime(true) - $start;

        $this->track('delete');
        $this->log('delete', $duration, $key);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function deleteMultiple($keys): bool
    {
        $start = microtime(true);
        $result = $this->engine->deleteMultiple($keys);
        $duration = microtime(true) - $start;

        $this->track('delete');
        $this->log('deleteMultiple', $duration);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function clear(): bool
    {
        $start = microtime(true);
        $result = $this->engine->clear();
        $duration = microtime(true) - $start;

        $this->track('delete');
        $this->log('clear', $duration);

        return $result;
    }

    /**
     * @inheritDoc
     */
    public function groups(): array
    {
        return $this->engine->groups();
    }

    /**
     * Returns the config.
     *
     * @param string|null $key The key to get or null for the whole config.
     * @param mixed $default The return value when the key does not exist.
     * @return mixed Config value being read.
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        return $this->engine->getConfig($key, $default);
    }

    /**
     * Sets the config.
     *
     * @param array|string $key The key to set, or a complete array of configs.
     * @param mixed|null $value The value to set.
     * @param bool $merge Whether to recursively merge or overwrite existing config, defaults to true.
     * @return $this
     * @throws \Cake\Core\Exception\CakeException When trying to set a key that is invalid.
     */
    public function setConfig(array|string $key, mixed $value = null, bool $merge = true): static
    {
        $this->engine->setConfig($key, $value, $merge);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function clearGroup(string $group): bool
    {
        $start = microtime(true);
        $result = $this->engine->clearGroup($group);
        $duration = microtime(true) - $start;

        $this->track('delete');
        $this->log('clearGroup', $duration, $group);

        return $result;
    }

    /**
     * Magic __toString() method to get the CacheEngine's name
     *
     * @return string Returns the CacheEngine's name
     */
    public function __toString(): string
    {
        if (isset($this->engine)) {
            // phpcs:ignore SlevomatCodingStandard.Variables.UnusedVariable.UnusedVariable
            [$ns, $class] = namespaceSplit($this->engine::class);

            return str_replace('Engine', '', $class);
        }

        return (string)$this->config['className'];
    }
}
