<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase\Panel;

use Cake\Event\Event;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Panel\EnvironmentPanel;
use stdClass;

/**
 * Class EnvironmentPanelTest
 *
 * @ported-from \DebugKit\Test\TestCase\Panel\EnvironmentPanelTest
 */
class EnvironmentPanelTest extends TestCase
{
    /**
     * @var EnvironmentPanel
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
        $this->panel = new EnvironmentPanel();
    }

    /**
     * Teardown method.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        unset($this->panel);
    }

    /**
     * test shutdown
     *
     * @return void
     */
    public function testShutdown(): void
    {
        $controller = new stdClass();
        $event = new Event('Controller.shutdown', $controller);
        $_SERVER['TEST_URL_1'] = 'mysql://user:password@localhost/my_db';

        $this->panel->shutdown($event);
        $output = $this->panel->data();
        $this->assertIsArray($output);
        $this->assertSame(['php', 'ini', 'cake', 'app', 'includePaths', 'includedFiles'], array_keys($output));
        $this->assertSame('mysql://user:password@localhost/my_db', $output['php']['TEST_URL_1']);
    }

    /**
     * test data collects without shutdown for CLI and worker rows
     *
     * No controller dispatches `Controller.shutdown` outside HTTP, so the
     * panel must fill itself on first read instead of rendering Versions only.
     *
     * @return void
     */
    public function testDataCollectsWithoutShutdown(): void
    {
        $output = $this->panel->data();
        $this->assertIsArray($output);
        $this->assertSame(['php', 'ini', 'cake', 'app', 'includePaths', 'includedFiles'], array_keys($output));
        $this->assertArrayHasKey('PHP_VERSION', $output['php']);
    }
}
