<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Contracts\WanIpProviderInterface;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class WanIpMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly WanIpProviderInterface $provider, private readonly string $header = 'X-Client-Wan-IP')
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request->hasHeader($this->header) ? $request : $request->withHeader($this->header, $this->provider->address()), $options);
    }
}
