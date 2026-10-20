<?php
declare(strict_types=1);

namespace Crustum\DebugBar\View;

use Cake\View\View;

/**
 * A view class that is used for AJAX responses.
 *
 * Currently only switches the default layout and sets the response type
 * which just maps to text/html by default.
 *
 * @property \Crustum\DebugBar\View\Helper\SimpleGraphHelper $SimpleGraph
 * @property \Crustum\DebugBar\View\Helper\ToolbarHelper $Toolbar
 * @ported-from \DebugKit\View\AjaxView
 */
class AjaxView extends View
{
    /**
     * Initialization hook method.
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();
        $this->response = $this->response->withType('ajax');
    }
}
