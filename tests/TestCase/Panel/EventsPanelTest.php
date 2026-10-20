<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\Event\EventList;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Event\CallsiteEventList;
use Crustum\DebugBar\Panel\EventsPanel;
use stdClass;

/**
 * Covers event grouping through the manager event list.
 */
class EventsPanelTest extends TestCase
{
    /**
     * @var \Cake\Event\EventList|null
     */
    protected $previousList;

    /**
     * @var \Cake\Event\EventManager|null
     */
    protected $previousManager;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->previousManager = EventManager::instance();
        $this->previousList = EventManager::instance()->getEventList();
        EventManager::instance()->setEventList(new EventList());
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        if ($this->previousManager !== null) {
            EventManager::instance($this->previousManager);
        }

        if ($this->previousList === null) {
            EventManager::instance()->setEventList(new EventList());
        } else {
            EventManager::instance()->setEventList($this->previousList);
        }

        (new EventsPanel())->initialize();
        EventManager::instance()->off('Test.debugEvent');
        parent::tearDown();
    }

    /**
     * Dispatches group by name with listener entries and duplicates.
     *
     * @return void
     */
    public function testDispatchesGroupWithDuplicates(): void
    {
        EventManager::instance()->on('Test.debugEvent', function (): void {
        });
        $subject = new ServerRequest();
        EventManager::instance()->dispatch(new Event('Test.debugEvent', $subject, ['id' => 1]));
        EventManager::instance()->dispatch(new Event('Test.debugEvent', $subject, ['id' => 2]));

        $panel = new EventsPanel();
        $data = $panel->data();

        $this->assertCount(1, $data['events']);
        $this->assertSame('Test.debugEvent', $data['events'][0]['name']);
        $this->assertSame('debugEvent', $data['events'][0]['display_name']);
        $this->assertSame(2, $data['events'][0]['occurrence_count']);
        $this->assertSame(1, $data['events'][0]['first_sequence']);
        $this->assertSame(2, $data['events'][0]['last_sequence']);
        $this->assertSame(1, $data['events'][0]['listener_count']);
        $this->assertSame('Closure', $data['events'][0]['listeners'][0]['label']);
        $this->assertSame('Completed', $data['events'][0]['listener_outcome_label']);
        $this->assertTrue($data['events'][0]['duplicate']);
        $this->assertSame(['id'], $data['events'][0]['data_keys']);
        $this->assertSame('framework', $data['events'][0]['source']);
        $this->assertSame(1, $data['summary']['duplicates']);
        // Dispatch sources resolve to the subject class definition file.
        $this->assertCount(1, $data['events'][0]['dispatch_sources']);
        $this->assertStringContainsString(
            'ServerRequest.php',
            $data['events'][0]['dispatch_sources'][0]['source'],
        );
        $this->assertSame(2, $data['events'][0]['dispatch_sources'][0]['count']);
        $this->assertSame(1, $data['events'][0]['dispatch_source_count']);
        $this->assertNull($data['recording_owner']);
    }

    /**
     * The same callable registered twice flags a duplicate registration.
     *
     * @return void
     */
    public function testDuplicateListenerRegistration(): void
    {
        $listener = function (): void {
        };
        EventManager::instance()->on('Test.dupEvent', $listener);
        EventManager::instance()->on('Test.dupEvent', $listener);
        EventManager::instance()->dispatch(new Event('Test.dupEvent', new stdClass()));

        $panel = new EventsPanel();
        $data = $panel->data();

        $this->assertCount(2, $data['events'][0]['listeners']);
        $this->assertSame(2, $data['events'][0]['listeners'][0]['registrations']);
        $this->assertSame(1, $data['events'][0]['duplicate_registration_count']);
        $this->assertTrue($data['events'][0]['duplicate']);

        EventManager::instance()->off('Test.dupEvent');
    }

    /**
     * Framework subjects classify as framework; values never stored.
     *
     * @return void
     */
    public function testFrameworkSourceAndShapeOnly(): void
    {
        EventManager::instance()->dispatch(
            new Event('Test.cakeEvent', new ServerRequest(), ['secret' => 'value']),
        );

        $panel = new EventsPanel();
        $data = $panel->data();

        $this->assertSame('framework', $data['events'][0]['source']);
        $this->assertSame(['secret'], $data['events'][0]['data_keys']);
        $this->assertStringNotContainsString('value', (string)json_encode($data));
    }

    /**
     * Subject-less events never break collection (server-level dispatches
     * carry no subject — `getSubject()` throws on those).
     *
     * @return void
     */
    public function testSubjectLessEventGroupedAsFramework(): void
    {
        EventManager::instance()->dispatch(new Event('Server.buildMiddleware', null, ['middleware' => []]));

        $panel = new EventsPanel();
        $data = $panel->data();

        $this->assertCount(1, $data['events']);
        $this->assertSame('Server.buildMiddleware', $data['events'][0]['name']);
        $this->assertNull($data['events'][0]['subject']);
        $this->assertSame('framework', $data['events'][0]['source']);
    }

    /**
     * The panel list remembers the true dispatch site, not the subject file.
     *
     * @return void
     */
    public function testDispatchSitePointsAtDispatcher(): void
    {
        EventManager::instance()->setEventList(new CallsiteEventList());
        (new EventsPanel())->initialize();
        EventManager::instance()->dispatch(new Event('Test.sited', new stdClass()));

        $data = (new EventsPanel())->data();

        $this->assertCount(1, $data['events']);
        $source = (string)$data['events'][0]['dispatch_sources'][0]['source'];
        $this->assertStringContainsString('EventsPanelTest.php', $source);
        $this->assertStringNotContainsString('stdClass', $source);
    }

    /**
     * A manager that diverts recording elsewhere leaves an owned empty set.
     *
     * Third-party recording managers override `addEventToList` without
     * filling Cake's list — the panel reports the owner instead of a fake
     * "no events" stream.
     *
     * @return void
     */
    public function testOwnedRecordingReportsOwner(): void
    {
        $owner = new class extends EventManager {
            public function addEventToList(EventInterface $event)
            {
                return $this;
            }
        };
        EventManager::instance($owner);
        (new EventsPanel())->initialize();
        EventManager::instance()->dispatch(new Event('Test.owned', new stdClass()));

        $data = (new EventsPanel())->data();

        $this->assertSame([], $data['events']);
        $this->assertNotNull($data['recording_owner']);
        $this->assertSame(0, $data['summary']['count']);
    }

    /**
     * Collection drains the list so workers never bleed across requests.
     *
     * @return void
     */
    public function testDataDrainsList(): void
    {
        EventManager::instance()->dispatch(new Event('Test.once', new stdClass()));

        $panel = new EventsPanel();
        $this->assertCount(1, $panel->data()['events']);
        $this->assertSame([], $panel->data()['events']);
    }
}
