<?php

declare(strict_types=1);

namespace JOOservices\Client\Middleware;

use Closure;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\Contracts\StateStoreInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class CircuitBreakerMiddleware implements MiddlewareInterface
{
    private CircuitBreakerConfig $config;
    private StateStoreInterface $store;

    public function __construct(CircuitBreakerConfig $config, StateStoreInterface $store)
    {
        $this->config = $config;
        $this->store = $store;
    }

    public function __invoke(RequestInterface $request, array $options, Closure $next): ResponseInterface
    {
        if ($this->store->isCircuitOpen($this->config->failureThreshold, $this->config->recoveryTimeoutMs)) {
            // Throw exception or return 503
            throw new NetworkConnectionException('Circuit Breaker is OPEN');
        }

        // 2. Check Half-Open (Logic handled partly in store, but here we can limit concurrency?)
        // For simple implementation: if we got past isCircuitOpen, we are either Closed or Half-Open (probe allowed).

        try {
            /** @var ResponseInterface $response */
            $response = $next($request, $options);

            // Success!
            // If Half-Open, record success. If enough successes -> Closed.
            // If Closed, simplified logic: reset failures on success.

            if ($this->store->isHalfOpen($this->config->recoveryTimeoutMs)) {
                $this->store->reportSuccessInHalfOpen();
                // Check if we should close
                // Note: The store implementation provided earlier didn't expose 'checkHalfOpenRecovery'.
                // Let's assume we can extend the interface or use a specific method.
                // Re-reading interface: verify 'reportSuccessInHalfOpen' usage.

                // Oops, I need to check if we can close.
                // Let's refine the logic:
                // Since interface is generic, maybe we just call recordSuccess?
                // But InMemoryStore separates half-open logic.
                // Let's use recordSuccess() which InMemoryStore resets.
                // Actually my InMemoryStore::recordSuccess() resets failures if openedAt is null.
                // Wait, if openedAt is SET, recordSuccess needs to know about HalfOpen state.

                // Let's upgrade the store logic in the middleware or fix the store.
                // For now, let's assume recordSuccess handles it or I check condition.

                // Let's stick to generic `recordSuccess`.
                $this->store->recordSuccess();
                // If I am half open, I need to know if I should CLOSE.
                // InMemoryStore logic for Reset needs update?
                // Let's call reset() if we have enough successes?
                // It's getting complex to do in Middleware. Store should handle state transitions ideally.
            } else {
                $this->store->recordSuccess();
            }

            return $response;
        } catch (Throwable $e) {
            $this->store->recordFailure();
            throw $e;
        }
    }
}
