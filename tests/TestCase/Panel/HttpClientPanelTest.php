<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\Client;
use Cake\Http\Client\ClientEvent;
use Cake\Http\Client\Request as ClientRequest;
use Cake\Http\Client\Response as ClientResponse;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\HttpClientPanel;

/**
 * Covers HTTP client capture through client events.
 */
class HttpClientPanelTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        HttpClientPanel::clear();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        HttpClientPanel::clear();
        parent::tearDown();
    }

    /**
     * A completed request/response round trip is captured with flags.
     *
     * @return void
     */
    public function testCapturesCompletedRequest(): void
    {
        $panel = new HttpClientPanel();
        $request = new ClientRequest('https://example.com/api/items?page=2', 'GET');
        $panel->captureSend(new Event('HttpClient.beforeSend', new Client(), ['request' => $request]));
        // The real client dispatches `ClientEvent`, which carries the
        // response as the event result instead of data.
        $panel->captureResponse(new ClientEvent(
            'HttpClient.afterSend',
            new Client(),
            ['request' => $request, 'response' => new ClientResponse(['HTTP/1.1 200 OK', 'X-Test: yes'], '{"ok":true}')],
        ));

        $data = $panel->data();

        $this->assertCount(1, $data['requests']);
        $this->assertSame('GET', $data['requests'][0]['method']);
        $this->assertSame('example.com', $data['requests'][0]['host']);
        $this->assertSame('/api/items', $data['requests'][0]['path']);
        $this->assertSame('page=2', $data['requests'][0]['query']);
        $this->assertFalse($data['requests'][0]['failed']);
        $this->assertSame('200 OK', $data['requests'][0]['status_label']);
        $this->assertSame('200', $data['requests'][0]['list_status_label']);
        $this->assertSame(1, $data['requests'][0]['execution']);
        $this->assertStringContainsString('example.com', $data['requests'][0]['search']);
        $this->assertStringStartsWith('curl -X GET', $data['requests'][0]['curl']);
        $this->assertTrue($data['requests'][0]['has_source']);
        $this->assertSame(
            'https://example.com/api/items?page=2',
            $data['requests'][0]['url'],
        );
        $this->assertArrayHasKey('duration_label', $data['requests'][0]);
        $this->assertSame(1, $data['summary']['count']);
        $this->assertSame(0, $data['summary']['failed']);
        $this->assertArrayHasKey('total_label', $data['summary']);
    }

    /**
     * Requests without a response are reported as failed.
     *
     * @return void
     */
    public function testPendingRequestIsFailed(): void
    {
        $panel = new HttpClientPanel();
        $request = new ClientRequest('https://example.com/down', 'POST');
        $panel->captureSend(new Event('HttpClient.beforeSend', new Client(), ['request' => $request]));

        $data = $panel->data();

        $this->assertTrue($data['requests'][0]['failed']);
        $this->assertTrue($data['requests'][0]['attention']);
        $this->assertSame(1, $data['summary']['failed']);
    }

    /**
     * A response without a real HTTP status counts as failed, not `0`.
     *
     * @return void
     */
    public function testZeroStatusCountsAsFailed(): void
    {
        $panel = new HttpClientPanel();
        $request = new ClientRequest('https://example.com/flaky', 'GET');
        $panel->captureSend(new Event('HttpClient.beforeSend', new Client(), ['request' => $request]));
        $panel->captureResponse(new Event(
            'HttpClient.afterSend',
            new Client(),
            ['request' => $request, 'response' => new ClientResponse([], '')],
        ));

        $data = $panel->data();

        $this->assertTrue($data['requests'][0]['failed']);
        $this->assertSame('Failed', $data['requests'][0]['status_label']);
        $this->assertSame(1, $data['summary']['failed']);
    }

    /**
     * JSON bodies render pretty-printed; other bodies pass through.
     *
     * @return void
     */
    public function testJsonBodyPrettyPrint(): void
    {
        $panel = new HttpClientPanel();
        $request = new ClientRequest('https://example.com/api', 'POST');
        $panel->captureSend(new Event('HttpClient.beforeSend', new Client(), ['request' => $request]));
        $panel->captureResponse(new ClientEvent(
            'HttpClient.afterSend',
            new Client(),
            [
                'request' => $request,
                'response' => new ClientResponse(
                    ['HTTP/1.1 200 OK', 'Content-Type: application/json'],
                    '{"ok":true,"n":1}',
                ),
            ],
        ));

        $data = $panel->data();

        $this->assertSame('json', $data['requests'][0]['response_body_language']);
        $this->assertStringContainsString("\n", $data['requests'][0]['response_body_pretty']);
        $this->assertStringContainsString('"ok": true', $data['requests'][0]['response_body_pretty']);
        $this->assertSame('application/json', $data['requests'][0]['content_type']);
    }

    /**
     * Collection drains the inbox so workers never bleed across requests.
     *
     * @return void
     */
    public function testDataDrainsInbox(): void
    {
        $panel = new HttpClientPanel();
        $request = new ClientRequest('https://example.com/once', 'GET');
        $panel->captureSend(new Event('HttpClient.beforeSend', new Client(), ['request' => $request]));

        $this->assertCount(1, $panel->data()['requests']);
        $this->assertSame([], $panel->data()['requests']);
    }

    /**
     * Live clients instrumented through the global manager need no changes.
     *
     * @return void
     */
    public function testLiveClientWithMockedResult(): void
    {
        $panel = new HttpClientPanel();
        $panel->initialize();
        EventManager::instance()->on('HttpClient.beforeSend', function (Event $event): void {
            $event->setResult(new ClientResponse([], 'mocked'));
        });

        $client = new Client();
        $response = $client->get('https://example.com/live');
        $this->assertSame('mocked', (string)$response->getBody());

        $data = $panel->data();
        $this->assertCount(1, $data['requests']);
        $this->assertSame('example.com', $data['requests'][0]['host']);

        EventManager::instance()->off('HttpClient.beforeSend');
        EventManager::instance()->off('HttpClient.afterSend');
    }
}
