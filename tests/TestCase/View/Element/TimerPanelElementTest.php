<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\View\Element;

use Cake\Core\Configure;
use Cake\Http\ServerRequest as Request;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\View\AjaxView;

/**
 * Render coverage for timer rows that carry a file path.
 *
 * `Render File:` timers link into the editor (or fall back to copy);
 * plain event timers stay unlinked text.
 */
class TimerPanelElementTest extends TestCase
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
        $this->View->loadHelper('Number');
        $this->View->loadHelper('Crustum/DebugBar.SimpleGraph');
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        Configure::delete('DebugBar.links');
        unset($this->View);
        parent::tearDown();
    }

    /**
     * Timer rows for rendered files expose the source affordance.
     *
     * @return void
     */
    public function testRenderFileTimerLinksSource(): void
    {
        $html = $this->renderPanel();

        $this->assertStringContainsString('data-dk-copy', $html);
        $this->assertStringContainsString(
            'data-dk-copy-value="/app/templates/test.php"',
            $html,
        );
        $this->assertStringContainsString('Render File: /app/templates/test.php', $html);
        $this->assertStringContainsString('><span class="dk:min-w-0 dk:break-words">', $html);
    }

    /**
     * Plain event timers stay unlinked text.
     *
     * @return void
     */
    public function testEventTimerStaysPlainText(): void
    {
        $html = $this->renderPanel();

        $this->assertStringContainsString('Event: Controller.startup', $html);
        $this->assertStringNotContainsString('data-dk-copy-value="Event:', $html);
    }

    /**
     * Enabled editor links point at the rendered file.
     *
     * @return void
     */
    public function testRenderFileTimerUsesEditorLinkWhenEnabled(): void
    {
        Configure::write('DebugBar.links', ['enabled' => true, 'editor' => 'vscode']);
        $html = $this->renderPanel();

        $this->assertStringContainsString('vscode://file//app/templates/test.php', $html);
    }

    /**
     * Render the panel with one file timer and one event timer.
     *
     * @return string
     */
    protected function renderPanel(): string
    {
        return (string)$this->View->element('Crustum/DebugBar.timer_panel', [
            'requestTime' => 0.05,
            'peakMemory' => 1024,
            'memory' => [],
            'timers' => [
                'render' => [
                    'message' => 'Render File: /app/templates/test.php',
                    'time' => 0.01,
                    'start' => 0.0,
                    'end' => 0.01,
                ],
                'event' => [
                    'message' => 'Event: Controller.startup',
                    'time' => 0.02,
                    'start' => 0.01,
                    'end' => 0.03,
                ],
            ],
        ]);
    }
}
