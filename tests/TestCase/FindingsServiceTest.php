<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\FindingsService;

/**
 * Covers one fixture per findings rule.
 */
class FindingsServiceTest extends TestCase
{
    /**
     * @return void
     */
    public function testSlowQueryFinding(): void
    {
        $service = new FindingsService(['slow_query_ms' => 100]);
        $result = $service->build([
            'SqlLog' => ['items' => [
                ['sql' => 'SELECT * FROM users WHERE id = 1', 'took' => 250, 'params' => []],
            ]],
        ]);

        $this->assertSame(1, $result['retained']);
        $this->assertSame(0, $result['omitted']);
        $this->assertSame('query.slow', $result['findings'][0]['rule_id']);
        $this->assertSame('warning', $result['findings'][0]['severity']);
        $this->assertSame('queries', $result['findings'][0]['panel']);
    }

    /**
     * @return void
     */
    public function testRepeatedQueryFinding(): void
    {
        $service = new FindingsService(['slow_query_ms' => 100]);
        $result = $service->build([
            'SqlLog' => ['items' => [
                ['sql' => 'SELECT * FROM settings', 'took' => 5, 'params' => []],
                ['sql' => 'SELECT * FROM settings', 'took' => 6, 'params' => []],
            ]],
        ]);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('query.repeated', $result['findings'][0]['rule_id']);
    }

    /**
     * @return void
     */
    public function testNPlusOneFinding(): void
    {
        $service = new FindingsService(['slow_query_ms' => 100]);
        $result = $service->build([
            'SqlLog' => ['items' => [
                ['sql' => 'SELECT * FROM comments WHERE post_id = 1', 'took' => 5, 'params' => [1]],
                ['sql' => 'SELECT * FROM comments WHERE post_id = 2', 'took' => 5, 'params' => [2]],
                ['sql' => 'SELECT * FROM comments WHERE post_id = 3', 'took' => 5, 'params' => [3]],
            ]],
        ]);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('query.n_plus_one', $result['findings'][0]['rule_id']);
        $this->assertSame('error', $result['findings'][0]['severity']);
    }

    /**
     * @return void
     */
    public function testHighCacheMissRateFinding(): void
    {
        $service = new FindingsService();
        $result = $service->build([
            'Cache' => ['metrics' => [
                'default' => ['set' => 0, 'delete' => 0, 'get hit' => 1, 'get miss' => 9],
            ]],
        ]);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('cache.high_miss_rate', $result['findings'][0]['rule_id']);
        $this->assertSame('cache', $result['findings'][0]['panel']);
    }

    /**
     * @return void
     */
    public function testLowCacheReadsStayQuiet(): void
    {
        $service = new FindingsService();
        $result = $service->build([
            'Cache' => ['metrics' => [
                'default' => ['set' => 0, 'delete' => 0, 'get hit' => 0, 'get miss' => 2],
            ]],
        ]);

        $this->assertSame([], $result['findings']);
    }

    /**
     * @return void
     */
    public function testCapturedErrorFinding(): void
    {
        $service = new FindingsService();
        $result = $service->build([
            'Log' => ['logger' => ['error' => [['2026-10-13 00:00:01', 'boom', 1700000000]]]],
        ]);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('exception.captured', $result['findings'][0]['rule_id']);
        $this->assertSame('error', $result['findings'][0]['severity']);
    }

    /**
     * @return void
     */
    public function testRequestErrorFinding(): void
    {
        $service = new FindingsService();
        $result = $service->build([], ['status_code' => 500, 'method' => 'GET', 'url' => '/broken']);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('request.error', $result['findings'][0]['rule_id']);
        $this->assertSame('error', $result['findings'][0]['severity']);
        $this->assertSame('request', $result['findings'][0]['panel']);
    }

    /**
     * @return void
     */
    public function testClientErrorIsWarning(): void
    {
        $service = new FindingsService();
        $result = $service->build([], ['status_code' => 404]);

        $this->assertSame('request.error', $result['findings'][0]['rule_id']);
        $this->assertSame('warning', $result['findings'][0]['severity']);
    }

    /**
     * @return void
     */
    public function testSlowRequestFinding(): void
    {
        $service = new FindingsService(['slow_request_ms' => 1000]);
        $result = $service->build(
            [],
            ['status_code' => 200, 'method' => 'GET', 'url' => '/slow', 'duration_ms' => 1500],
        );

        $this->assertSame(1, $result['retained']);
        $this->assertSame('request.slow', $result['findings'][0]['rule_id']);
        $this->assertSame('warning', $result['findings'][0]['severity']);
        $this->assertSame('timeline', $result['findings'][0]['panel']);
    }

    /**
     * @return void
     */
    public function testFastRequestStaysQuiet(): void
    {
        $service = new FindingsService(['slow_request_ms' => 1000]);
        $result = $service->build(
            [],
            ['status_code' => 200, 'method' => 'GET', 'url' => '/fast', 'duration_ms' => 120],
        );

        $this->assertSame([], $result['findings']);
    }

    /**
     * @return void
     */
    public function testDeprecationsFinding(): void
    {
        $service = new FindingsService();
        $result = $service->build([
            'Deprecations' => ['app' => [
                ['file' => 'src/Model/Table/UsersTable.php', 'line' => 10, 'message' => 'old call'],
            ]],
        ]);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('deprecations.present', $result['findings'][0]['rule_id']);
    }

    /**
     * @return void
     */
    public function testTruncationFinding(): void
    {
        $service = new FindingsService();
        $result = $service->build(['Timeline' => ['omitted' => 3, 'retained' => 10, 'events' => []]]);

        $this->assertSame(1, $result['retained']);
        $this->assertSame('collector.truncated', $result['findings'][0]['rule_id']);
        $this->assertSame('timeline', $result['findings'][0]['panel']);
    }

    /**
     * @return void
     */
    public function testFindingsCapAndSeverityOrder(): void
    {
        $service = new FindingsService([
            'slow_query_ms' => 100,
            'findings' => ['max_findings' => 2],
        ]);
        $result = $service->build([
            'SqlLog' => ['items' => [
                ['sql' => 'SELECT * FROM alpha', 'took' => 200, 'params' => []],
                ['sql' => 'SELECT * FROM beta', 'took' => 300, 'params' => []],
                ['sql' => 'SELECT * FROM gamma', 'took' => 400, 'params' => []],
            ]],
        ]);

        $this->assertSame(3, $result['retained']);
        $this->assertSame(1, $result['omitted']);
        $this->assertCount(2, $result['findings']);
    }

    /**
     * @return void
     */
    public function testEmptyRequestHasNoFindings(): void
    {
        $service = new FindingsService();
        $result = $service->build([]);

        $this->assertSame([], $result['findings']);
        $this->assertSame(0, $result['retained']);
        $this->assertSame(0, $result['omitted']);
    }

    /**
     * @return void
     */
    public function testPanelKeysFollowStoredTitles(): void
    {
        $service = new FindingsService(['slow_query_ms' => 100]);
        $result = $service->build(
            ['SqlLog' => ['items' => [
                ['sql' => 'SELECT * FROM users', 'took' => 250, 'params' => []],
            ]]],
            [],
            ['SqlLog' => 'Sql Log'],
        );

        $this->assertSame('queries', $result['findings'][0]['panel']);
    }
}
