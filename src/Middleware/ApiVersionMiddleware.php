<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ApiVersionMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly string $version, private readonly string $header = 'X-Api-Version')
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->hasHeader($this->header) ? $request : $request->withHeader($this->header, $this->version), $options);
    }
}
