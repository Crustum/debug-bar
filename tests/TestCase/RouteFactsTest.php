<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Authentication\Middleware\AuthenticationMiddleware;
use Cake\Core\Plugin;
use Cake\Error\Middleware\ErrorHandlerMiddleware;
use Cake\Event\EventList;
use Cake\Event\EventManager;
use Cake\Http\BaseApplication;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\Route\Route;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Service\RouteFacts;

/**
 * Covers the route prefix parsing.
 */
class RouteFactsTest extends TestCase
{
    /**
     * Reports the prefix name from scoped route defaults.
     *
     * @return void
     */
    public function testPrefixReportsScopedName(): void
    {
        $route = new Route('/admin/users', ['prefix' => 'Admin', 'controller' => 'Users', 'action' => 'index']);

        $this->assertSame('Admin', RouteFacts::prefix($route));
    }

    /**
     * Reports an empty string when the route has no prefix.
     *
     * @return void
     */
    public function testPrefixReportsEmptyStringWhenScopeless(): void
    {
        $route = new Route('/users', ['controller' => 'Users', 'action' => 'index']);

        $this->assertSame('', RouteFacts::prefix($route));
    }

    /**
     * Normalizes route middleware entries to short display names.
     *
     * @return void
     */
    public function testRouteMiddlewareNormalizesEntries(): void
    {
        $route = new Route('/users', ['controller' => 'Users', 'action' => 'index']);
        $route->setMiddleware([
            RoutingMiddleware::class,
            [AuthenticationMiddleware::class, 'process'],
            function (): void {
            },
        ]);

        $middleware = RouteFacts::routeMiddleware($route);

        $this->assertSame(['RoutingMiddleware', 'AuthenticationMiddleware::process', 'Closure'], $middleware);
    }

    /**
     * Reports an empty list when the route runs bare.
     *
     * @return void
     */
    public function testRouteMiddlewareReportsEmptyListWhenBare(): void
    {
        $route = new Route('/users', ['controller' => 'Users', 'action' => 'index']);

        $this->assertSame([], RouteFacts::routeMiddleware($route));
    }

    /**
     * Reports the remembered boot stack verbatim and idempotently.
     *
     * @return void
     */
    public function testAppMiddlewareReportsList(): void
    {
        RouteFacts::forgetApplication();
        RouteFacts::rememberAppMiddleware([
            RoutingMiddleware::class,
            function (): void {
            },
        ]);

        $middleware = RouteFacts::appMiddleware();

        $this->assertSame(
            [RoutingMiddleware::class, 'Closure'],
            $middleware,
        );
        $this->assertSame($middleware, RouteFacts::appMiddleware());
        RouteFacts::forgetApplication();
    }

    /**
     * Reconstructs the stack against the remembered application.
     *
     * Constructing an application swaps the global plugin collection,
     * so it is saved and restored around the reconstruction.
     *
     * @return void
     */
    public function testAppMiddlewareReconstructsFromRememberedApplication(): void
    {
        $collection = Plugin::getCollection();
        RouteFacts::forgetApplication();
        try {
            $app = new class (CONFIG . DS . 'config') extends BaseApplication {
                /**
                 * @param \Cake\Http\MiddlewareQueue $middlewareQueue Queue under construction.
                 * @return \Cake\Http\MiddlewareQueue
                 */
                public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
                {
                    return $middlewareQueue->add(ErrorHandlerMiddleware::class);
                }
            };
            RouteFacts::rememberApplication($app);

            $this->assertSame(
                [ErrorHandlerMiddleware::class],
                RouteFacts::appMiddleware(),
            );
        } finally {
            RouteFacts::forgetApplication();
            Plugin::setCollection($collection);
        }

        $this->assertSame([], RouteFacts::appMiddleware());
    }

    /**
     * The reconstruct dispatch never lands in the tracked event stream.
     *
     * The synthetic `Server.buildMiddleware` re-dispatch mirrors boot only
     * to run hook listeners — with tracking suspended it stays out of the
     * request's event list instead of reading as a real dispatch.
     *
     * @return void
     */
    public function testAppMiddlewareReconstructionSkipsEventTracking(): void
    {
        $collection = Plugin::getCollection();
        $manager = EventManager::instance();
        $previousList = $manager->getEventList();
        $manager->setEventList(new EventList());
        RouteFacts::forgetApplication();
        try {
            $app = new class (CONFIG . DS . 'config') extends BaseApplication {
                /**
                 * @param \Cake\Http\MiddlewareQueue $middlewareQueue Queue under construction.
                 * @return \Cake\Http\MiddlewareQueue
                 */
                public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
                {
                    return $middlewareQueue->add(ErrorHandlerMiddleware::class);
                }
            };
            RouteFacts::rememberApplication($app);

            $this->assertSame(
                [ErrorHandlerMiddleware::class],
                RouteFacts::appMiddleware(),
            );
            $this->assertFalse($manager->getEventList()->hasEvent('Server.buildMiddleware'));
        } finally {
            RouteFacts::forgetApplication();
            Plugin::setCollection($collection);
            if (!$previousList instanceof EventList) {
                $manager->setEventList(new EventList());
            } else {
                $manager->setEventList($previousList);
            }
        }

        $this->assertSame([], RouteFacts::appMiddleware());
    }
}
