<?php
declare(strict_types=1);

namespace Crustum\DebugBar\Authorization;

use Authorization\AuthorizationServiceInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Wraps the authorization service so the panel sees every check.
 *
 * Place after the authorization middleware: when the request already
 * carries an authorization service it gets decorated, otherwise the
 * request passes through untouched. Double decoration never happens.
 */
class DebugBarAuthorizationMiddleware implements MiddlewareInterface
{
    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $service = $request->getAttribute('authorization');
        if (
            $service instanceof AuthorizationServiceInterface
            && !$service instanceof DebugBarAuthorizationServiceDecorator
        ) {
            $request = $request->withAttribute(
                'authorization',
                new DebugBarAuthorizationServiceDecorator($service),
            );
        }

        return $handler->handle($request);
    }
}
