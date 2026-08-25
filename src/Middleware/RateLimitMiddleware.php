<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\PartitionKeyResolverInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\RateLimitExceededException;
use JOOservices\Client\Resilience\Contracts\RateLimitStoreInterface;
use JOOservices\Client\Resilience\RateLimitConfig;
use JOOservices\Client\Resilience\Storage\InMemoryRateLimitStore;
use JOOservices\Client\Support\HostPartitionKeyResolver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RateLimitConfig $config,
        private readonly RateLimitStoreInterface $store = new InMemoryRateLimitStore(),
        private readonly PartitionKeyResolverInterface $keys = new HostPartitionKeyResolver(),
    ) {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->store->attempt($this->keys->resolve($request), $this->config->maxRequests, $this->config->perSeconds)) {
            throw new RateLimitExceededException($request);
        }

        return $handler->handle($request, $options);
    }
}
