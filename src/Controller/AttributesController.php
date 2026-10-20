<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Controller;

use Cake\AttributeResolver\AttributeResolver;
use Cake\AttributeResolver\Enum\AttributeTargetType;
use Cake\AttributeResolver\ValueObject\AttributeInfo;
use Cake\View\JsonView;
use Crustum\DebugBar\Panel\AttributesPanel;
use Throwable;

/**
 * On-demand attribute inspection.
 *
 * Counterpart to `ComposerController` for the Attributes panel: where the
 * Packages panel runs `composer outdated` / `composer audit` in-process via
 * `Composer\Console\Application`, this controller queries
 * `Cake\AttributeResolver\AttributeResolver::collection()` directly in
 * this process — no shell, no `bin/cake attributes ...` subprocess, no
 * `Command` instantiation. The panel snapshot stays bounded; these
 * endpoints serve filtered pages, single-record detail, and cache warming
 * on demand. Filter semantics mirror `AttributesListCommand` /
 * `AttributesInspectCommand` / `AttributesWarmCommand` one-to-one.
 */
class AttributesController extends DebugBarController
{
    /**
     * Maximum rows served per query request.
     *
     * @var int
     */
    public const QUERY_LIMIT = 200;

    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->viewBuilder()->setClassName(JsonView::class);
    }

    /**
     * Filtered attribute list, served as JSON.
     *
     * Mirrors `attributes list` options. POST fields: `config` (default
     * `default`), `attribute` (partial attribute class, `--attribute`),
     * `class` (partial target class, `--class`), `namespace` (wildcard
     * pattern, `--namespace`), `type` (target type value, `--type`),
     * `plugin` (`--plugin`, `-` for app-level attributes), `limit`
     * (1..QUERY_LIMIT).
     *
     * @return void
     */
    public function query(): void
    {
        $this->request->allowMethod('post');

        if (!class_exists(AttributeResolver::class)) {
            $this->setListError('AttributeResolver is not installed.');

            return;
        }

        $data = $this->requestData();
        $configName = (string)($data['config'] ?? 'default');
        if ($configName === '') {
            $configName = 'default';
        }

        if (AttributeResolver::getConfig($configName) === null) {
            $this->setListError(sprintf('Configuration "%s" does not exist.', $configName));

            return;
        }

        $limit = max(1, (int)($data['limit'] ?? 100));
        $limit = min($limit, static::QUERY_LIMIT);

        try {
            $collection = AttributeResolver::collection($configName);
            $total = count($collection);

            $attribute = trim((string)($data['attribute'] ?? ''));
            if ($attribute !== '') {
                $collection = $collection->withAttributeContains($attribute);
            }

            $class = trim((string)($data['class'] ?? ''));
            if ($class !== '') {
                $collection = $collection->withClassNameContains($class);
            }

            $namespace = trim((string)($data['namespace'] ?? ''));
            if ($namespace !== '') {
                $collection = $collection->withNamespace($namespace);
            }

            $type = trim((string)($data['type'] ?? ''));
            if ($type !== '') {
                $targetType = AttributeTargetType::tryFrom($type);
                if ($targetType === null) {
                    // Same as the command: an unknown type matches nothing.
                    $this->set([
                        'items' => [],
                        'count' => 0,
                        'total' => $total,
                        'matching' => 0,
                        'truncated' => false,
                        'config' => $configName,
                        'error' => sprintf('Unknown target type "%s".', $type),
                    ]);
                    $this->viewBuilder()->setOption('serialize', [
                        'items', 'count', 'total', 'matching', 'truncated', 'config', 'error',
                    ]);

                    return;
                }

                $collection = $collection->withTargetType($targetType);
            }

            $plugin = trim((string)($data['plugin'] ?? ''));
            if ($plugin !== '') {
                $wanted = $plugin === '-' ? null : $plugin;
                $collection = $collection->filter(
                    static fn(AttributeInfo $attr): bool => (
                        $attr->pluginName ?? AttributesPanel::detectPlugin($attr->filePath)
                    ) === $wanted,
                );
            }

            $matching = count($collection);
            $rows = [];
            foreach ($collection as $attr) {
                if (count($rows) >= $limit) {
                    break;
                }

                $rows[] = $this->present($attr);
            }
        } catch (Throwable $throwable) {
            $this->setListError('Attribute query failed: ' . $throwable->getMessage());

            return;
        }

        $this->set([
            'items' => $rows,
            'count' => count($rows),
            'total' => $total,
            'matching' => $matching,
            'truncated' => $matching > count($rows),
            'config' => $configName,
            'error' => null,
        ]);
        $this->viewBuilder()->setOption('serialize', [
            'items', 'count', 'total', 'matching', 'truncated', 'config', 'error',
        ]);
    }

    /**
     * Detailed information about matching attributes, served as JSON.
     *
     * Mirrors `attributes inspect` arguments: `attribute` (partial
     * attribute class name) or `class` (partial target class name, shows
     * all attributes on matching classes), plus `config`. At least one of
     * the two matchers is required. Every match is returned, like the
     * command prints every match.
     *
     * @return void
     */
    public function inspect(): void
    {
        $this->request->allowMethod('post');

        if (!class_exists(AttributeResolver::class)) {
            $this->setInspectError('AttributeResolver is not installed.');

            return;
        }

        $data = $this->requestData();
        $configName = (string)($data['config'] ?? 'default');
        if ($configName === '') {
            $configName = 'default';
        }

        if (AttributeResolver::getConfig($configName) === null) {
            $this->setInspectError(sprintf('Configuration "%s" does not exist.', $configName));

            return;
        }

        $attribute = trim((string)($data['attribute'] ?? ''));
        $class = trim((string)($data['class'] ?? ''));

        if ($attribute === '' && $class === '') {
            $this->setInspectError('Please provide an attribute name or use class option.');

            return;
        }

        try {
            $collection = AttributeResolver::collection($configName);

            if ($attribute !== '') {
                $collection = $collection->withAttributeContains($attribute);
            }

            if ($class !== '') {
                $collection = $collection->withClassNameContains($class);
            }

            $rows = [];
            foreach ($collection as $attr) {
                if (count($rows) >= static::QUERY_LIMIT) {
                    break;
                }

                $rows[] = $this->present($attr);
            }

            $matching = count($collection);
        } catch (Throwable $throwable) {
            $this->setInspectError('Attribute inspect failed: ' . $throwable->getMessage());

            return;
        }

        if ($rows === []) {
            $this->setInspectError('No attributes found matching the criteria.');

            return;
        }

        $this->set([
            'items' => $rows,
            'count' => count($rows),
            'matching' => $matching,
            'truncated' => $matching > count($rows),
            'config' => $configName,
            'error' => null,
        ]);
        $this->viewBuilder()->setOption('serialize', [
            'items', 'count', 'matching', 'truncated', 'config', 'error',
        ]);
    }

    /**
     * Warm the attribute cache, served as JSON.
     *
     * Mirrors `attributes warm`: clears the cached collection for
     * `config` and re-discovers it. POST field: `config` (default
     * `default`).
     *
     * @return void
     */
    public function warm(): void
    {
        $this->request->allowMethod('post');

        if (!class_exists(AttributeResolver::class)) {
            $this->set([
                'config' => null,
                'count' => 0,
                'elapsed' => 0.0,
                'error' => 'AttributeResolver is not installed.',
            ]);
            $this->viewBuilder()->setOption('serialize', ['config', 'count', 'elapsed', 'error']);

            return;
        }

        $data = $this->requestData();
        $configName = (string)($data['config'] ?? 'default');
        if ($configName === '') {
            $configName = 'default';
        }

        if (AttributeResolver::getConfig($configName) === null) {
            $this->set([
                'config' => null,
                'count' => 0,
                'elapsed' => 0.0,
                'error' => sprintf('Configuration "%s" does not exist.', $configName),
            ]);
            $this->viewBuilder()->setOption('serialize', ['config', 'count', 'elapsed', 'error']);

            return;
        }

        try {
            $start = microtime(true);
            AttributeResolver::clear($configName);
            $count = count(AttributeResolver::collection($configName));
            $elapsed = round(microtime(true) - $start, 3);
        } catch (Throwable $throwable) {
            $this->set([
                'config' => $configName,
                'count' => 0,
                'elapsed' => 0.0,
                'error' => 'Attribute warm failed: ' . $throwable->getMessage(),
            ]);
            $this->viewBuilder()->setOption('serialize', ['config', 'count', 'elapsed', 'error']);

            return;
        }

        $this->set([
            'config' => $configName,
            'count' => $count,
            'elapsed' => $elapsed,
            'error' => null,
        ]);
        $this->viewBuilder()->setOption('serialize', ['config', 'count', 'elapsed', 'error']);
    }

    /**
     * Read POST data, falling back to a JSON body.
     *
     * fetch() posts a JSON body; pick it up directly when body parsing
     * did not populate getData(), same as `PanelsController::explain()`.
     *
     * @return array
     */
    protected function requestData(): array
    {
        $data = (array)$this->request->getData();
        if ($data === []) {
            $decoded = json_decode((string)$this->request->getBody(), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        return $data;
    }

    /**
     * Present one attribute as a JSON-safe row.
     *
     * `pluginName` stays `null` for app-level attributes (the CLI prints
     * `-` for those); the template renders the dash.
     *
     * @param \Cake\AttributeResolver\ValueObject\AttributeInfo $attr Attribute info.
     * @return array
     */
    protected function present(AttributeInfo $attr): array
    {
        $row = $attr->toArray();
        if (($row['pluginName'] ?? null) === null) {
            $row['pluginName'] = AttributesPanel::detectPlugin($attr->filePath);
        }

        $fileTime = $attr->fileTime;
        $row['fileTimeDisplay'] = $fileTime > 0 ? date('Y-m-d H:i:s', $fileTime) : '';
        $row['key'] = AttributesPanel::rowKey($row);
        $row['shortAttribute'] = $this->shortName($attr->attributeName);
        $row['shortClass'] = $this->shortName($attr->className);
        $row['targetType'] = $attr->target->type->value;
        $row['targetName'] = $attr->target->name;
        $row['argumentsDisplay'] = AttributesPanel::displayArguments($attr->arguments);

        return $row;
    }

    /**
     * Serialize a uniform list error envelope.
     *
     * @param string $message Error message.
     * @return void
     */
    protected function setListError(string $message): void
    {
        $this->set([
            'items' => [],
            'count' => 0,
            'total' => 0,
            'matching' => 0,
            'truncated' => false,
            'config' => null,
            'error' => $message,
        ]);
        $this->viewBuilder()->setOption('serialize', [
            'items', 'count', 'total', 'matching', 'truncated', 'config', 'error',
        ]);
    }

    /**
     * Serialize a uniform inspect error envelope.
     *
     * @param string $message Error message.
     * @return void
     */
    protected function setInspectError(string $message): void
    {
        $this->set([
            'items' => [],
            'count' => 0,
            'matching' => 0,
            'truncated' => false,
            'config' => null,
            'error' => $message,
        ]);
        $this->viewBuilder()->setOption('serialize', [
            'items', 'count', 'matching', 'truncated', 'config', 'error',
        ]);
    }

    /**
     * Shorten a fully-qualified class name to its last segment.
     *
     * @param string $name Class name.
     * @return string
     */
    protected function shortName(string $name): string
    {
        $position = strrpos($name, '\\');

        return $position === false ? $name : substr($name, $position + 1);
    }
}
