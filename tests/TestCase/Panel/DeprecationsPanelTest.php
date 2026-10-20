<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Event\Event;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\DeprecationsPanel;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use function Cake\Core\deprecationWarning;

/**
 * Class DeprecationsPanelTest
 *
 * @ported-from \DebugKit\Test\TestCase\Panel\DeprecationsPanelTest
 */
class DeprecationsPanelTest extends TestCase
{
    /**
     * @var DeprecationsPanel
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
        DeprecationsPanel::clearDeprecatedErrors();

        $this->loadPlugins(['Crustum/DebugBar']);
        $this->panel = new DeprecationsPanel();

        set_error_handler(function ($code, $message, $file, $line, $context = null): void {
            DeprecationsPanel::addDeprecatedError(['code' => $code, 'message' => $message, 'file' => $file, 'line' => $line, 'context' => $context]);
        });
        try {
            deprecationWarning('x.x', 'Something going away', 0);
            deprecationWarning('x.x', 'Something else going away', 0);
            trigger_error('Raw error', E_USER_DEPRECATED);
        } finally {
            restore_error_handler();
        }
    }

    #[WithoutErrorHandler]
    public function testShutdown(): void
    {
        $event = new Event('Panel.shutdown');
        $this->panel->shutdown($event);
        $data = $this->panel->data();

        $this->assertArrayHasKey('app', $data);
        $this->assertArrayHasKey('cake', $data);
        $this->assertArrayHasKey('vendor', $data);
        $this->assertArrayHasKey('plugins', $data);
        $this->assertArrayHasKey('other', $data);
        $this->assertCount(3, $data['plugins']['Crustum/DebugBar']);

        $error = $data['plugins']['Crustum/DebugBar'][0];
        $this->assertStringContainsString('Something going away', $error['message']);
        $this->assertArrayHasKey('niceFile', $error);
        $this->assertArrayHasKey('line', $error);
    }

    public function testSummary(): void
    {
        $this->assertSame('1', $this->panel->summary());
    }
}
