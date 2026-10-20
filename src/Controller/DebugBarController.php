<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Controller;

use Authorization\AuthorizationService;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Exception\NotFoundException;
use Cake\Log\Log;

/**
 * DebugBar Controller.
 *
 * @ported-from \DebugKit\Controller\DebugKitController
 */
class DebugBarController extends Controller
{
    /**
     * Before filter handler.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function beforeFilter(EventInterface $event): void
    {
        if (!Configure::read('debug')) {
            throw new NotFoundException('Not available without debug mode on.');
        }

        $authorizationService = $this->getRequest()->getAttribute('authorization');
        if ($authorizationService instanceof AuthorizationService) {
            if (Configure::read('DebugBar.ignoreAuthorization')) {
                $authorizationService->skipAuthorization();
            } else {
                Log::info(
                    'Cake Authorization plugin is enabled. If you would like ' .
                    'to force DebugBar to ignore it, set `DebugBar.ignoreAuthorization` ' .
                    ' Configure option to true.',
                );
            }
        }
    }
}
