<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mcp\Tools;

use Cake\Datasource\EntityInterface;
use Cake\Validation\Validator;
use Crustum\DebugBar\Mcp\DebugTool;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('Inspect bounded query evidence from one request with the same grouping and filters as the browser inspector: fingerprint groups, repeats, slow statements, N+1 hints, sources and per-execution evidence.')]
class InspectQueriesTool extends DebugTool
{
    /**
     * Defines the tool input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'request_id' => $schema->string()->format('uuid')->max(64)->description('Exact stored request id.')->required(),
            'filter' => $schema->string()->enum(['all', 'attention', 'repeated', 'slow', 'read', 'write'])->default('all'),
            'search' => $schema->string()->max(200)->default(''),
            'sort' => $schema->string()->enum(['execution', 'duration'])->default('execution'),
            'cursor' => $schema->integer()->min(0)->default(0),
            'limit' => $schema->integer()->min(1)->max(50)->default(5),
        ];
    }

    /**
     * Inspects bounded query evidence for one request.
     *
     * @param \Crustum\Mcp\Request $request MCP request.
     * @return \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validator = new Validator();
        $validator
            ->requirePresence('request_id')
            ->notEmptyString('request_id')
            ->uuid('request_id')
            ->maxLength('request_id', 64)
            ->allowEmptyString('filter')
            ->inList('filter', ['all', 'attention', 'repeated', 'slow', 'read', 'write'])
            ->allowEmptyString('search')
            ->maxLength('search', 200)
            ->allowEmptyString('sort')
            ->inList('sort', ['execution', 'duration'])
            ->allowEmptyString('cursor')
            ->nonNegativeInteger('cursor')
            ->allowEmptyString('limit')
            ->naturalNumber('limit');
        $input = $request->validateWith($validator);

        return $this->safeResponse(function () use ($input): Response|ResponseFactory {
            $id = trim((string)($input['request_id'] ?? ''));
            $row = $id === '' ? null : $this->findRequest($id);
            if (!$row instanceof EntityInterface) {
                return $this->notFound("Request `{$id}` is no longer stored.");
            }

            $panels = $this->storedPanels($row);
            if (!isset($panels['SqlLog'])) {
                return $this->notFound("Request `{$id}` has no SQL panel.");
            }

            $limits = $this->mcpLimits();
            $records = array_values((array)($panels['SqlLog']['records'] ?? []));
            $records = $this->applyFilter($records, (string)($input['filter'] ?? 'all'));
            $records = $this->applySearch($records, (string)($input['search'] ?? ''));
            $records = $this->applySort($records, (string)($input['sort'] ?? 'execution'));

            $total = count($records);
            $limit = max(1, min((int)($input['limit'] ?? 5), $limits['max_items']));
            $cursor = max(0, (int)($input['cursor'] ?? 0));
            $page = array_slice($records, $cursor, $limit);
            $presented = [];
            foreach ($page as $record) {
                $record = (array)$record;
                $presented[] = [
                    'key' => (string)($record['key'] ?? ''),
                    'execution' => (int)($record['execution'] ?? 0),
                    'sql' => (string)($record['sql'] ?? ''),
                    'connection' => (string)($record['connection'] ?? ''),
                    'driver' => (string)($record['driver'] ?? ''),
                    'query_type' => (string)($record['query_type'] ?? ''),
                    'duration_ms' => (float)($record['duration_ms'] ?? 0),
                    'duration_label' => (string)($record['duration_label'] ?? ''),
                    'count' => (int)($record['count'] ?? 1),
                    'repeated' => (bool)($record['repeated'] ?? false),
                    'slow' => (bool)($record['slow'] ?? false),
                    'likely_n_plus_one' => (bool)($record['likely_n_plus_one'] ?? false),
                    'source_label' => (string)($record['source_label'] ?? ''),
                    'rows' => $this->executionRows($record),
                ];
            }

            return $this->ok([
                'request_id' => $id,
                'total' => $total,
                'cursor' => $cursor,
                'returned' => count($presented),
                'truncated' => $cursor + count($presented) < $total,
                'next_cursor' => $cursor + count($presented) < $total ? $cursor + count($presented) : null,
                'records' => $presented,
            ]);
        });
    }

    /**
     * Filters stored records by inspector filter name.
     *
     * @param array<int, array<string, mixed>> $records Stored query records.
     * @param string $filter Filter name.
     * @return array<int, array<string, mixed>>
     */
    protected function applyFilter(array $records, string $filter): array
    {
        if ($filter === '' || $filter === 'all') {
            return $records;
        }

        return array_values(array_filter($records, static function (mixed $record) use ($filter): bool {
            $record = (array)$record;
            if ($filter === 'attention') {
                return (bool)($record['attention'] ?? $record['slow'] ?? $record['repeated'] ?? false);
            }

            if ($filter === 'repeated') {
                return (bool)($record['repeated'] ?? false);
            }

            if ($filter === 'slow') {
                return (bool)($record['slow'] ?? false);
            }

            return (string)($record['query_type'] ?? '') === $filter;
        }));
    }

    /**
     * Filters stored records by SQL search text.
     *
     * @param array<int, array<string, mixed>> $records Stored query records.
     * @param string $search Case-insensitive substring over the record search text.
     * @return array<int, array<string, mixed>>
     */
    protected function applySearch(array $records, string $search): array
    {
        $needle = mb_strtolower(trim($search));
        if ($needle === '') {
            return $records;
        }

        return array_values(array_filter(
            $records,
            static function (mixed $record) use ($needle): bool {
                $row = (array)$record;

                return str_contains(mb_strtolower((string)($row['search'] ?? '')), $needle);
            },
        ));
    }

    /**
     * Sorts stored records by execution order or duration.
     *
     * @param array<int, array<string, mixed>> $records Stored query records.
     * @param string $sort execution|duration.
     * @return array<int, array<string, mixed>>
     */
    protected function applySort(array $records, string $sort): array
    {
        if ($sort === 'duration') {
            usort($records, static fn(mixed $a, mixed $b): int => (float)((array)$b)['duration_ms'] <=> (float)((array)$a)['duration_ms']);
        }

        return $records;
    }

    /**
     * Flattens per-execution evidence to bounded rows.
     *
     * @param array<string, mixed> $record Stored query record.
     * @return array<int, array<string, mixed>>
     */
    protected function executionRows(array $record): array
    {
        $rows = [];
        foreach (array_values((array)($record['executions'] ?? [])) as $execution) {
            $execution = (array)$execution;
            $rows[] = [
                'execution' => (int)($execution['execution'] ?? 0),
                'duration_ms' => (float)($execution['duration_ms'] ?? 0),
                'query_time_percent' => (float)($execution['query_time_percent'] ?? 0),
                'bindings' => array_values((array)($execution['bindings'] ?? [])),
                'rows' => (int)($execution['rows'] ?? 0),
                'explain_available' => (bool)($execution['explain_available'] ?? false),
            ];
        }

        return $rows;
    }
}
