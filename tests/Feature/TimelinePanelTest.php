<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\TimelinePanel;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the derived timeline panel contract.
 */
class TimelinePanelTest extends TestCase
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
        $panel = new TimelinePanel();

        $this->assertSame('Timeline', $panel->title());
        $this->assertSame('Crustum/DebugBar.timeline_panel', $panel->elementName());
        $this->assertSame([], $panel->data()['events']);
        $this->assertTrue($panel->data()['pending']);
    }

    /**
     * Event lists page through the shared cursor contract.
     *
     * @return void
     */
    public function testEventsPageThroughJson(): void
    {
        $events = [];
        for ($i = 0; $i < 5; $i++) {
            $events[] = ['at' => $i * 10.0, 'duration_ms' => 1.0, 'type' => 'sql', 'label' => "SELECT {$i}", 'panel' => 'sqllog'];
        }

        $this->seedRequest(['Timeline' => ['events' => $events, 'retained' => 5, 'omitted' => 0]]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Timeline'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id . '.json?cursor=2&limit=2');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertCount(2, $body['data']['events']);
        $this->assertSame('SELECT 2', $body['data']['events'][0]['label']);
        $this->assertTrue($body['pagination']['has_more']);
        $this->assertSame(5, $body['pagination']['matching_count']);
    }

    /**
     * The waterfall renders rows with marks, ticks and a detail pane.
     *
     * @return void
     */
    public function testWaterfallMarkup(): void
    {
        $this->seedRequest(['Timeline' => [
            'events' => [
                ['at' => 0.0, 'duration_ms' => 12.0, 'type' => 'sql', 'label' => 'SELECT 1', 'panel' => 'sqllog'],
                ['at' => 5.0, 'duration_ms' => 0.0, 'type' => 'log', 'label' => 'Boom', 'panel' => 'log'],
            ],
            'retained' => 2,
            'omitted' => 1,
            'end_ms' => 20.0,
        ]]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Timeline'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('data-dk-timeline-panel', $body);
        $this->assertStringContainsString('data-dk-timeline-entry', $body);
        $this->assertStringContainsString('--dk-timeline-width', $body);
        $this->assertStringContainsString('data-dk-select="timeline-filter"', $body);
        $this->assertStringContainsString('data-dk-timeline-detail="timeline-1"', $body);
        $this->assertStringContainsString('Timeline incomplete: 1 omitted.', $body);
        $this->assertStringContainsString('Open inspector', $body);
        $this->assertStringContainsString('data-dk-workspace-mode="focus"', $body);
        $this->assertStringContainsString('data-dk-workspace-back', $body);
        $this->assertStringContainsString('data-dk-inspector-focus-detail', $body);
    }
}
