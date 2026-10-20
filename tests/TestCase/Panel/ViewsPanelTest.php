<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Event\Event;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Cake\View\View;
use Crustum\DebugBar\Panel\ViewsPanel;
use Crustum\DebugBar\Service\NodeFlattener;

/**
 * Covers view render capture.
 */
class ViewsPanelTest extends TestCase
{
    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        ViewsPanel::clear();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        ViewsPanel::clear();
        parent::tearDown();
    }

    /**
     * Render files record in order with a shared vars snapshot.
     *
     * @return void
     */
    public function testCapturesRendersAndVars(): void
    {
        $panel = new ViewsPanel();
        $view = new View(new ServerRequest());
        $view->set('title', 'Hello');

        $panel->captureVars(new Event('View.beforeRender', $view));
        $panel->captureFile(new Event('View.beforeRenderFile', $view), 'templates/Pages/home.php');
        $panel->captureFile(new Event('View.beforeRenderFile', $view), 'templates/layout/default.php');

        $data = $panel->data();

        $this->assertCount(2, $data['renders']);
        $this->assertSame('templates/Pages/home.php', $data['renders'][0]['file']);
        $this->assertSame(1, $data['renders'][0]['order']);
        $this->assertSame(2, $data['renders'][1]['order']);
        $this->assertTrue($data['renders'][0]['app']);
        $this->assertNotNull($data['vars']);
        $this->assertSame(['count' => 2], $data['summary']);
        // The snapshot carries names AND values, never a bare key list.
        $flat = NodeFlattener::toPlain($data['vars']);
        $this->assertSame('Hello', $flat['title']);
    }

    /**
     * Vars capture at the configured depth, not the shallow Cake default.
     *
     * @return void
     */
    public function testCapturesAtConfiguredDepth(): void
    {
        $panel = new ViewsPanel();
        $view = new View(new ServerRequest());
        $view->set('deep', ['a' => ['b' => ['c' => ['d' => 'deep']]]]);

        $panel->captureVars(new Event('View.beforeRender', $view));

        $flat = NodeFlattener::toPlain($panel->data()['vars']);
        $this->assertSame('deep', $flat['deep']['a']['b']['c']['d']);
    }

    /**
     * Integer view var names do not break capture.
     *
     * `View::getVars()` can list int keys while `View::get()` requires a
     * string — the snapshot casts instead of crashing the request.
     *
     * @return void
     */
    public function testCapturesIntegerVarNames(): void
    {
        $panel = new ViewsPanel();
        $view = new View(new ServerRequest());
        $view->set([0 => 'zero']);

        $panel->captureVars(new Event('View.beforeRender', $view));

        $flat = NodeFlattener::toPlain($panel->data()['vars']);
        $this->assertSame('zero', $flat[0]);
    }

    /**
     * Collection drains the inbox so workers never bleed across requests.
     *
     * @return void
     */
    public function testDataDrainsInbox(): void
    {
        $panel = new ViewsPanel();
        $panel->captureFile(new Event('View.beforeRenderFile', new View()), 'templates/Pages/home.php');

        $this->assertCount(1, $panel->data()['renders']);
        $this->assertSame([], $panel->data()['renders']);
    }
}
