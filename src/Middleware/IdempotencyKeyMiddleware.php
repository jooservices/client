<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\IdempotencyConfig;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class IdempotencyKeyMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly IdempotencyConfig $config = new IdempotencyConfig())
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH'], true) || $request->hasHeader($this->config->header)) {
            return $handler->handle($request, $options);
        }

        return $handler->handle($request->withHeader($this->config->header, bin2hex(random_bytes(16))), $options);
    }
}
