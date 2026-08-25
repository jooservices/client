<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MiddlewarePipeline implements RequestHandlerInterface
{
    /** @var RequestHandlerInterface */
    private RequestHandlerInterface $compiled;

    /** @param list<MiddlewareInterface> $middlewares */
    public function __construct(array $middlewares, RequestHandlerInterface $terminal)
    {
        $this->compiled = array_reduce(
            array_reverse($middlewares),
            static fn(RequestHandlerInterface $next, MiddlewareInterface $middleware): RequestHandlerInterface => new class ($middleware, $next) implements RequestHandlerInterface {
                public function __construct(private readonly MiddlewareInterface $middleware, private readonly RequestHandlerInterface $next)
                {
                }
                public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
                {
                    return $this->middleware->process($request, $options, $this->next);
                }
            },
            $terminal,
        );
    }

    public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
    {
        return $this->compiled->handle($request, $options);
    }
}
