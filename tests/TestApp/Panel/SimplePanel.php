<?php
declare(strict_types=1);

namespace TestApp\Panel;

use Crustum\DebugBar\DebugPanel;

/**
 * Testing stub.
 *
 * @ported-from \DebugKit\TestApp\Panel\SimplePanel
 */
class SimplePanel extends DebugPanel
{
    public function setData(array $data): void
    {
        $this->data = $data;
    }
}
