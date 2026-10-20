<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Middleware;

use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Database\Driver\Sqlite;
use Cake\Datasource\ConnectionManager;
use Cake\Http\CallbackStream;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Middleware\DebugBarMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\MessageInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;

/**
 * Test the middleware object
 *
 * @ported-from \DebugKit\Test\TestCase\Middleware\DebugKitMiddlewareTest
 */
class DebugBarMiddlewareTest extends TestCase
{
    /**
     * Tables to reset each test.
     *
     * @var array<string>
     */
    protected array $fixtures = [
        'plugin.Crustum/DebugBar.Requests',
        'plugin.Crustum/DebugBar.Panels',
    ];

    protected ?array $oldConfig = null;

    /**
     * @var bool
     */
    protected bool $restore = false;

    /**
     * setup
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $connection = ConnectionManager::get('test');
        $this->skipIf($connection->getDriver() instanceof Sqlite, 'This test fails in CI with sqlite');
        $this->oldConfig = Configure::read('DebugBar');
        $this->restore = $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] ?? false;
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = true;
    }

    /**
     * tearDown
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        Configure::write('DebugBar', $this->oldConfig);
        $GLOBALS['FORCE_DEBUGBAR_TOOLBAR'] = $this->restore;
    }

    protected function handler(): MockObject
    {
        return $this->getMockBuilder(RequestHandlerInterface::class)
            ->onlyMethods(['handle'])
            ->getMock();
    }

    /**
     * A throwing handler still stores the failed request and rethrows.
     *
     * @return void
     */
    public function testInvokeStoresFailedRequest(): void
    {
        $request = new ServerRequest([
            'url' => '/broken',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);

        $handler = $this->handler();
        $handler->expects($this->once())
            ->method('handle')
            ->willThrowException(new RuntimeException('Boom'));

        $middleware = new DebugBarMiddleware();

        try {
            $middleware->process($request, $handler);
            $this->fail('The original error must bubble to the error handler.');
        } catch (RuntimeException $runtimeException) {
            $this->assertSame('Boom', $runtimeException->getMessage());
        }

        $requests = $this->getTableLocator()->get('Crustum/DebugBar.Requests');
        $result = $requests->find()
            ->orderBy(['Requests.requested_at' => 'DESC'])
            ->contain('Panels')
            ->first();

        $this->assertSame(500, $result->status_code);
        $found = false;
        foreach ((array)$result->panels as $panel) {
            if (($panel->panel ?? null) !== 'Exceptions') {
                continue;
            }

            $found = true;
            // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $stored = @unserialize((string)$panel->content);
            $this->assertSame('RuntimeException', $stored['exceptions'][0]['class']);
            $this->assertSame('Boom', $stored['exceptions'][0]['message']);
        }

        $this->assertTrue($found, 'Exceptions row missing on the failed request.');
    }

    /**
     * Ensure data is saved for HTML requests
     *
     * @return void
     */
    public function testInvokeSaveData(): void
    {
        $request = new ServerRequest([
            'url' => '/articles',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = new Response([
            'statusCode' => 200,
            'type' => 'text/html',
            'body' => '<html><title>test</title><body><p>some text</p></body>',
        ]);

        $handler = $this->handler();
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $middleware = new DebugBarMiddleware();
        $response = $middleware->process($request, $handler);
        $this->assertInstanceOf(Response::class, $response, 'Should return the response');

        $requests = $this->getTableLocator()->get('Crustum/DebugBar.Requests');
        $result = $requests->find()
            ->orderBy(['Requests.requested_at' => 'DESC'])
            ->contain('Panels')
            ->first();

        $this->assertSame('GET', $result->method);
        $this->assertSame('/articles', $result->url);
        $this->assertNotEmpty($result->requested_at);
        $this->assertNotEmpty('text/html', $result->content_type);
        $this->assertSame(200, $result->status_code);
        $this->assertGreaterThan(1, $result->panels);

        $this->assertSame('Timer', $result->panels[11]->panel);
        $this->assertSame('Crustum/DebugBar.timer_panel', $result->panels[11]->element);
        $this->assertNotNull($result->panels[11]->summary);
        $this->assertSame('Timer', $result->panels[11]->title);

        $timeStamp = filectime(Plugin::path('Crustum/DebugBar') . 'webroot' . DS . 'js' . DS . 'inject-iframe.js');

        $expected = '<html><title>test</title><body><p>some text</p>' .
            '<script id="__debug_bar_script" data-id="' . $result->id . '" ' .
            'data-url="http://localhost/" type="module" src="/debug_bar/js/inject-iframe.js?' . $timeStamp . '"></script>' .
            '</body>';
        $body = (string)$response->getBody();
        $this->assertTextEquals($expected, $body);
    }

    /**
     * Ensure data is saved for HTML requests
     *
     * @return void
     */
    public function testInvokeInjectCspNonce(): void
    {
        $request = new ServerRequest([
            'url' => '/articles',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $request = $request->withAttribute('cspScriptNonce', 'csp-nonce');
        Router::setRequest($request);

        $response = new Response([
            'statusCode' => 200,
            'type' => 'text/html',
            'body' => '<html><title>test</title><body><p>some text</p></body>',
        ]);

        $handler = $this->handler();
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $middleware = new DebugBarMiddleware();
        $response = $middleware->process($request, $handler);
        $this->assertInstanceOf(Response::class, $response, 'Should return the response');

        $body = (string)$response->getBody();
        $this->assertStringContainsString('nonce="csp-nonce"', $body);
    }

    /**
     * Ensure that streaming results are tracked, but not modified.
     *
     * @return void
     */
    public function testInvokeNoModifyBinaryResponse(): void
    {
        $request = new ServerRequest([
            'url' => '/articles',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = new Response([
            'statusCode' => 200,
            'type' => 'text/html',
        ]);

        $handler = $this->handler();
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function ($req) use ($response): MessageInterface {
                $stream = new CallbackStream(fn(): string => 'hi!');

                return $response->withBody($stream);
            });
        $middleware = new DebugBarMiddleware();
        $result = $middleware->process($request, $handler);
        $this->assertInstanceOf(Response::class, $result, 'Should return a response');

        $requests = $this->getTableLocator()->get('Crustum/DebugBar.Requests');
        $total = $requests->find()->where(['url' => '/articles'])->count();

        $this->assertSame(1, $total, 'Should track response');
        $body = $result->getBody();
        $this->assertStringNotContainsString('__debug_bar', '' . $body);
        $this->assertStringNotContainsString('<script', '' . $body);
    }

    /**
     * Ensure that no script tag is added to non html responses.
     *
     * @return void
     */
    public function testInvokeNoModifyNonHtmlResponse(): void
    {
        $request = new ServerRequest([
            'url' => '/articles',
            'environment' => ['REQUEST_METHOD' => 'GET'],
        ]);
        $response = new Response([
            'statusCode' => 200,
            'type' => 'text/plain',
            'body' => 'OK',
        ]);

        $handler = $this->handler();
        $handler->expects($this->once())
            ->method('handle')
            ->willReturn($response);
        $middleware = new DebugBarMiddleware();
        $result = $middleware->process($request, $handler);
        $this->assertInstanceOf(Response::class, $result, 'Should return a response');

        $requests = $this->getTableLocator()->get('Crustum/DebugBar.Requests');
        $total = $requests->find()->where(['url' => '/articles'])->count();

        $this->assertSame(1, $total, 'Should track response');
        $body = (string)$result->getBody();
        $this->assertSame('OK', $body);
    }

    /**
     * Test that configuration is correctly passed to the service
     *
     * @return void
     */
    public function testConfigIsPassed(): void
    {
        $config = ['foo' => 'bar'];
        Configure::write('DebugBar', $config);
        $layer = new DebugBarMiddleware();
        $prop = new ReflectionProperty(DebugBarMiddleware::class, 'service');

        $service = $prop->getValue($layer);
        $this->assertSame('bar', $service->getConfig('foo'));
    }
}
