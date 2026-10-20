<?php
declare(strict_types=1);

namespace TestApp\Panel;

use Cake\Controller\Controller;
use Crustum\DebugBar\DebugPanel;

/**
 * Class TestPanel
 *
 * @ported-from \DebugKit\TestApp\Panel\TestPanel
 */
class TestPanel extends DebugPanel
{
    /**
     * Startup
     *
     * @param Controller $controller
     */
    public function startup(Controller $controller): void
    {
        $controller->testPanel = true;
    }
}
