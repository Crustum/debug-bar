<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\Feature;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;
use Crustum\DebugBar\Test\DebugBarTestTrait;

/**
 * Covers the exceptions inspector.
 */
class ExceptionsPanelTest extends TestCase
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
     * A single exception renders the focused stream with three tabs.
     *
     * @return void
     */
    public function testSingleExceptionRendersStream(): void
    {
        $this->seedRequest([
            'Exceptions' => [
                'exceptions' => [
                    [
                        'class' => 'RuntimeException',
                        'message' => 'Boom',
                        'code' => 0,
                        'file' => 'src/Controller/TasksController.php',
                        'line' => 42,
                        'frames' => [
                            [
                                'file' => 'src/Controller/TasksController.php',
                                'line' => 42,
                                'function' => 'TasksController::add',
                                'vendor' => false,
                                'context' => [['line' => 42, 'code' => 'throw new RuntimeException();', 'current' => true]],
                            ],
                            [
                                'file' => 'vendor/cakephp/cakephp/src/Controller/Controller.php',
                                'line' => 10,
                                'function' => 'Controller::invokeAction',
                                'vendor' => true,
                                'context' => [],
                            ],
                        ],
                        'causes' => [
                            [
                                'class' => 'InvalidArgumentException',
                                'message' => 'Root cause',
                                'file' => 'src/Model/Table/TasksTable.php',
                                'line' => 7,
                            ],
                        ],
                    ],
                ],
                'summary' => ['count' => 1],
            ],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Exceptions'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $body = (string)$this->response->getBody();

        $this->assertStringContainsString('data-dk-exceptions-panel', $body);
        $this->assertStringContainsString('RuntimeException', $body);
        $this->assertStringContainsString('data-dk-tab-panel="exception-1:source"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="exception-1:stack"', $body);
        $this->assertStringContainsString('data-dk-tab-panel="exception-1:causes"', $body);
        $this->assertStringContainsString('Vendor frames (1)', $body);
        $this->assertStringContainsString('Root cause', $body);
    }

    /**
     * @return void
     */
    public function testEmptyExceptionsRenderEmptyState(): void
    {
        $this->seedRequest([
            'Exceptions' => ['exceptions' => [], 'summary' => ['count' => 0]],
        ]);
        $panels = $this->fetchTable('Crustum/DebugBar.Panels');
        $panel = $panels->find()->where(['panel' => 'Exceptions'])->firstOrFail();

        $this->get('/debug-bar/panels/view/' . $panel->id);
        $this->assertResponseOk();
        $this->assertStringContainsString('No exceptions were captured', (string)$this->response->getBody());
    }
}
