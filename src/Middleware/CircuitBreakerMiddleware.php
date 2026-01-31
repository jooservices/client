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

            // Success! Handle based on circuit state
            if ($this->store->isHalfOpen($this->config->recoveryTimeoutMs)) {
                // In Half-Open state: record success and check if we should close the circuit
                $this->store->reportSuccessInHalfOpen();

                // Check if we have enough successful requests to close the circuit
                if ($this->store->checkHalfOpenRecovery($this->config->successThreshold)) {
                    // Close the circuit - reset all state
                    $this->store->reset();
                }
            } else {
                // In Closed state: reset failure count on success
                $this->store->recordSuccess();
            }

            return $response;
        } catch (Throwable $e) {
            $this->store->recordFailure();
            throw $e;
        }
    }
}
