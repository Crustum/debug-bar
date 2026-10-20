<?php
declare(strict_types=1);

namespace TestApp\Authorization;

use Cake\Http\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Captures the request a middleware passes down the queue.
 */
class TestHandler implements RequestHandlerInterface
{
    /**
     * Last seen request.
     *
     * @var \Psr\Http\Message\ServerRequestInterface|null
     */
    public ?ServerRequestInterface $seen = null;

    /**
     * @inheritDoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->seen = $request;

        return new Response();
    }
}
