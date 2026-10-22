<?php
declare(strict_types=1);

use Cake\Routing\Route\DashedRoute;
use Cake\Routing\RouteBuilder;

/**
 * DebugBar plugin routes.
 *
 * @var \Cake\Routing\RouteBuilder $routes
 */
$routes->plugin(
    'Crustum/DebugBar',
    function (RouteBuilder $builder): void {
        $builder->setExtensions('json');
        $builder->setRouteClass(DashedRoute::class);

        $builder->connect(
            '/toolbar/clear-cache',
            ['controller' => 'Toolbar', 'action' => 'clearCache']
        );
        $builder->connect(
            '/toolbar/clear-all-caches',
            ['controller' => 'Toolbar', 'action' => 'clearAllCaches']
        );
        $builder->connect(
            '/toolbar/clear-session',
            ['controller' => 'Toolbar', 'action' => 'clearSession']
        );
        $builder->connect(
            '/toolbar/*',
            ['controller' => 'Requests', 'action' => 'view']
        );
        $builder->connect(
            '/requests/recent',
            ['controller' => 'Requests', 'action' => 'recent']
        );
        $builder->connect(
            '/requests/panels/*',
            ['controller' => 'Requests', 'action' => 'panels']
        );
        $builder->connect(
            '/panels/explain',
            ['controller' => 'Panels', 'action' => 'explain'],
            ['_method' => 'POST']
        );
        $builder->connect(
            '/panels/view-data/*',
            ['controller' => 'Panels', 'action' => 'viewData']
        );
        $builder->connect(
            '/panels/view-variable/*',
            ['controller' => 'Panels', 'action' => 'viewVariable']
        );
        $builder->connect(
            '/panels/view/*',
            ['controller' => 'Panels', 'action' => 'view']
        );
        $builder->connect(
            '/panels/*',
            ['controller' => 'Panels', 'action' => 'index']
        );

        $builder->connect(
            '/composer/check-dependencies',
            ['controller' => 'Composer', 'action' => 'checkDependencies']
        );
        $builder->connect(
            '/composer/audit',
            ['controller' => 'Composer', 'action' => 'audit']
        );

        $builder->connect(
            '/attributes/query',
            ['controller' => 'Attributes', 'action' => 'query']
        );
        $builder->connect(
            '/attributes/inspect',
            ['controller' => 'Attributes', 'action' => 'inspect']
        );
        $builder->connect(
            '/attributes/warm',
            ['controller' => 'Attributes', 'action' => 'warm']
        );

        $builder->scope(
            '/mail-preview',
            function (RouteBuilder $routes): void {
                $routes->connect('/', ['action' => 'index']);
                $routes->connect('/preview', ['action' => 'email']);
                $routes->connect('/preview/*', ['action' => 'email']);
                $routes->connect('/sent/{panel}/{id}', ['action' => 'sent'], ['pass' => ['panel', 'id']]);
                $routes->connect(
                    '/sent/{panel}/{id}/attachment/{index}',
                    ['action' => 'attachment'],
                    ['pass' => ['panel', 'id', 'index']],
                );
            },
            ['controller' => 'MailPreview']
        );

        $builder->get('/', ['controller' => 'Dashboard', 'action' => 'index']);
        $builder->get('/dashboard', ['controller' => 'Dashboard', 'action' => 'index']);
        $builder->post('/dashboard/reset', ['controller' => 'Dashboard', 'action' => 'reset']);
    },
    ['path' => '/debug-bar']
);
