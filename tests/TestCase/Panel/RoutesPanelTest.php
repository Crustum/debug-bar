<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\RoutesPanel;

/**
 * Class RoutesPanelTest
 *
 * @ported-from \DebugKit\Test\TestCase\Panel\RoutesPanelTest
 */
class RoutesPanelTest extends TestCase
{
    /**
     * @var RoutesPanel
     */
    protected $panel;

    /**
     * set up
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        Router::defaultRouteClass('DashedRoute');
        $routes = Router::createRouteBuilder('/');
        $routes->connect('/', ['controller' => 'Pages', 'action' => 'display', 'home']);
        $routes->connect('/pages/*', ['controller' => 'Pages', 'action' => 'display']);
        $routes->fallbacks('DashedRoute');

        // Force Router::$initialized to be true.
        Router::url(['controller' => 'Pages', 'action' => 'display', 'contact']);

        $this->panel = new RoutesPanel();
    }

    /**
     * Test that the log panel outputs a summary.
     *
     * @return void
     */
    public function testSummary(): void
    {
        $this->panel->initialize();
        $this->assertSame('4', $this->panel->summary());

        Router::createRouteBuilder('/')
            ->connect('/test', ['controller' => 'Pages', 'action' => 'display', 'home']);
        $this->assertSame('5', $this->panel->summary());
    }
}
