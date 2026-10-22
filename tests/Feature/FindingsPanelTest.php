<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\FindingsPanel;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the derived findings panel contract.
 */
class FindingsPanelTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
    }

    /**
     * The panel exposes its contract before the toolbar assembles rows.
     *
     * @return void
     */
    public function testPanelContract(): void
    {
        $panel = new FindingsPanel();

        $this->assertSame('Findings', $panel->title());
        $this->assertSame('Crustum/DebugBar.findings_panel', $panel->elementName());
        $this->assertSame([], $panel->data()['findings']);
        $this->assertTrue($panel->data()['pending']);
    }

    /**
     * Finding links resolve to the source inspector sections.
     *
     * @return void
     */
    public function testFindingLinksResolveToSourcePanels(): void
    {
        $this->seedRequest([
            'SqlLog' => ['items' => [], 'records' => [], 'summary' => []],
            'Findings' => [
                'findings' => [
                    [
                        'rule_id' => 'query.slow',
                        'severity' => 'warning',
                        'panel' => 'queries',
                        'summary' => 'Slow query (250 ms)',
                        'why' => 'A single statement took 250 ms.',
                        'location' => 'src/Model/Table/DaysTable.php:41',
                        'next' => 'Run EXPLAIN on the query.',
                        'action' => 'Review queries',
                    ],
                ],
                'retained' => 1,
                'omitted' => 0,
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Findings'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('data-dk-finding="query.slow"', $body);
        $this->assertStringContainsString('Slow query (250 ms)', $body);
        $this->assertStringContainsString('Why', $body);
        $this->assertStringContainsString('data-dk-open-inspector="queries"', $body);
        $this->assertStringContainsString('DaysTable.php:41', $body);
    }

    /**
     * An empty request renders the empty state, not an error.
     *
     * @return void
     */
    public function testEmptyFindingsRenderEmptyState(): void
    {
        $this->seedRequest([
            'Findings' => ['findings' => [], 'retained' => 0, 'omitted' => 0],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Findings'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $this->assertStringContainsString('No findings', (string)$this->response->getBody());
    }
}
