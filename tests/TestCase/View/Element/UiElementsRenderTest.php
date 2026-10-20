<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\View\Element;

use Cake\Http\ServerRequest as Request;
use Cake\TestSuite\TestCase;
use Cake\View\View;

/**
 * Isolated render coverage for the shared `ui/` component kit.
 */
class UiElementsRenderTest extends TestCase
{
    /**
     * Shared view instance.
     *
     * @var \Cake\View\View
     */
    protected $View;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->View = new View(new Request());
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
     * @return void
     */
    public function testInspectorHeadingRendersLabelAndDescription(): void
    {
        $html = (string)$this->View->element('Crustum/DebugBar.ui/inspector_heading', [
            'label' => 'Queries',
            'description' => 'All captured statements.',
        ]);

        $this->assertStringContainsString('data-dk-inspector-section-heading', $html);
        $this->assertStringContainsString('Queries', $html);
        $this->assertStringContainsString('All captured statements.', $html);
    }

    /**
     * @return void
     */
    public function testInspectorDetailEmptyRendersMessage(): void
    {
        $html = (string)$this->View->element('Crustum/DebugBar.ui/inspector_detail_empty', [
            'message' => 'Choose a query to inspect its evidence.',
        ]);

        $this->assertStringContainsString('data-dk-detail-empty', $html);
        $this->assertStringContainsString('Choose a query', $html);
    }

    /**
     * @return void
     */
    public function testInspectorDefinitionListRendersRows(): void
    {
        $html = (string)$this->View->element('Crustum/DebugBar.ui/inspector_definition_list', [
            'rows' => [
                ['term' => 'Connection', 'html' => 'default'],
                ['term' => 'Driver', 'html' => 'Sqlite'],
            ],
        ]);

        $this->assertStringContainsString('data-dk-definition-list', $html);
        $this->assertStringContainsString('Connection', $html);
        $this->assertStringContainsString('Sqlite', $html);
    }

    /**
     * @return void
     */
    public function testInspectorSourceFactMarksAppAndVendorFrames(): void
    {
        $app = (string)$this->View->element('Crustum/DebugBar.ui/inspector_source_fact', [
            'file' => 'src/Model/Table/DaysTable.php',
            'line' => 41,
            'app' => true,
        ]);
        $vendor = (string)$this->View->element('Crustum/DebugBar.ui/inspector_source_fact', [
            'file' => 'vendor/cakephp/cakephp/src/ORM/Table.php',
            'line' => 1,
            'app' => false,
        ]);

        $this->assertStringContainsString('data-dk-source-frame="app"', $app);
        $this->assertStringContainsString('DaysTable.php:41', $app);
        $this->assertStringContainsString('data-dk-source-frame="vendor"', $vendor);
    }
}
