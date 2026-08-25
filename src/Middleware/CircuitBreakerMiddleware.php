<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\PartitionKeyResolverInterface;
use JOOservices\Client\Contracts\RequestHandlerInterface;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\CircuitOpenException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\Contracts\StateStoreInterface;
use JOOservices\Client\Resilience\Storage\InMemoryStateStore;
use JOOservices\Client\Support\HostPartitionKeyResolver;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class CircuitBreakerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CircuitBreakerConfig $config,
        private readonly StateStoreInterface $store = new InMemoryStateStore(),
        private readonly PartitionKeyResolverInterface $keys = new HostPartitionKeyResolver(),
    ) {
    }

    public function process(RequestInterface $request, RequestOptions $options, RequestHandlerInterface $handler): ResponseInterface
    {
        $key = $this->keys->resolve($request);
        $rejected = false;
        $probing = false;
        $this->store->mutate($key, function (?array $state) use (&$rejected, &$probing): array {
            $state ??= ['failures' => 0, 'openedAt' => null];
            $openedAt = $state['openedAt'] ?? null;
            if (! is_float($openedAt)) {
                return $state;
            }

            if (microtime(true) - $openedAt < $this->config->resetAfterSeconds) {
                $rejected = true;

                return $state;
            }

            if (($state['probing'] ?? false) === true) {
                $rejected = true;

                return $state;
            }

            $probing = true;

            return [...$state, 'probing' => true];
        }, $this->stateTtlSeconds());

        if ($rejected) {
            throw new CircuitOpenException($request);
        }

        try {
            $response = $handler->handle($request, $options);
            $failed = in_array($response->getStatusCode(), $this->config->failureStatuses, true);
        } catch (NetworkConnectionException $error) {
            $this->recordFailure($key);
            throw $error;
        } catch (\Throwable $error) {
            // Any other failure during a half-open probe (rate limit, bulkhead, validation, ...) is not
            // itself evidence the backend is unhealthy, but it must still release the probe slot so a
            // future request can retry it instead of the circuit staying rejected forever.
            if ($probing) {
                $this->clearProbing($key);
            }

            throw $error;
        }

        if ($failed) {
            $this->recordFailure($key);

            return $response;
        }

        $this->store->forget($key);

        return $response;
    }

    private function recordFailure(string $key): void
    {
        $this->store->mutate($key, function (?array $state): array {
            $recorded = $state['failures'] ?? 0;
            $failures = (is_int($recorded) ? $recorded : 0) + 1;

            return ['failures' => $failures, 'openedAt' => $failures >= $this->config->failureThreshold ? microtime(true) : null];
        }, $this->stateTtlSeconds());
    }

    private function clearProbing(string $key): void
    {
        $this->store->mutate($key, static function (?array $state): array {
            $state ??= ['failures' => 0, 'openedAt' => null];
            unset($state['probing']);

            return $state;
        }, $this->stateTtlSeconds());
    }

    /**
     * A store with its own fixed default TTL (e.g. Psr16StateStore) could otherwise expire — and
     * silently reset — an open circuit's state before resetAfterSeconds is actually up, letting failed
     * requests straight through again early.
     */
    private function stateTtlSeconds(): int
    {
        return (int) ceil($this->config->resetAfterSeconds);
    }
}
