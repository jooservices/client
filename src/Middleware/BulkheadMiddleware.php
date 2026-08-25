<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\PartitionKeyResolverInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\BulkheadRejectedException;
use JOOservices\Client\Resilience\BulkheadConfig;
use JOOservices\Client\Resilience\Contracts\BulkheadStoreInterface;
use JOOservices\Client\Resilience\Storage\InMemoryBulkheadStore;
use JOOservices\Client\Support\HostPartitionKeyResolver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class BulkheadMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly BulkheadConfig $config,
        private readonly BulkheadStoreInterface $store = new InMemoryBulkheadStore(),
        private readonly PartitionKeyResolverInterface $keys = new HostPartitionKeyResolver(),
    ) {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->keys->resolve($request);
        if (! $this->store->tryAcquire($key, $this->config->maxConcurrent)) {
            throw new BulkheadRejectedException($request);
        }

        try {
            return $handler->handle($request, $options);
        } finally {
            $this->store->release($key);
        }
    }
}
