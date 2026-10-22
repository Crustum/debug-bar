<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\I18n\DateTime;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the recent-requests endpoint behind the request switcher.
 */
class RequestsRecentTest extends TestCase
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
     * Recent requests come back newest first with switcher fields.
     *
     * @return void
     */
    public function testRecentListsSummariesNewestFirst(): void
    {
        $first = $this->seedRequest([], ['url' => '/first', 'method' => 'GET', 'status_code' => 200]);
        $second = $this->seedRequest([], ['url' => '/second', 'method' => 'POST', 'status_code' => 201]);
        $requests = $this->fetchTable('Crustum/DebugBar.Requests');
        $requests->updateAll(['requested_at' => new DateTime('2026-01-01 10:00:00')], ['id' => $first->id]);
        $requests->updateAll(['requested_at' => new DateTime('2026-01-02 10:00:00')], ['id' => $second->id]);

        $this->get('/debug-bar/requests/recent.json');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertSame(2, $body['pagination']['count']);
        $this->assertSame('/second', $body['requests'][0]['url']);
        $this->assertSame('POST', $body['requests'][0]['method']);
        $this->assertSame(201, $body['requests'][0]['status_code']);
        $this->assertSame('/first', $body['requests'][1]['url']);
        $this->assertArrayHasKey('requested_at', $body['requests'][0]);
        $this->assertArrayHasKey('content_type', $body['requests'][0]);
    }

    /**
     * The limit is honored and capped by configuration.
     *
     * @return void
     */
    public function testRecentHonorsLimit(): void
    {
        $this->seedRequest([], ['url' => '/one']);
        $this->seedRequest([], ['url' => '/two']);
        $this->seedRequest([], ['url' => '/three']);

        $this->get('/debug-bar/requests/recent.json?limit=2');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertSame(2, $body['pagination']['limit']);
        $this->assertSame(2, $body['pagination']['count']);
        $this->assertCount(2, $body['requests']);
    }

    /**
     * The default limit covers the switcher cap.
     *
     * @return void
     */
    public function testRecentDefaultsToCap(): void
    {
        $this->seedRequest([], ['url' => '/one']);
        $this->seedRequest([], ['url' => '/two']);
        $this->seedRequest([], ['url' => '/three']);

        $this->get('/debug-bar/requests/recent.json');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertSame(20, $body['pagination']['limit']);
        $this->assertSame(3, $body['pagination']['count']);
    }
}
