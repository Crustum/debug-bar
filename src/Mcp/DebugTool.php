<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Mcp;

use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\ORM\Locator\LocatorAwareTrait;
use Crustum\JsonSchema\Contracts\JsonSchema;
use Crustum\Mcp\Response;
use Crustum\Mcp\ResponseFactory;
use Crustum\Mcp\Server\Tool;
use Throwable;

/**
 * Base tool for the DebugBar MCP server.
 *
 * Every tool answers with the same envelope (`version`, `status`,
 * `data`) so agents can tell stored rows from failures without
 * guessing: `ok` carries data, `not_found` names the missing
 * request or panel. Unexpected failures never leak internals —
 * they surface as a plain tool error instead.
 */
abstract class DebugTool extends Tool
{
    use LocatorAwareTrait;

    /**
     * @inheritDoc
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'version' => $schema->integer()->required(),
            'status' => $schema->string()->enum(['ok', 'not_found'])->required(),
            'data' => $schema->object()->required(),
        ];
    }

    /**
     * Answers with a stored-data envelope.
     *
     * @param array<string, mixed> $data Bounded response data.
     * @return \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory
     */
    protected function ok(array $data): Response|ResponseFactory
    {
        return Response::structured([
            'version' => 2,
            'status' => 'ok',
            'data' => $data,
        ]);
    }

    /**
     * Answers when a request or panel is no longer stored.
     *
     * @param string $message What is missing.
     * @return \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory
     */
    protected function notFound(string $message): Response|ResponseFactory
    {
        return Response::structured([
            'version' => 2,
            'status' => 'not_found',
            'data' => ['message' => $message],
        ]);
    }

    /**
     * Runs a payload builder, converting unexpected failures to a tool error.
     *
     * @param callable $content Payload builder.
     * @return \Crustum\Mcp\Response|\Crustum\Mcp\ResponseFactory
     */
    protected function safeResponse(callable $content): Response|ResponseFactory
    {
        try {
            return $content();
        } catch (Throwable) {
            return Response::error('The stored debug data could not be read.');
        }
    }

    /**
     * Reads the response bounds from the toolbar config.
     *
     * @return array{max_items: int, max_bytes: int}
     */
    protected function mcpLimits(): array
    {
        $config = (array)Configure::read('DebugBar.mcp', []);

        return [
            'max_items' => max(1, (int)($config['max_items'] ?? 50)),
            'max_bytes' => max(1024, (int)($config['max_bytes'] ?? 100000)),
        ];
    }

    /**
     * Loads one stored request row with its panels.
     *
     * @param string $id Request id.
     * @return \Cake\Datasource\EntityInterface|null Request with panels or null.
     */
    protected function findRequest(string $id): ?EntityInterface
    {
        try {
            $requests = $this->fetchTable('Crustum/DebugBar.Requests');
            $row = $requests->find()
                ->where(['Requests.id' => $id])
                ->contain(['Panels'])
                ->first();
        } catch (Throwable) {
            return null;
        }

        return $row instanceof EntityInterface ? $row : null;
    }

    /**
     * Unserializes every stored panel of one request.
     *
     * Panels whose content no longer unserializes are skipped — one
     * corrupt row never hides the healthy siblings.
     *
     * @param \Cake\Datasource\EntityInterface $row Request with panels.
     * @return array<string, array<string, mixed>> Stored content keyed by panel name.
     */
    protected function storedPanels(EntityInterface $row): array
    {
        $panels = [];
        foreach ((array)$row->get('panels') as $panel) {
            $name = '';
            $content = '';
            if ($panel instanceof EntityInterface) {
                $name = (string)$panel->get('panel');
                $content = (string)$panel->get('content');
            }

            if ($name === '') {
                continue;
            }

            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $stored = @unserialize($content);
            if (is_array($stored)) {
                $panels[$name] = $stored;
            }
        }

        return $panels;
    }
}
