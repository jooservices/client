<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

final class InterceptorMiddleware implements MiddlewareInterface
{
    /**
     * @param (Closure(RequestInterface): (RequestInterface|null))|null $onRequest
     * @param (Closure(ResponseInterface): (ResponseInterface|null))|null $onResponse
     * @param (Closure(Throwable): void)|null $onError
     */
    public function __construct(private readonly ?Closure $onRequest = null, private readonly ?Closure $onResponse = null, private readonly ?Closure $onError = null)
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $changedRequest = $this->onRequest === null ? null : ($this->onRequest)($request);
        if ($changedRequest instanceof RequestInterface) {
            $request = $changedRequest;
        }
        try {
            $response = $handler->handle($request, $options);

            $changedResponse = $this->onResponse === null ? null : ($this->onResponse)($response);

            return $changedResponse instanceof ResponseInterface ? $changedResponse : $response;
        } catch (Throwable $error) {
            if ($this->onError !== null) {
                ($this->onError)($error);
            }

            throw $error;
        }
    }
}
