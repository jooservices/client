<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Middleware\CircuitBreakerMiddleware;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\Storage\InMemoryStateStore;

describe('CircuitBreakerMiddleware', function () {
    it('allows request when circuit is closed', function () {
        $config = new CircuitBreakerConfig(failureThreshold: 3, recoveryTimeoutMs: 5000);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');
        $next = fn ($req, $opts) => new Response(200);

        $response = $middleware($request, [], $next);

        expect($response->getStatusCode())->toBe(200);
    });

    it('records success and resets failures', function () {
        $config = new CircuitBreakerConfig(failureThreshold: 3, recoveryTimeoutMs: 5000);
        $store = new InMemoryStateStore();

        // Record some failures first
        $store->recordFailure();
        $store->recordFailure();
        // Circuit should not be open yet (threshold is 3)
        expect($store->isCircuitOpen(3, 5000))->toBeFalse();

        $middleware = new CircuitBreakerMiddleware($config, $store);
        $request = new Request('GET', 'https://example.com/api');
        $next = fn ($req, $opts) => new Response(200);

        $middleware($request, [], $next);

        // After success, circuit should still be closed
        expect($store->isCircuitOpen(3, 5000))->toBeFalse();
    });

    it('records failure and rethrows exception', function () {
        $config = new CircuitBreakerConfig(failureThreshold: 3, recoveryTimeoutMs: 5000);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');
        $next = fn ($req, $opts) => throw new \RuntimeException('Server error');

        expect(fn () => $middleware($request, [], $next))
            ->toThrow(\RuntimeException::class);

        // After one failure, circuit should not be open yet
        expect($store->isCircuitOpen(3, 5000))->toBeFalse();
    });

    it('opens circuit after reaching failure threshold', function () {
        $config = new CircuitBreakerConfig(failureThreshold: 2, recoveryTimeoutMs: 5000);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');
        $next = fn ($req, $opts) => throw new \RuntimeException('Server error');

        // First failure
        try {
            $middleware($request, [], $next);
        } catch (\Throwable) {
        }

        // Second failure - opens circuit
        try {
            $middleware($request, [], $next);
        } catch (\Throwable) {
        }

        // Third call - circuit should be open
        expect(fn () => $middleware($request, [], $next))
            ->toThrow(NetworkConnectionException::class, 'Circuit Breaker is OPEN');
    });

    it('allows request in half-open state after recovery timeout', function () {
        $config = new CircuitBreakerConfig(failureThreshold: 2, recoveryTimeoutMs: 100);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');

        // Get 2 failures to reach threshold
        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }
        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }

        // Verify circuit is open
        expect(fn () => $middleware($request, [], fn ($req, $opts) => new Response(200)))
            ->toThrow(NetworkConnectionException::class);

        // Wait for recovery timeout
        usleep(150 * 1000); // 150ms

        // Should allow request in half-open state
        $response = $middleware($request, [], fn ($req, $opts) => new Response(200));

        expect($response->getStatusCode())->toBe(200);
    });

    it('handles successful response in half-open state', function () {
        $config = new CircuitBreakerConfig(failureThreshold: 2, recoveryTimeoutMs: 100);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');

        // Get failures to reach threshold and open circuit
        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }
        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }

        // Trigger circuit open detection
        try {
            $middleware($request, [], fn ($req, $opts) => new Response(200));
        } catch (\Throwable) {
        }

        // Wait for half-open
        usleep(150 * 1000);

        // Success in half-open should return response (and call recordSuccess internally)
        $response = $middleware($request, [], fn ($req, $opts) => new Response(200));

        // Verify the request succeeded
        expect($response->getStatusCode())->toBe(200);
    });
});
