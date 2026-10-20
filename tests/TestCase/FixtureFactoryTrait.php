<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Test\TestCase;

use Cake\Error\Debugger;

/**
 * @ported-from \DebugKit\Test\TestCase\FixtureFactoryTrait
 */

trait FixtureFactoryTrait
{
    protected function makeRequest()
    {
        $requests = $this->getTableLocator()->get('Crustum/DebugBar.Requests');
        $request = $requests->newEntity([
            'url' => '/panels',
            'requested_at' => time(),
        ]);

        return $requests->saveOrFail($request);
    }

    protected function makePanel($request, $name = 'Crustum/DebugBar.Request', $title = 'Request', $element = 'Crustum/DebugBar.request_panel', $content = null)
    {
        $content ??= [
            'attributes' => [
                Debugger::exportVarAsNodes([
                    'params' => [
                        'plugin' => null,
                        'controller' => 'Tasks',
                        'action' => 'add',
                        '_ext' => null,
                        'pass' => [],
                    ],
                ]),
            ],
            'query' => Debugger::exportVarAsNodes([]),
            'data' => Debugger::exportVarAsNodes([]),
            'get' => Debugger::exportVarAsNodes([]),
            'cookie' => Debugger::exportVarAsNodes([
                'toolbarDisplay' => 'show',
            ]),
            'params' => [
                'plugin' => null,
                'controller' => 'Tasks',
                'action' => 'add',
                '_ext' => null,
                'pass' => [],
            ],
        ];

        $panels = $this->getTableLocator()->get('Crustum/DebugBar.Panels');
        $panel = $panels->newEntity([
            'request_id' => $request->id,
            'panel' => $name,
            'title' => $title,
            'element' => $element,
            'content' => serialize($content),
        ]);

        return $panels->saveOrFail($panel);
    }
}
