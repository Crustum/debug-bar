<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Database\Expression\ComparisonExpression;
use Cake\Database\Expression\IdentifierExpression;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Event\EventManager;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\Table;
use Crustum\DebugBar\DebugPanel;
use Crustum\DebugBar\Service\CallsiteCapture;
use Throwable;

/**
 * Models panel.
 *
 * Groups ORM operations per model class: retrievals, logical writes
 * (created/updated/deleted) and reloads (re-reads of a known identity),
 * each with the application source that caused it. The global `Model.*`
 * listeners catch every table — the event manager merges global listeners
 * into each table-local dispatch — so no app changes are needed. Only
 * shapes reach the stored row — identifiers and truncated changed fields,
 * never full rows. The inbox drains on `data()` for workers.
 */
class ModelsPanel extends DebugPanel
{
    /**
     * Captured operations.
     *
     * @var array<int, array<string, mixed>>
     */
    protected static array $inbox = [];

    /**
     * Subscribes to model operation events once per manager.
     *
     * @return void
     */
    public function initialize(): void
    {
        $this->subscribeOnce(EventManager::instance(), [
            'Model.beforeFind' => $this->captureFind(...),
            'Model.beforeSave' => $this->captureSave(...),
            'Model.beforeDelete' => $this->captureDelete(...),
        ]);
    }

    /**
     * Records one operation (behavior and listener entry point).
     *
     * @param array<string, mixed> $operation Operation with `op`, `table`, `class`,
     *   `connection`, `identifier`, `changed`, `is_new` and `source` keys.
     * @return void
     */
    public static function record(array $operation): void
    {
        $source = (string)($operation['source'] ?? '');
        static::$inbox[] = [
            'op' => (string)($operation['op'] ?? ''),
            'table' => (string)($operation['table'] ?? ''),
            'class' => (string)($operation['class'] ?? ''),
            'connection' => (string)($operation['connection'] ?? ''),
            'identifier' => (string)($operation['identifier'] ?? ''),
            'changed' => (array)($operation['changed'] ?? []),
            'is_new' => (bool)($operation['is_new'] ?? false),
            'source' => $source,
            'source_app' => (bool)($operation['source_app'] ?? ($source !== '')),
            'stack' => array_values((array)($operation['stack'] ?? [])),
            'at' => microtime(true),
        ];
    }

    /**
     * Resets captured operations (long-lived workers and tests).
     *
     * @return void
     */
    public static function clear(): void
    {
        static::$inbox = [];
    }

    /**
     * Captures a retrieval from a shared-manager table.
     *
     * `Model.beforeFind` fires before the query runs, so record identities
     * come from primary-key equality in the where clause (`get()` and
     * find-by-id render real identifiers, reloads and per-record rows).
     * Bare list queries carry no single identity and stay unidentified —
     * an honest gap, not a guess.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     */
    public function captureFind(EventInterface $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof Table) {
            return;
        }

        $data = (array)$event->getData();
        $query = $data[0] ?? null;

