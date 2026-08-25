<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Sync PHP cannot overlap requests in one process; this middleware reserves an extension point
 * and intentionally delegates. Async coalescing belongs to a future async transport.
 */
final class RequestCoalescingMiddleware implements MiddlewareInterface
{
    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request, $options);
    }
}
