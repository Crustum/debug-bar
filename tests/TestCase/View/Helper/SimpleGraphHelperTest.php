<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\View\Helper;

use Cake\Http\ServerRequest as Request;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Crustum\DebugBar\View\Helper\SimpleGraphHelper;

/**
 * Class SimpleGraphHelperTestCase
 *
 * @ported-from \DebugKit\Test\TestCase\View\Helper\SimpleGraphHelperTest
 */
class SimpleGraphHelperTest extends TestCase
{
    /**
     * @var View
     */
    protected $View;

    /**
     * @var SimpleGraphHelper
     */
    protected $Graph;

    /**
     * Setup
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        Router::createRouteBuilder('/')->connect('/{controller}/{action}');

        $request = new Request();
        $request = $request->withParam('controller', 'pages')->withParam('action', 'display');

        $this->View = new View($request);
        $this->Graph = new SimpleGraphHelper($this->View);
    }

    /**
     * Tear Down
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->Graph);
    }

    /**
     * Test bar()
     *
     * @return void
     */
    public function testBar(): void
    {
        $output = $this->Graph->bar(10, 0);
        $expected = [
            ['div' => [
                'class' => 'c-graph-bar',
                'style' => 'width: 100%',
            ]],
            ['div' => [
                'class' => 'c-graph-bar__value',
                'style' => 'margin-left: 0.0000%; width: 10.0000%; min-width: 1px',
                'title' => 'Starting 0ms into the request, taking 10ms',
            ]],
            ' ',
            '/div',
            '/div',
        ];
        $this->assertHtml($expected, $output);
    }

    /**
     * Test bar() with offset
     *
     * @return void
     */
    public function testBarOffset(): void
    {
        $output = $this->Graph->bar(10, 10);
        $expected = [
            ['div' => [
                'class' => 'c-graph-bar',
                'style' => 'width: 100%',
            ]],
            ['div' => [
                'class' => 'c-graph-bar__value',
                'style' => 'margin-left: 10.0000%; width: 10.0000%; min-width: 1px',
                'title' => 'Starting 10ms into the request, taking 10ms',
            ]],
            ' ',
            '/div',
            '/div',
        ];
        $this->assertHtml($expected, $output);
    }

    /**
     * Test bar() using float offset values
     *
     * @return void
     */
    public function testBarWithFloat(): void
    {
        $output = $this->Graph->bar(10.5, 10.5);
        $expected = [
            ['div' => [
                'class' => 'c-graph-bar',
                'style' => 'width: 100%',
            ]],
            ['div' => [
                'class' => 'c-graph-bar__value',
                'style' => 'margin-left: 10.5000%; width: 10.5000%; min-width: 1px',
                'title' => 'Starting 10.5ms into the request, taking 10.5ms',
            ]],
            ' ',
            '/div',
            '/div',
        ];
        $this->assertHtml($expected, $output);
    }
}
