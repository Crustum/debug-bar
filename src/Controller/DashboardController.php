<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Controller;

use Cake\Event\EventInterface;
use Cake\Http\Response;

/**
 * Dashboard and common DebugBar backend.
 *
 * @property \Crustum\DebugBar\Model\Table\RequestsTable $Requests
 * @ported-from \DebugKit\Controller\DashboardController
 */
class DashboardController extends DebugBarController
{
    /**
     * Before filter handler.
     *
     * @param \Cake\Event\EventInterface $event The event.
     * @return void
     */
    public function beforeFilter(EventInterface $event): void
    {
        parent::beforeFilter($event);

        $this->viewBuilder()->setLayout('dashboard');
    }

    /**
     * Dashboard.
     *
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function index(): void
    {
        $requestsModel = $this->fetchTable('Crustum/DebugBar.Requests');

        $data = [
            'driver' => $requestsModel->getConnection()->getDriver()::class,
            'rows' => $requestsModel->find()->count(),
        ];

        $this->set('connection', $data);
    }

    /**
     * Reset SQLite DB.
     *
     * @return \Cake\Http\Response|null
     */
    public function reset(): ?Response
    {
        $this->request->allowMethod('post');
        /** @var \Crustum\DebugBar\Model\Table\RequestsTable $requestsModel */
        $requestsModel = $this->fetchTable('Crustum/DebugBar.Requests');

        $requestsModel->Panels->deleteAll('1=1');
        $requestsModel->deleteAll('1=1');

        return $this->redirect(['action' => 'index']);
    }
}
