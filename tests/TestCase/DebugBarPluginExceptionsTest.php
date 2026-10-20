<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\DebugBarPlugin;
use Crustum\DebugBar\Panel\ExceptionsPanel;
use Crustum\DebugBar\ToolbarService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionProperty;
use RuntimeException;

/**
 * Coverage for the `Exception.beforeRender` collector wiring.
 *
 * Cake's `ErrorHandlerMiddleware` sits inside our middleware and renders
 * error pages itself, so handled throwables only reach the panel through
 * the event it dispatches before rendering.
 */
class DebugBarPluginExceptionsTest extends TestCase
{
    /**
     * Resets the panel inbox and the listener singleton.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        ExceptionsPanel::clear();
        $flag = new ReflectionProperty(DebugBarPlugin::class, 'exceptionListenerInstalled');
        $flag->setValue(null, false);
    }

    /**
     * Resets the panel inbox.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        ExceptionsPanel::clear();
        parent::tearDown();
    }

    /**
     * Builds a toolbar service with the exceptions panel enabled.
     *
     * @return \Crustum\DebugBar\ToolbarService
     */
    protected function service(): ToolbarService
    {
        return new ToolbarService(EventManager::instance(), [
            'panels' => ['Crustum/DebugBar.Exceptions' => true],
        ]);
    }

    /**
     * Dispatched event data reaches the panel inbox.
     *
     * @return void
     */
    public function testListenerRecordsDispatchedException(): void
    {
        (new DebugBarPlugin())->setExceptionListener($this->service());

        EventManager::instance()->dispatch(new Event(
            'Exception.beforeRender',
            null,
            ['exception' => new RuntimeException('boom')],
        ));

        $recorded = ExceptionsPanel::recorded();
        $this->assertCount(1, $recorded);
        $this->assertSame('boom', $recorded[0]->getMessage());
    }

    /**
     * Exceptions handled by Cake's error middleware are recorded.
     *
     * @return void
     */
    public function testErrorHandlerMiddlewareRecordsHandledException(): void
    {
        (new DebugBarPlugin())->setExceptionListener($this->service());

        $middleware = new ErrorHandlerMiddleware(['log' => false]);
        $handler = new class implements RequestHandlerInterface {
            /**
             * Throws a probe exception instead of responding.
             *
             * @param \Psr\Http\Message\ServerRequestInterface $request The request.
             * @return \Psr\Http\Message\ResponseInterface Never returns.
             */
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                throw new RuntimeException('probe-boom');
            }
        };
        $response = $middleware->process(new ServerRequest(['url' => '/probe-boom']), $handler);

        $this->assertSame(500, $response->getStatusCode());
        $recorded = ExceptionsPanel::recorded();
        $this->assertCount(1, $recorded);
        $this->assertSame('probe-boom', $recorded[0]->getMessage());
    }
}
