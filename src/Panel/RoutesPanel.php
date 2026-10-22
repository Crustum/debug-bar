<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Panel;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;
use Cake\Routing\Router;
use Crustum\DebugBar\DebugPanel;

/**
 * A panel to get the list of connected routes for the application
 *
 * @ported-from \DebugKit\Panel\RoutesPanel
 */
class RoutesPanel extends DebugPanel
{
    /**
     * Get summary data for the routes panel.
     *
     * @return string
     */
    public function summary(): string
    {
        $routes = array_filter(Router::routes(), fn($route): bool => !isset($route->defaults['plugin']) || $route->defaults['plugin'] !== 'Crustum/DebugBar');

        return (string)count($routes);
    }

    /**
     * Data collection callback.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event The shutdown event.
     * @return void
     */
    public function shutdown(EventInterface $event): void
    {
        $controller = $event->getSubject();
        if (!$controller instanceof Controller) {
            return;
        }

        $this->data = [
            'matchedRoute' => $controller->getRequest()->getParam('_matchedRoute'),
        ];
    }
}
