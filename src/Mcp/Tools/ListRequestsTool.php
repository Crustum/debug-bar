<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mcp\Tools;

use Cake\Datasource\EntityInterface;
use Cake\Validation\Validator;
use Crustum\DebugBar\Mcp\DebugTool;
use Crustum\DebugBar\Service\RequestFacts;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('List recent debug request summaries with method, path, status, duration and query counts. Prefer an exact request id from a previous call; otherwise match method, path or status.')]
class ListRequestsTool extends DebugTool
{
    /**
     * Defines the tool input schema.
     *
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'method' => $schema->string()->max(20)->description('Exact HTTP method, such as GET.'),
            'path' => $schema->string()->max(200)->description('Case-sensitive path fragment.'),
            'status' => $schema->integer()->min(100)->max(599)->description('Exact HTTP status.'),
            'limit' => $schema->integer()->min(1)->max(50)->default(10),
        ];
    }

    /**
     * Lists recent stored request summaries.
     *
     * @param \Crustum\Mcp\Request $request MCP request.
     * @return \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory
     */
    public function handle(Request $request): Response|ResponseFactory
    {
        $validator = new Validator();
        $validator
            ->allowEmptyString('method')
            ->maxLength('method', 20)
            ->allowEmptyString('path')
            ->maxLength('path', 200)
            ->allowEmptyString('status')
            ->naturalNumber('status')
            ->greaterThanOrEqual('status', 100)
            ->lessThanOrEqual('status', 599)
            ->allowEmptyString('limit')
            ->naturalNumber('limit');
        $input = $request->validateWith($validator);

        return $this->safeResponse(function () use ($input): Response|ResponseFactory {
            $limits = $this->mcpLimits();
            $limit = max(1, min((int)($input['limit'] ?? 10), $limits['max_items']));
            $method = isset($input['method']) && $input['method'] !== ''
                ? strtoupper((string)$input['method']) : null;
            $path = isset($input['path']) && $input['path'] !== '' ? (string)$input['path'] : null;
            $status = isset($input['status']) && $input['status'] !== '' ? (int)$input['status'] : null;

            $requests = $this->fetchTable('Crustum/DebugBar.Requests');
            $query = $requests->find()
                ->orderBy(['Requests.requested_at' => 'DESC'])
                ->contain(['Panels'])
                ->limit($limit + 1);
            $rows = [];
            foreach ($query->all() as $row) {
                if ((string)$row->get('id') === '') {
                    continue;
                }

                if ($method !== null && strtoupper((string)$row->get('method')) !== $method) {
                    continue;
                }

                if ($path !== null && !str_contains((string)$row->get('url'), $path)) {
                    continue;
                }

                if ($status !== null && (int)$row->get('status_code') !== $status) {
                    continue;
                }

                $rows[] = $this->summarize($row);
                if (count($rows) >= $limit) {
                    break;
                }
            }

            return $this->ok(['requests' => $rows, 'count' => count($rows)]);
        });
    }

    /**
     * Summarizes one stored request for agents: identity first, then
     * duration, queries and per-panel counts.
     *
     * @param \Cake\Datasource\EntityInterface $row Request with panels.
     * @return array<string, mixed>
     */
    protected function summarize(EntityInterface $row): array
    {
        $facts = RequestFacts::summarize($row);
        $counts = [];
        /** @var array $panels */
        $panels = $row->get('panels');
        foreach ((array)$panels as $panel) {
            if (!$panel instanceof EntityInterface) {
                continue;
            }

            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $stored = @unserialize((string)$panel->get('content'));
            if (!is_array($stored)) {
                continue;
            }

            $count = RequestFacts::panelCount((string)$panel->get('panel'), $stored);
            if ($count !== null) {
                $counts[(string)$panel->get('title')] = $count;
            }
        }

        return [
            'id' => $facts['id'],
            'url' => $facts['url'],
            'method' => $facts['method'],
            'status_code' => $facts['status_code'],
            'requested_at' => $facts['requested_at'],
            'duration' => $facts['duration_label'],
            'query_count' => $facts['query_count'],
            'query_time' => $facts['query_time_label'],
            'warning' => $facts['warning'],
            'panel_counts' => $counts,
        ];
    }
}
