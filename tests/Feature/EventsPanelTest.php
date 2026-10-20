<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the events inspector workspace.
 */
class EventsPanelTest extends TestCase
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
     * Groups filter by source with overview/payload/source tabs.
     *
     * @return void
     */
    public function testEventGroupsWithDetailTabs(): void
    {
        $this->seedRequest([
            'Events' => [
                'events' => [
                    [
                        'id' => 1,
                        'key' => 'event-1',
                        'name' => 'Model.afterSave',
                        'display_name' => 'afterSave',
                        'short' => 'afterSave',
                        'namespace' => 'Model.',
                        'search' => 'model.aftersave app\\model\\table\\orderstable',
                        'occurrence_count' => 3,
                        'dispatches' => 3,
                        'first_sequence' => 31,
                        'last_sequence' => 33,
                        'first_at_ms' => null,
                        'occurrences' => [
                            ['sequence' => 31, 'at_ms' => null],
                            ['sequence' => 32, 'at_ms' => null],
                            ['sequence' => 33, 'at_ms' => null],
                        ],
                        'listeners' => [
                            [
                                'label' => 'App\Listeners\AuditLog@handle',
                                'name' => 'App\Listeners\AuditLog@handle',
                                'queued' => false,
                                'file' => 'src/Listeners/AuditLog.php',
                                'line' => 11,
                                'source' => ['file' => 'src/Listeners/AuditLog.php', 'line' => 11],
                                'app' => true,
                                'registrations' => 2,
                            ],
                        ],
                        'listener_count' => 1,
                        'completed_listener_count' => 1,
                        'queued_listener_count' => 0,
                        'duplicate_registration_count' => 1,
                        'duplicate_registrations' => 1,
                        'duplicate' => true,
                        'listener_outcome' => 'completed',
                        'listener_outcome_label' => 'Completed',
                        'listener_summary' => '1 registration completed.',
                        'subject' => 'App\Model\Table\OrdersTable',
                        'source' => 'application',
                        'data_keys' => ['entity', 'options'],
                        'data_shapes' => [],
                        'payload_shape' => [
                            [
                                'position' => 1,
                                'name' => 'entity',
                                'type' => 'App\Model\Entity\Order',
                                'fields' => ['id', 'total'],
                                'field_count' => 2,
                            ],
                        ],
                        'data_count' => 2,
                        'dispatch_sources' => [],
                        'dispatch_source_count' => 0,
                        'dispatch_source_omitted_count' => 0,
                        'occurrence_omitted_count' => 0,
                        'broadcast' => false,
                        'related_inspector' => null,
                    ],
                ],
                'summary' => [
                    'count' => 1,
                    'retained_count' => 1,
                    'application_count' => 1,
                    'framework_count' => 0,
                    'dispatches' => 3,
                    'duplicates' => 1,
                ],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Events'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();

        $this->assertStringContainsString('data-dk-events-panel', $body);
        $this->assertStringContainsString('data-dk-event-entry', $body);
        $this->assertStringContainsString('data-dk-select="events-filter"', $body);
        $this->assertStringContainsString('data-dk-event-detail="event-1"', $body);
        $this->assertStringContainsString('Model.afterSave', $body);
        $this->assertStringContainsString('Duplicate registration', $body);
        $this->assertStringContainsString('Listener handling', $body);
        $this->assertStringContainsString('AuditLog', $body);
        $this->assertStringContainsString('Payload shape', $body);
        $this->assertStringContainsString('Dispatch locations', $body);
        $this->assertStringContainsString('Dispatch timeline', $body);
        $this->assertStringContainsString('data-dk-tab-panel="event-1:overview"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="event-1:payload"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="event-1:source"', $body);
        $this->assertStringContainsString('Field names and types are shown', $body);
    }

    /**
     * @return void
     */
    public function testEmptyEventsRenderEmptyState(): void
    {
        $this->seedRequest([
            'Events' => ['events' => [], 'summary' => ['count' => 0, 'dispatches' => 0, 'duplicates' => 0]],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Events'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $this->assertStringContainsString('No events were dispatched', (string)$this->_response->getBody());
    }

    /**
     * @return void
     */
    public function testOwnedRecordingRendersNotice(): void
    {
        $this->seedRequest([
            'Events' => [
                'events' => [],
                'summary' => ['count' => 0, 'dispatches' => 0, 'duplicates' => 0],
                'recording_owner' => 'RecordingEventManager',
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Events'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->_response->getBody();
        $this->assertStringContainsString('data-dk-events-owned', $body);
        $this->assertStringContainsString('RecordingEventManager', $body);
        $this->assertStringNotContainsString('No events were dispatched', $body);
    }
}
