<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Controller;

use Cake\Cache\Cache;
use Cake\Http\Exception\NotFoundException;
use Cake\View\JsonView;
use Throwable;

/**
 * Provides utility features need by the toolbar.
 *
 * @ported-from \DebugKit\Controller\ToolbarController
 */
class ToolbarController extends DebugBarController
{
    /**
     * @inheritDoc
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->viewBuilder()->setClassName(JsonView::class);
    }

    /**
     * Clear a named cache.
     *
     * @return void
     * @throws \Cake\Http\Exception\NotFoundException
     */
    public function clearCache(): void
    {
        $this->request->allowMethod('post');
        $name = $this->request->getData('name');
        if (!$name) {
            throw new NotFoundException('Invalid cache engine name.');
        }

        if (!Cache::getConfig($name)) {
            throw new NotFoundException(sprintf('Unknown cache engine "%s".', $name));
        }

        $success = Cache::clear($name);
        $message = $success ?
            sprintf('%s cache cleared.', $name) :
            sprintf('%s cache could not be cleared.', $name);
        $this->set(['success' => $success, 'message' => $message]);
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }

    /**
     * Clear every configured cache engine.
     *
     * @return void
     */
    public function clearAllCaches(): void
    {
        $this->request->allowMethod('post');

        $cleared = [];
        $failed = [];
        foreach (Cache::configured() as $name) {
            try {
                if (Cache::clear($name)) {
                    $cleared[] = $name;
                } else {
                    $failed[] = $name;
                }
            } catch (Throwable) {
                $failed[] = $name;
            }
        }

        $success = $failed === [];
        if ($success) {
            $message = sprintf('All %d caches cleared.', count($cleared));
        } else {
            $message = sprintf('Could not clear: %s.', implode(', ', $failed));
        }

        $this->set([
            'success' => $success,
            'message' => $message,
            'cleared' => $cleared,
            'failed' => $failed,
        ]);
        $this->viewBuilder()->setOption('serialize', ['success', 'message', 'cleared', 'failed']);
    }

    /**
     * Clear the session.
     *
     * @return void
     */
    public function clearSession(): void
    {
        $this->request->allowMethod('post');
        $session = $this->request->getSession();
        $session->destroy();

        $success = true;
        $message = 'Session cleared.';
        $this->set(['success' => $success, 'message' => $message]);
        $this->viewBuilder()->setOption('serialize', ['success', 'message']);
    }
}
