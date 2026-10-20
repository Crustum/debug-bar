<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\View\Element;

use Cake\Http\ServerRequest as Request;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\View\AjaxView;

/**
 * Render coverage for the environment tab layout.
 *
 * Sections render as horizontal tabs with single-line debug-tree rows;
 * vertical disclosures are gone.
 */
class EnvironmentPanelElementTest extends TestCase
{
    /**
     * Shared view instance.
     *
     * @var \Crustum\DebugBar\View\AjaxView
     */
    protected $View;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->View = new AjaxView(new Request());
        $this->View->loadHelper('Crustum/DebugBar.Credentials');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->View);
        parent::tearDown();
    }

    /**
     * Sections render as tabs with the versions tab preselected.
     *
     * @return void
     */
    public function testSectionsRenderAsTabs(): void
    {
        $html = (string)$this->View->element('Crustum/DebugBar.environment_panel', [
            'app' => ['APP_NAME' => 'TestApp'],
            'cake' => [],
            'ini' => [],
            'php' => ['PHP_VERSION' => '8.5.0', 'memory_limit' => '512M'],
            'includedFiles' => [],
            'includePaths' => [],
        ]);

        $this->assertStringContainsString('role="tablist"', $html);
        $this->assertStringContainsString('data-dk-tab="environment:versions"', $html);
        $this->assertStringContainsString('data-dk-tab="environment:app"', $html);
        $this->assertStringContainsString('data-dk-tab="environment:php"', $html);
        $this->assertStringNotContainsString('data-dk-tab="environment:cake"', $html);
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('CakePHP Version', $html);
        $this->assertStringContainsString('8.5.0', $html);
        $this->assertStringContainsString('TestApp', $html);
        $this->assertStringNotContainsString('<details', $html);
    }
}
