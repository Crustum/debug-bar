<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\QueryExplainer;

/**
 * Covers read-only EXPLAIN gating and plan pretty-printing.
 */
class QueryExplainerTest extends TestCase
{
    /**
     * Single-column text plans render as an indented tree.
     *
     * @return void
     */
    public function testPrettyPlanJoinsSingleColumnRows(): void
    {
        $explainer = new QueryExplainer();
        $pretty = $explainer->prettyPlan([
            ['QUERY PLAN' => 'Hash Join  (cost=5.28..20.74 rows=1 width=4187)'],
            ['QUERY PLAN' => '  Hash Cond: (projectsusers.project_id = projects.id)'],
            ['QUERY PLAN' => '  ->  Seq Scan on projects_users  (cost=0.00..14.30 rows=430 width=158)'],
        ]);

        $this->assertSame(
            "Hash Join  (cost=5.28..20.74 rows=1 width=4187)\n" .
            "  Hash Cond: (projectsusers.project_id = projects.id)\n" .
            '  ->  Seq Scan on projects_users  (cost=0.00..14.30 rows=430 width=158)',
            $pretty,
        );
    }

    /**
     * Multi-column plans stay structured for the JSON renderer.
     *
     * @return void
     */
    public function testPrettyPlanRefusesMultiColumnRows(): void
    {
        $explainer = new QueryExplainer();

        $this->assertNull($explainer->prettyPlan([]));
        $this->assertNull($explainer->prettyPlan([['id' => 1, 'detail' => 'SCAN']]));
        $this->assertNull($explainer->prettyPlan([['QUERY PLAN' => ['nested' => true]]]));
        $this->assertNull($explainer->prettyPlan(['just a string']));
    }

    /**
     * Non-read statements are refused without touching the database.
     *
     * @return void
     */
    public function testExplainRefusesWriteStatements(): void
    {
        $explainer = new QueryExplainer();
        $result = $explainer->explain('test', 'UPDATE t SET a = 1', []);

        $this->assertArrayHasKey('unavailable_reason', $result);
        $this->assertArrayNotHasKey('plan', $result);
    }
}
