<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ProgressMiddleware implements MiddlewareInterface
{
    /** @param Closure(int, int|null): void $progress */
    public function __construct(private readonly Closure $progress)
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request, $options);
        $length = $response->getHeaderLine('Content-Length');
        ($this->progress)(0, ctype_digit($length) ? (int) $length : null);

        return $response;
    }
}
