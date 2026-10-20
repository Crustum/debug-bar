<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\View\Helper;

use Cake\Error\Debug\NodeInterface;
use Cake\Error\Debugger;
use Cake\Http\ServerRequest as Request;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Crustum\DebugBar\View\Helper\ToolbarHelper;
use DOMDocument;
use DOMXPath;

/**
 * Class ToolbarHelperTestCase
 *
 * @ported-from \DebugKit\Test\TestCase\View\Helper\ToolbarHelperTest
 */
class ToolbarHelperTest extends TestCase
{
    /**
     * @var View
     */
    protected $View;

    /**
     * @var ToolbarHelper
     */
    protected $Toolbar;

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
        $this->Toolbar = new ToolbarHelper($this->View);
    }

    /**
     * Tear Down
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->Toolbar);
    }

    public function testDumpNodesSorted(): void
    {
        $path = '//*[@class="cake-debug-array-item"]/*[@class="cake-debug-string"]';
        $data = ['z' => 1, 'a' => 99, 'm' => 123];
        $nodes = array_map(fn($v): NodeInterface => Debugger::exportVarAsNodes($v), $data);
        $result = $this->Toolbar->dumpNodes($nodes);
        $doc = new DOMDocument();
        $doc->loadHTML($result);

        $elements = new DOMXPath($doc);

        $result = [];
        foreach ($elements->query($path) as $elem) {
            $result[] = $elem->nodeValue;
        }

        $expected = ["'z'", "'a'", "'m'"];
        $this->assertSame($expected, $result);

        $this->Toolbar->setSort(true);
        $result = $this->Toolbar->dumpNodes($nodes);
        $doc = new DOMDocument();
        $doc->loadHTML($result);

        $elements = new DOMXPath($doc);

        $result = [];
        foreach ($elements->query($path) as $elem) {
            $result[] = $elem->nodeValue;
        }

        $expected = ["'a'", "'m'", "'z'"];
        $this->assertSame($expected, $result);
    }
}
