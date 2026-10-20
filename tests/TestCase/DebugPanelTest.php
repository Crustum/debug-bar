<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\TestSuite\TestCase;
use TestApp\Panel\SimplePanel;

/**
 * DebugPanel TestCase
 *
 * @ported-from \DebugKit\Test\TestCase\DebugPanelTest
 */
class DebugPanelTest extends TestCase
{
    /**
     * @var SimplePanel
     */
    protected $panel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->panel = new SimplePanel();
    }

    public function testTitle(): void
    {
        $this->assertSame('Simple', $this->panel->title());
    }

    public function testElementName(): void
    {
        $this->assertSame('Crustum/DebugBar.simple_panel', $this->panel->elementName());

        $this->panel->plugin = 'Derpy';
        $this->assertSame('Derpy.simple_panel', $this->panel->elementName());

        $this->panel->plugin = '';
        $this->assertSame('simple_panel', $this->panel->elementName());
    }

    /**
     * @return void
     */
    public function testContextsCoverWebAndCli(): void
    {
        $this->assertSame(['web', 'cli'], $this->panel->contexts());
    }
}
