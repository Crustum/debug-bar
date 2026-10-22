<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\Error\Debugger;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the views inspector and its lazy data endpoint.
 */
class ViewsPanelTest extends TestCase
{
    use DebugBarTestTrait;
    use IntegrationTestTrait;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resetToolbarStore();
    }

    /**
     * @return string Panel id.
     */
    protected function seedViewsPanel(): string
    {
        $this->seedRequest([
            'Views' => [
                'renders' => [
                    ['file' => 'templates/Pages/home.php', 'app' => true, 'order' => 1],
                    ['file' => 'templates/Pages/home.php', 'app' => true, 'order' => 2],
                    ['file' => 'vendor/cakephp/cakephp/templates/layout/default.php', 'app' => false, 'order' => 3],
                    ['file' => 'vendor/cakedc/admin/templates/element/view.php', 'app' => false, 'origin' => 'plugin', 'plugin' => 'Admin', 'order' => 4],
                ],
                'vars' => Debugger::exportVarAsNodes(['title' => 'Hello', 'table' => ['alias' => 'Users']]),
                'summary' => ['count' => 2],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');

        return (string)$panels->find()->where(['panel' => 'Views'])->firstOrFail()->id;
    }

    /**
     * The render list filters by origin with a lazy select.
     *
     * @return void
     */
    public function testViewsListWithLazySelect(): void
    {
        $panelId = $this->seedViewsPanel();

        $this->get('/debug-bar/panels/view/' . $panelId);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('data-dk-views-panel', $body);
        $this->assertStringContainsString('data-dk-views-url="/debug-bar/panels/view-data/' . $panelId . '"', $body);
        $this->assertStringContainsString('data-dk-select="views-filter"', $body);
        $this->assertStringContainsString('data-dk-select="views-render"', $body);
        $this->assertStringContainsString('data-dk-view-entry', $body);
        $this->assertStringContainsString('data-dk-view-detail="view-1"', $body);
        $this->assertStringContainsString('templates/Pages/home.php', $body);
        $this->assertStringContainsString('data-dk-views-loading', $body);
        $this->assertStringContainsString('data-dk-views-error', $body);
        $this->assertStringContainsString('Passed data', $body);
        $this->assertStringContainsString('Plugin (1)', $body);
        $this->assertStringContainsString('data-dk-filter-group="plugin"', $body);
        $this->assertStringContainsString('>Admin<', $body);
    }

    /**
     * One render serves with the other views stripped.
     *
     * @return void
     */
    public function testViewDataServesSingleRender(): void
    {
        $panelId = $this->seedViewsPanel();

        $this->get('/debug-bar/panels/view-data/' . $panelId . '.json?render=3');
        $this->assertResponseOk();
        $body = json_decode((string)$this->response->getBody(), true);

        $this->assertSame('vendor/cakephp/cakephp/templates/layout/default.php', $body['file']);
        $this->assertSame(3, $body['order']);
        $this->assertStringContainsString('Hello', $body['html']);
        $this->assertStringContainsString('data-dk-debug-tree', $body['html']);
        $this->assertStringContainsString('<details', $body['html']);
        $this->assertStringContainsString('Users', $body['html']);
    }

    /**
     * Unknown renders are a 404 for the inspector error state.
     *
     * @return void
     */
    public function testViewDataMissingRenderIsNotFound(): void
    {
        $panelId = $this->seedViewsPanel();

        $this->get('/debug-bar/panels/view-data/' . $panelId . '.json?render=9');
        $this->assertResponseCode(404);
    }
}
