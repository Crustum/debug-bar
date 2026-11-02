<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Event\EventManager;
use Cake\TestSuite\LogTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\PanelRegistry;
use Crustum\DebugBar\Service\TimelineBuilder;
use TestApp\Panel\SimplePanel;
use TestApp\Panel\TimelineStubPanel;
use TestApp\Panel\TimelineThrowingPanel;

/**
 * Covers timeline merging, ordering and omission counts.
 */
class TimelineBuilderTest extends TestCase
{
    use LogTestTrait;

    /**
     * @return \Crustum\DebugBar\Panel\PanelRegistry
     */
    protected function coreRegistry(): PanelRegistry
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Timer');
        $registry->load('Crustum/DebugBar.SqlLog');
        $registry->load('Crustum/DebugBar.Log');
        $registry->load('Crustum/DebugBar.Cache');

        return $registry;
    }

    /**
     * @return void
     */
    public function testMergesSourcesInTimeOrder(): void
    {
        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build([
            'Timer' => ['timers' => [
                'Controller: action' => ['start' => 0.01, 'end' => 0.05, 'time' => 0.04],
            ]],
            'SqlLog' => ['items' => [
                ['sql' => 'SELECT 1', 'took' => 2, 'start' => $start + 0.02, 'callsite' => null],
            ]],
            'Log' => ['logger' => ['error' => [['2026-10-12 00:00:01', 'boom', $start + 0.03]]]],
            'Cache' => ['logs' => []],
        ], $start, $start + 0.1, $this->coreRegistry(), 500);

        $this->assertSame(0, $result['omitted']);
        $this->assertSame(3, $result['retained']);
        $labels = array_column($result['events'], 'type');
        $this->assertSame(['timer', 'sql', 'log'], $labels);
        $this->assertSame('sqllog', $result['events'][1]['panel']);
        $this->assertSame(1, $result['events'][1]['ref']);
        $this->assertSame('log-0', $result['events'][2]['ref']);
        $this->assertSame(['key' => 'queries', 'label' => 'Sql Log'], $result['inspectors']['sqllog']);
        $this->assertSame(['key' => 'timer', 'label' => 'Timer'], $result['inspectors']['timer']);
    }

    /**
     * @return void
     */
    public function testCapsEventsAndReportsOmissions(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.SqlLog');

        $builder = new TimelineBuilder();
        $items = [];
        for ($i = 0; $i < 5; $i++) {
            $items[] = ['sql' => "SELECT {$i}", 'took' => 1, 'start' => 1000.0 + $i / 1000];
        }

        $result = $builder->build(['SqlLog' => ['items' => $items]], 1000.0, 1001.0, $registry, 3);

        $this->assertSame(5, $result['retained']);
        $this->assertSame(2, $result['omitted']);
        $this->assertCount(3, $result['events']);
    }

    /**
     * @return void
     */
    public function testToleratesMissingSources(): void
    {
        $builder = new TimelineBuilder();
        $result = $builder->build([], 1000.0, 1001.0, $this->coreRegistry(), 500);

        $this->assertSame([], $result['events']);
        $this->assertSame(0, $result['retained']);
        $this->assertSame(0, $result['omitted']);
        $this->assertSame(1000.0, $result['end_ms']);
    }

    /**
     * Cache spy lines render as short operation labels, not raw lines.
     *
     * @return void
     */
    public function testCacheEventsUseParsedLabels(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Cache');

        $builder = new TimelineBuilder();
        $result = $builder->build([
            'Cache' => ['logs' => ['info: :default: get `default_sessions` - 0.00069ms']],
        ], 1000.0, 1001.0, $registry, 500);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('cache', $result['events'][0]['panel']);
        $this->assertSame('get `default_sessions`', $result['events'][0]['label']);
        $this->assertSame('cache-0', $result['events'][0]['ref']);
    }

    /**
     * @return void
     */
    public function testCacheEventsUseFractionalTimestamps(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Cache');

        $builder = new TimelineBuilder();
        $now = time();
        $stamp = gmdate('Y-m-d H:i:s', $now - 10 + 1) . '.500000 +00:00';
        $result = $builder->build([
            'Cache' => ['logs' => ["{$stamp} info: :default: get `key` - 0.250ms"]],
        ], (float)($now - 10), (float)$now, $registry, 500);

        $this->assertSame(1, $result['retained']);
        $this->assertEqualsWithDelta(1500.0, $result['events'][0]['at'], 2.0);
        $this->assertSame(0.25, $result['events'][0]['duration_ms']);
    }

    /**
     * @return void
     */
    public function testQueryEventsUseCapturedStart(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.SqlLog');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build([
            'SqlLog' => ['items' => [
                ['sql' => 'SELECT 1', 'took' => 2, 'start' => $start + 0.02, 'callsite' => null],
            ]],
        ], $start, $start + 0.1, $registry, 500);

        $this->assertEqualsWithDelta(20.0, $result['events'][0]['at'], 0.001);
        $this->assertSame(2.0, $result['events'][0]['duration_ms']);
    }

    /**
     * Registry order with a third-party source needs no core changes.
     *
     * @return void
     */
    public function testCustomSourceViaRegistry(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Timer');
        $registry->load('TimelineStub', ['className' => TimelineStubPanel::class]);

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            [
                'Timer' => ['timers' => [
                    'Controller: action' => ['start' => 0.01, 'end' => 0.05, 'time' => 0.04],
                ]],
                'TimelineStub' => [],
            ],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(2, $result['retained']);
        $types = array_column($result['events'], 'type');
        $this->assertContains('stub', $types);
        $this->assertContains('timer', $types);
        $this->assertSame(['key' => 'timeline_stub', 'label' => 'Timeline Stub'], $result['inspectors']['stub']);
    }

    /**
     * A panel that is not loaded never reaches the waterfall, even with data.
     *
     * @return void
     */
    public function testUnloadedPanelIsExcluded(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Timer');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $collected = [
            'Timer' => ['timers' => [
                'Controller: action' => ['start' => 0.01, 'end' => 0.05, 'time' => 0.04],
            ]],
            'SqlLog' => ['items' => [
                ['sql' => 'SELECT 1', 'took' => 2, 'start' => $start + 0.02, 'callsite' => null],
            ]],
        ];
        $result = $builder->build($collected, $start, $start + 0.1, $registry, 500);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('timer', $result['events'][0]['type']);
    }

    /**
     * Loaded panels without the contract are ignored.
     *
     * @return void
     */
    public function testNonSourcePanelIsIgnored(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Timer');
        $registry->load('Simple', ['className' => SimplePanel::class]);

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            [
                'Timer' => ['timers' => [
                    'Controller: action' => ['start' => 0.01, 'end' => 0.05, 'time' => 0.04],
                ]],
                'Simple' => ['anything' => true],
            ],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('timer', $result['events'][0]['type']);
    }

    /**
     * A throwing source never breaks the waterfall; it is only logged.
     *
     * @return void
     */
    public function testThrowingSourceIsSkippedWithLog(): void
    {
        $this->setupLog(['warning']);

        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Timer');
        $registry->load('TimelineThrowing', ['className' => TimelineThrowingPanel::class]);

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            [
                'Timer' => ['timers' => [
                    'Controller: action' => ['start' => 0.01, 'end' => 0.05, 'time' => 0.04],
                ]],
                'TimelineThrowing' => [],
            ],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('timer', $result['events'][0]['type']);
        $this->assertLogMessageContains('warning', 'TimelineThrowing');
    }

    /**
     * HTTP client requests appear with captured start and duration.
     *
     * @return void
     */
    public function testHttpClientSource(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.HttpClient');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            ['HttpClient' => ['requests' => [
                [
                    'method' => 'GET',
                    'url' => 'https://example.test/users',
                    'start' => $start + 0.02,
                    'duration_ms' => 12.5,
                    'execution' => 1,
                    'stack' => [],
                ],
            ]]],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('http', $result['events'][0]['type']);
        $this->assertSame('httpclient', $result['events'][0]['panel']);
        $this->assertEqualsWithDelta(20.0, $result['events'][0]['at'], 0.001);
        $this->assertSame(12.5, $result['events'][0]['duration_ms']);
        $this->assertSame('http-1', $result['events'][0]['ref']);
    }

    /**
     * Model operations appear as point events.
     *
     * @return void
     */
    public function testModelsSource(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Models');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            ['Models' => ['groups' => [
                [
                    'operations' => [
                        [
                            'op' => 'find',
                            'table' => 'Articles',
                            'class' => 'App\Model\Table\ArticlesTable',
                            'identifier' => 'id:1',
                            'at' => $start + 0.03,
                        ],
                    ],
                ],
            ]]],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('model', $result['events'][0]['type']);
        $this->assertSame('models', $result['events'][0]['panel']);
        $this->assertEqualsWithDelta(30.0, $result['events'][0]['at'], 0.001);
        $this->assertSame('model-1', $result['events'][0]['ref']);
    }

    /**
     * View renders appear at their capture time.
     *
     * @return void
     */
    public function testViewsSource(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Views');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            ['Views' => ['renders' => [
                ['file' => 'templates' . DIRECTORY_SEPARATOR . 'Pages' . DIRECTORY_SEPARATOR . 'home.php', 'order' => 1, 'at' => $start + 0.04],
                ['file' => 'templates' . DIRECTORY_SEPARATOR . 'layout' . DIRECTORY_SEPARATOR . 'default.php', 'order' => 2],
            ]]],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('view', $result['events'][0]['type']);
        $this->assertSame('views', $result['events'][0]['panel']);
        $this->assertEqualsWithDelta(40.0, $result['events'][0]['at'], 0.001);
        $this->assertSame('view-1', $result['events'][0]['ref']);
    }

    /**
     * Events appear at first dispatch; groups without time stay out.
     *
     * @return void
     */
    public function testEventsSource(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Events');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            ['Events' => ['events' => [
                ['name' => 'Model.afterSave', 'display_name' => 'afterSave', 'key' => 'event-1', 'first_at' => $start + 0.05],
                ['name' => 'Controller.shutdown', 'display_name' => 'shutdown', 'key' => 'event-2'],
            ]]],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('event', $result['events'][0]['type']);
        $this->assertSame('events', $result['events'][0]['panel']);
        $this->assertEqualsWithDelta(50.0, $result['events'][0]['at'], 0.001);
        $this->assertSame('event-1', $result['events'][0]['ref']);
        $this->assertSame('Model.afterSave', $result['events'][0]['label']);
    }

    /**
     * Sent mail appears with its subject.
     *
     * @return void
     */
    public function testMailSource(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Mail');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            ['Mail' => ['emails' => [
                ['headers' => ['Subject' => 'Welcome'], 'at' => $start + 0.06, 'duration_ms' => 0.0],
            ]]],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('mail', $result['events'][0]['type']);
        $this->assertSame('mail', $result['events'][0]['panel']);
        $this->assertEqualsWithDelta(60.0, $result['events'][0]['at'], 0.001);
        $this->assertStringContainsString('Welcome', $result['events'][0]['label']);
        $this->assertSame('mail-0', $result['events'][0]['ref']);
    }

    /**
     * Exceptions appear with class and message.
     *
     * @return void
     */
    public function testExceptionsSource(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Exceptions');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            ['Exceptions' => ['exceptions' => [
                ['class' => 'RuntimeException', 'message' => 'boom', 'file' => '/app/src/Foo.php', 'line' => 12, 'at' => $start + 0.07],
            ]]],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('exception', $result['events'][0]['type']);
        $this->assertSame('exceptions', $result['events'][0]['panel']);
        $this->assertEqualsWithDelta(70.0, $result['events'][0]['at'], 0.001);
        $this->assertSame(['file' => '/app/src/Foo.php', 'line' => 12], $result['events'][0]['source']);
        $this->assertSame('exception-1', $result['events'][0]['ref']);
    }

    /**
     * Authorization checks appear with ability and result.
     *
     * @return void
     */
    public function testAuthorizationSource(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Authorization');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            ['Authorization' => ['decisions' => [
                ['ability' => 'Articles.view', 'result' => 'allowed', 'key' => 'authorization-1', 'at' => $start + 0.08],
            ]]],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('authorization', $result['events'][0]['type']);
        $this->assertSame('authorization', $result['events'][0]['panel']);
        $this->assertEqualsWithDelta(80.0, $result['events'][0]['at'], 0.001);
        $this->assertSame('authorization-1', $result['events'][0]['ref']);
    }

    /**
     * Deprecations appear with message and file source.
     *
     * @return void
     */
    public function testDeprecationsSource(): void
    {
        $registry = new PanelRegistry(new EventManager());
        $registry->load('Crustum/DebugBar.Deprecations');

        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build(
            ['Deprecations' => ['app' => [
                ['file' => '/app/src/Old.php', 'line' => 3, 'message' => 'Old::thing() is deprecated', 'at' => $start + 0.09],
            ]]],
            $start,
            $start + 0.1,
            $registry,
            500,
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('deprecation', $result['events'][0]['type']);
        $this->assertSame('deprecations', $result['events'][0]['panel']);
        $this->assertEqualsWithDelta(90.0, $result['events'][0]['at'], 0.001);
        $this->assertSame(['file' => '/app/src/Old.php', 'line' => 3], $result['events'][0]['source']);
    }
}
