<?php
declare(strict_types=1);

namespace TestApp;

use Cake\Core\Configure;
use Cake\Http\BaseApplication;
use Cake\Http\Middleware\CsrfProtectionMiddleware;
use Cake\Http\MiddlewareQueue;
use Cake\Routing\Middleware\AssetMiddleware;
use Cake\Routing\Middleware\RoutingMiddleware;
use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;
use Override;

/**
 * Test application for Crustum/DebugBar plugin tests.
 *
 * @ported-from \DebugKit\TestApp\Application
 */
class Application extends BaseApplication
{
    /**
     * @inheritDoc
     */
    #[Override]
    public function bootstrap(): void
    {
        parent::bootstrap();

        if (!$this->plugins->has('Crustum/DebugBar')) {
            $this->addPlugin('Crustum/DebugBar', [
                'bootstrap' => true,
                'routes' => true,
            ]);
        }
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function routes(RouteBuilder $routes): void
    {
        $routes->connect('/users/{action}/*', ['controller' => 'Users']);
        $routes->scope(
            '/browser-fixtures',
            function (RouteBuilder $routes): void {
                $routes->setRouteClass(DashedRoute::class);
                $routes->connect('/{action}/*', []);
            },
            ['controller' => 'BrowserFixtures'],
        );
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middlewareQueue
            ->add(AssetMiddleware::class)
            ->add(new RoutingMiddleware($this));

        if (Configure::read('DebugBar.TestApp.csrf')) {
            $middlewareQueue->add(new CsrfProtectionMiddleware());
        }

        return $middlewareQueue;
    }
}
