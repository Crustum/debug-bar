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
#[Description('Read the stored findings of one request: rule id, severity, panel, summary and next check. Findings explain what is wrong, why it matters, where it came from and what to check next.')]
class GetFindingsTool extends DebugTool
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
            'severity' => $schema->string()->enum(['error', 'warning', 'info'])
                ->description('Only findings at or above this severity.'),
        ];
    }

    /**
     * Reads stored findings for one request.
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
            ->allowEmptyString('severity')
            ->inList('severity', ['error', 'warning', 'info']);
        $input = $request->validateWith($validator);

        return $this->safeResponse(function () use ($input): Response|ResponseFactory {
            $id = trim((string)($input['request_id'] ?? ''));
            $row = $id === '' ? null : $this->findRequest($id);
            if (!$row instanceof EntityInterface) {
                return $this->notFound("Request `{$id}` is no longer stored.");
            }

            $panels = $this->storedPanels($row);
            if (!isset($panels['Findings'])) {
                return $this->notFound("Request `{$id}` has no Findings panel.");
            }

            $findings = array_values((array)($panels['Findings']['findings'] ?? []));
            $minimum = (string)($input['severity'] ?? '');
            if ($minimum !== '') {
                $findings = array_values(array_filter(
                    $findings,
                    function (mixed $finding) use ($minimum): bool {
                        $row = (array)$finding;

                        return $this->severityRank((string)($row['severity'] ?? '')) >= $this->severityRank($minimum);
                    },
                ));
            }

            $presented = [];
            foreach ($findings as $finding) {
                $finding = (array)$finding;
                $presented[] = [
                    'rule_id' => (string)($finding['rule_id'] ?? ''),
                    'severity' => (string)($finding['severity'] ?? ''),
                    'panel' => (string)($finding['panel'] ?? ''),
                    'summary' => (string)($finding['summary'] ?? ''),
                    'why' => (string)($finding['why'] ?? ''),
                    'location' => $finding['location'] ?? null,
                    'next' => (string)($finding['next'] ?? ''),
                    'action' => $finding['action'] ?? null,
                ];
            }

            return $this->ok(['request_id' => $id, 'count' => count($presented), 'findings' => $presented]);
        });
    }

    /**
     * Ranks severities so filters keep everything at or above a level.
     *
     * @param string $severity Severity name.
     * @return int Rank or zero for unknown.
     */
    protected function severityRank(string $severity): int
    {
        return match (strtolower($severity)) {
            'error' => 3,
            'warning' => 2,
            'info' => 1,
            default => 0,
        };
    }
}
