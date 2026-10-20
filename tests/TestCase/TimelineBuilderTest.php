<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\TimelineBuilder;

/**
 * Covers timeline merging, ordering and omission counts.
 */
class TimelineBuilderTest extends TestCase
{
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
        ], $start, $start + 0.1, 500);

        $this->assertSame(0, $result['omitted']);
        $this->assertSame(3, $result['retained']);
        $labels = array_column($result['events'], 'type');
        $this->assertSame(['timer', 'sql', 'log'], $labels);
        $this->assertSame('sqllog', $result['events'][1]['panel']);
        $this->assertSame(1, $result['events'][1]['ref']);
    }

    /**
     * @return void
     */
    public function testCapsEventsAndReportsOmissions(): void
    {
        $builder = new TimelineBuilder();
        $items = [];
        for ($i = 0; $i < 5; $i++) {
            $items[] = ['sql' => "SELECT {$i}", 'took' => 1, 'start' => 1000.0 + $i / 1000];
        }

        $result = $builder->build(['SqlLog' => ['items' => $items]], 1000.0, 1001.0, 3);

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
        $result = $builder->build([], 1000.0, 1001.0, 500);

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
        $builder = new TimelineBuilder();
        $result = $builder->build([
            'Cache' => ['logs' => ['info: :default: get `default_sessions` - 0.00069ms']],
        ], 1000.0, 1001.0, 500);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('cache', $result['events'][0]['panel']);
        $this->assertSame('get `default_sessions`', $result['events'][0]['label']);
    }

    /**
     * @return void
     */
    public function testCacheEventsUseFractionalTimestamps(): void
    {
        $builder = new TimelineBuilder();
        $now = time();
        $stamp = gmdate('Y-m-d H:i:s', $now - 10 + 1) . '.500000 +00:00';
        $result = $builder->build([
            'Cache' => ['logs' => ["{$stamp} info: :default: get `key` - 0.250ms"]],
        ], (float)($now - 10), (float)$now, 500);

        $this->assertSame(1, $result['retained']);
        $this->assertEqualsWithDelta(1500.0, $result['events'][0]['at'], 2.0);
        $this->assertSame(0.25, $result['events'][0]['duration_ms']);
    }

    /**
     * @return void
     */
    public function testQueryEventsUseCapturedStart(): void
    {
        $builder = new TimelineBuilder();
        $start = 1000.0;
        $result = $builder->build([
            'SqlLog' => ['items' => [
                ['sql' => 'SELECT 1', 'took' => 2, 'start' => $start + 0.02, 'callsite' => null],
            ]],
        ], $start, $start + 0.1, 500);

        $this->assertEqualsWithDelta(20.0, $result['events'][0]['at'], 0.001);
        $this->assertSame(2.0, $result['events'][0]['duration_ms']);
    }
}