        $capture = CallsiteCapture::capture([__FILE__], 100);
        static::record([
            'op' => 'find',
            'table' => $subject->getAlias(),
            'class' => $subject::class,
            'connection' => $subject->getConnection()->configName(),
            'identifier' => $this->findIdentifier($subject, $query),
            'changed' => [],
            'source' => CallsiteCapture::label($capture['callsite']),
            'source_app' => $capture['callsite']['file'] !== null,
            'stack' => self::shortStack($capture['stack']),
        ]);
    }

    /**
     * Reads primary-key equality out of a select query.
     *
     * Walks the where expression for `pk = scalar` comparisons in the same
     * `field:value` shape writes use, so finds and writes share identifier
     * rows. Anything else (ranges, IN-lists, contains, booleans) stays
     * unidentified rather than guessing.
     *
     * @param \Cake\ORM\Table $subject Queried table.
     * @param mixed $query The `Model.beforeFind` query argument.
     * @return string Identifier or an empty string.
     */
    protected function findIdentifier(Table $subject, mixed $query): string
    {
        if (!$query instanceof SelectQuery) {
            return '';
        }

        try {
            $where = $query->clause('where');
        } catch (Throwable) {
            return '';
        }

        if (!$where instanceof QueryExpression) {
            return '';
        }

        $keys = array_map(strval(...), (array)$subject->getPrimaryKey());
        $parts = [];
        try {
            $where->traverse(static function (mixed $expression) use (&$parts, $keys): void {
                if (!$expression instanceof ComparisonExpression) {
                    return;
                }

                if (strtoupper($expression->getOperator()) !== '=') {
                    return;
                }

                $field = $expression->getField();
                if ($field instanceof IdentifierExpression) {
                    $field = $field->getIdentifier();
                }

                if (!is_string($field)) {
                    return;
                }

                $position = strrpos($field, '.');
                $short = $position === false ? $field : substr($field, $position + 1);
                if (!in_array($short, $keys, true)) {
                    return;
                }

                $value = $expression->getValue();
                if (!is_scalar($value) || is_bool($value)) {
                    return;
                }

                $parts[] = $short . ':' . mb_strcut((string)$value, 0, 60);
            });
        } catch (Throwable) {
            return '';
        }

        return implode(', ', $parts);
    }

    /**
     * Captures a write from a shared-manager table.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     */
    public function captureSave(EventInterface $event): void
    {
        $this->captureEntity($event, 'save');
    }

    /**
     * Captures a delete from a shared-manager table.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     */
    public function captureDelete(EventInterface $event): void
    {
        $this->captureEntity($event, 'delete');
    }

    /**
     * Get the data for this panel.
     *
     * Groups operations by model class: retrieval counts, logical writes
     * (created/updated/deleted with changed fields) and reloads, plus
     * per-identifier retrieval rows and per-source groupings so the detail
     * can show which code loaded or changed each record.
     *
     * @return array
     */
    public function data(): array
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $requestStart = isset($_SERVER['REQUEST_TIME_FLOAT']) ? (float)$_SERVER['REQUEST_TIME_FLOAT'] : null;
        foreach (static::$inbox as $operation) {
            $operation = (array)$operation;
            $class = (string)($operation['class'] ?? '');
            $table = (string)($operation['table'] ?? '');
            $name = $class !== '' ? $class : $table;
            if ($name === '') {
                continue;
            }

            $groups[$name] ??= [
                'table' => $table,
                'class' => $class,
                'model' => $class !== '' ? $class : $table,
                'name' => $this->shortName($class !== '' ? $class : $table),
                'connection' => (string)($operation['connection'] ?? ''),
                'retrieved' => 0,
                'writes' => 0,
                'reloads' => 0,
                'unidentified' => 0,
                'identities' => [],
                'records' => [],
                'writes_list' => [],
                'sources' => [],
                'operations' => [],
            ];

            $group = &$groups[$name];
            $op = (string)($operation['op'] ?? '');
            $identity = (string)($operation['identifier'] ?? '');
            $source = (string)($operation['source'] ?? '');
            $at = isset($operation['at']) ? (float)$operation['at'] : null;
            $atMs = $at !== null && $requestStart !== null ? max(0.0, ($at - $requestStart) * 1000) : null;
            if ($op === 'find') {
                $group['retrieved']++;
                if ($identity === '') {
                    $group['unidentified']++;
                } else {
                    if (isset($group['identities'][$identity])) {
                        $group['reloads']++;
                    }

                    $group['records'][$identity] ??= [
                        'identifier' => $identity,
                        'key' => $identity,
                        'retrieved' => 0,
                        'loads' => 0,
                        'source' => $source,
                        'source_app' => (bool)($operation['source_app'] ?? false),
                        'sources' => [],
                        'unknown_sources' => 0,
                    ];

                    $group['records'][$identity]['retrieved']++;
                    $group['records'][$identity]['loads']++;
                    if ($group['records'][$identity]['source'] === '' && $source !== '') {
                        $group['records'][$identity]['source'] = $source;
                        $group['records'][$identity]['source_app'] = (bool)($operation['source_app'] ?? false);
                    }

                    if ($source !== '') {
                        $group['records'][$identity]['sources'][$source] ??= [
                            'source' => $source,
                            'retrieval_count' => 0,
                            'change_count' => 0,
                        ];

                        $group['records'][$identity]['sources'][$source]['retrieval_count']++;
                    } else {
                        $group['records'][$identity]['unknown_sources']++;
                    }

                    $group['identities'][$identity] = true;
                }
            } else {
                $group['writes']++;
                $changed = (array)($operation['changed'] ?? []);
                $group['writes_list'][] = [
                    'operation' => $this->writeLabel($op, (bool)($operation['is_new'] ?? false)),
                    'event' => $this->writeLabel($op, (bool)($operation['is_new'] ?? false)),
                    'identifier' => $identity,
                    'key' => $identity,
                    'source' => $source,
                    'source_app' => (bool)($operation['source_app'] ?? false),
                    'changed' => $changed,
                    'changes' => $changed,
                    'changed_count' => count($changed),
                    'at_ms' => $atMs,
                ];
            }

            if ($source !== '') {
                $group['sources'][$source] ??= [
                    'source' => $source,
                    'source_app' => (bool)($operation['source_app'] ?? false),
                    'count' => 0,
                    'retrieval_count' => 0,
                    'change_count' => 0,
                    'chain' => array_values((array)($operation['stack'] ?? [])),
                ];

                $group['sources'][$source]['count']++;
                if ($op === 'find') {
                    $group['sources'][$source]['retrieval_count']++;
                } else {
                    $group['sources'][$source]['change_count']++;
                }
            }

            $group['operations'][] = $operation;
            unset($group);
        }

        static::$inbox = [];

        $list = array_values($groups);
        foreach ($list as &$group) {
            unset($group['identities']);
            $group['records'] = array_values($group['records']);
            foreach ($group['records'] as &$record) {
                $record['sources'] = array_values($record['sources']);
                $record['source_count'] = count($record['sources']);
            }

            unset($record);
            $group['writes_list'] = array_values($group['writes_list']);
            $group['sources'] = array_values($group['sources']);
            $group['record_count'] = count($group['records']);
            $group['source_count'] = count($group['sources']);
            $group['unidentified_load_count'] = $group['unidentified'];
        }

        unset($group);

        return [
            'groups' => $list,
            'summary' => [
                'models' => count($list),
                'tables' => count($list),
                'retrieved' => array_sum(array_column($list, 'retrieved')),
                'writes' => array_sum(array_column($list, 'writes')),
            ],
        ];
    }

    /**
     * Get the summary data.
     *
     * @return string
     */
    public function summary(): string
    {
        return (string)count(static::$inbox);
    }

    /**
     * Shortens a fully-qualified class name to its last segment.
     *
     * @param string $name Class name or table alias.
     * @return string Short display name.
     */
    protected function shortName(string $name): string
    {
        $short = str_replace('\\', '/', $name);
        $position = strrpos($short, '/');

        return $position === false ? $name : substr($short, $position + 1);
    }

    /**
     * Shortens captured stacks to application `basename:line` chains.
     *
     * Stored per operation so the Sources tab can show the real call
     * chain behind a callsite instead of a single frame.
     *
     * @param array<int, array{file: string|null, line: int|null, function: string, app: bool}> $stack Captured stack.
     * @return list<string> Short labels, innermost first, capped at eight.
     */
    protected static function shortStack(array $stack): array
    {
        $chain = [];
        foreach ($stack as $frame) {
            if ($frame['app'] !== true) {
                continue;
            }

            $file = (string)($frame['file'] ?? '');
            if ($file === '') {
                continue;
            }

            $label = basename(str_replace('\\', '/', $file));
            $line = $frame['line'] ?? null;
            $chain[] = $line !== null ? $label . ':' . $line : $label;
            if (count($chain) >= 8) {
                break;
            }
        }

        return $chain;
    }

    /**
     * Maps a raw ORM operation to its logical write label.
     *
     * @param string $op Raw operation (`save` or `delete`).
     * @param bool $isNew Whether the saved entity was new.
     * @return string `Created`, `Updated` or `Deleted`.
     */
    protected function writeLabel(string $op, bool $isNew): string
    {
        if ($op === 'delete') {
            return 'Deleted';
        }

        if ($op === 'save' && $isNew) {
            return 'Created';
        }

        return 'Updated';
    }

    /**
     * Captures an entity write from a shared-manager table.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @param string $op `save` or `delete`.
     * @return void
     */
    protected function captureEntity(EventInterface $event, string $op): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof Table) {
            return;
        }

        $data = (array)$event->getData();
        $entity = $data['entity'] ?? null;
        $identifier = '';
        $changed = [];
        $isNew = false;
        if ($entity instanceof EntityInterface) {
            $isNew = $op === 'save' && $entity->isNew();
            $parts = [];
            foreach ((array)$subject->getPrimaryKey() as $field) {
                $value = $entity->get((string)$field);
                if ($value !== null) {
                    $parts[] = $field . ':' . mb_strcut((string)$value, 0, 60);
                }
            }

            $identifier = implode(', ', $parts);
            if ($op === 'save') {
                foreach ($entity->getDirty() as $field) {
                    $value = $entity->get((string)$field);
                    $changed[(string)$field] = is_scalar($value) || $value === null
                        ? mb_strcut((string)$value, 0, 200)
                        : gettype($value);
                }
            }
        }

        $capture = CallsiteCapture::capture([__FILE__]);
        $source = CallsiteCapture::label($capture['callsite']);
        static::record([
            'op' => $op,
            'table' => $subject->getAlias(),
            'class' => $subject::class,
            'connection' => $subject->getConnection()->configName(),
            'identifier' => $identifier,
            'changed' => $changed,
            'is_new' => $isNew,
            'source' => $source,
            'source_app' => $capture['callsite']['file'] !== null,
        ]);
    }
}
