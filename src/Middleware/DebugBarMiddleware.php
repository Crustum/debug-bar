<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Middleware;

use Cake\Core\Configure;
use Cake\Event\EventManager;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Crustum\DebugBar\DebugTimer;
use Crustum\DebugBar\Panel\ExceptionsPanel;
use Crustum\DebugBar\ToolbarService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

/**
 * Middleware that enables DebugBar for the layers below.
 *
 * @ported-from \DebugKit\Middleware\DebugKitMiddleware
 */
class DebugBarMiddleware implements MiddlewareInterface
{
    /**
     * Toolbar service handling the request.
     *
     * @var \Crustum\DebugBar\ToolbarService
     */
    protected ToolbarService $service;

    /**
     * Constructor
     *
     * @param \Crustum\DebugBar\ToolbarService $service The configured service, or null.
     */
    public function __construct(?ToolbarService $service = null)
    {
        $service = $service ?: new ToolbarService(EventManager::instance(), (array)Configure::read('DebugBar'));
        $this->service = $service;
    }

    /**
     * Invoke the middleware.
     *
     * DebugBar will augment the response and add the toolbar if possible.
     *
     * @param \Psr\Http\Message\ServerRequestInterface $request The request.
     * @param \Psr\Http\Server\RequestHandlerInterface $handler The request handler.
     * @return \Psr\Http\Message\ResponseInterface A response.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        DebugTimer::clear();

        if ($this->service->isEnabled()) {
            $this->service->loadPanels();
            $this->service->initializePanels();
            $registry = $this->service->registry();
            foreach ($registry->loaded() as $alias) {
                $registry->{$alias}::clear();
            }
        }

        try {
            $response = $handler->handle($request);
        } catch (Throwable $throwable) {
            if ($this->service->isEnabled() && $request instanceof ServerRequest) {
                ExceptionsPanel::record($throwable);
                try {
                    $this->service->saveData($request, new Response(['status' => 500]));
                } catch (Throwable) {
                    // Never mask the original error with storage failures.
                }
            }

            ExceptionsPanel::clear();

            throw $throwable;
        }

        if (!$this->service->isEnabled()) {
            return $response;
        }

        if (!$request instanceof ServerRequest) {
            return $response;
        }

        $row = $this->service->saveData($request, $response);
        if (!$row) {
            return $response;
        }

        return $this->service->injectScripts($row, $response);
    }
}
