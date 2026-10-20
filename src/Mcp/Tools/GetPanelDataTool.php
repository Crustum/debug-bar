<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mcp\Tools;

use Cake\Datasource\EntityInterface;
use Cake\Validation\Validator;
use Crustum\DebugBar\Mcp\DebugTool;
use Crustum\DebugBar\Mcp\McpDataWalker;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Request;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Attributes\Description;
use Crustum\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Crustum\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld(false)]
#[Description('Read any stored debug value by JSON Pointer. Start at /panels for panel names, then follow returned paths to lists, objects and exact scalar values. Strings beyond the byte budget chunk with omitted_due_to_bytes instead of dumping everything at once.')]
class GetPanelDataTool extends DebugTool
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
            'path' => $schema->string()->max(1000)
                ->description('JSON Pointer to inspect. Empty for the request root.')
                ->default(''),
            'cursor' => $schema->integer()->min(0)->default(0),
            'limit' => $schema->integer()->min(1)->max(50)->default(10),
        ];
    }

    /**
     * Reads one stored value by JSON Pointer path.
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
            ->allowEmptyString('path')
            ->maxLength('path', 1000)
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

            $limits = $this->mcpLimits();
            $limit = max(1, min((int)($input['limit'] ?? 10), $limits['max_items']));
            $cursor = max(0, (int)($input['cursor'] ?? 0));
            $path = (string)($input['path'] ?? '');

            $walker = new McpDataWalker($limits['max_items'], $limits['max_bytes']);
            $document = [
                'request' => [
                    'id' => (string)$row->get('id'),
                    'url' => (string)$row->get('url'),
                    'method' => (string)$row->get('method'),
                    'status_code' => (int)$row->get('status_code'),
                    'requested_at' => (string)$row->get('requested_at'),
                ],
                'panels' => [],
            ];
            foreach ($this->storedPanels($row) as $name => $content) {
                $document['panels'][$name] = $walker->sanitize($content);
            }

            $located = $walker->locate($document, $path);
            if (!$located['found']) {
                return $this->notFound("Path `{$path}` does not exist in request `{$id}`.");
            }

            $view = $walker->render($located['value'], $path, $cursor, $limit);
            $view['request_id'] = (string)$row->get('id');

            return $this->ok($view + ['path' => $path]);
        });
    }
}
