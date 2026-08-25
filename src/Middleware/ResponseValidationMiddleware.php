<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Dto\ResponseValidationConfig;
use JOOservices\Client\Exceptions\ResponseValidationException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class ResponseValidationMiddleware implements MiddlewareInterface
{
    /** @param Closure(ResponseInterface): bool $validator */
    public function __construct(private readonly Closure $validator, private readonly ResponseValidationConfig $config = new ResponseValidationConfig())
    {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request, $options);
        if ((! $this->config->onlySuccessful || $response->getStatusCode() < 400) && ! ($this->validator)($response)) {
            throw new ResponseValidationException('Response validation failed.');
        }

        return $response;
    }
}
