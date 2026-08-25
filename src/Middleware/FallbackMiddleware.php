<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Resilience\FallbackConfig;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FallbackMiddleware implements MiddlewareInterface
{
    /** @param Closure(RequestInterface, \Throwable|null): ResponseInterface $fallback */
    public function __construct(private readonly Closure $fallback, private readonly FallbackConfig $config = new FallbackConfig())
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            $response = $handler->handle($request, $options);
        } catch (NetworkConnectionException $error) {
            if ($this->config->onNetworkFailure) {
                return ($this->fallback)($request, $error);
            }

            throw $error;
        }

        return $this->config->onServerError && $response->getStatusCode() >= 500
            ? ($this->fallback)($request, null)
            : $response;
    }
}
