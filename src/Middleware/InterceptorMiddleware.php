<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class InterceptorMiddleware implements MiddlewareInterface
{
    /**
     * @var array<callable(RequestInterface, array<string, mixed>): RequestInterface>
     */
    private array $requestInterceptors = [];

    /**
     * @var array<callable(ResponseInterface, array<string, mixed>): ResponseInterface>
     */
    private array $responseInterceptors = [];

    public function onRequest(callable $callback): self
    {
        $this->requestInterceptors[] = $callback;
        return $this;
    }

    public function onResponse(callable $callback): self
    {
        $this->responseInterceptors[] = $callback;
        return $this;
    }

    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
    {
        // Run Request Interceptors
        foreach ($this->requestInterceptors as $interceptor) {
            $request = $interceptor($request, $options);
        }

        /** @var ResponseInterface $response */
        $response = $next($request, $options);

        // Run Response Interceptors
        foreach ($this->responseInterceptors as $interceptor) {
            $response = $interceptor($response, $options);
        }

        return $response;
    }
}
