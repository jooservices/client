<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Middleware\CircuitBreakerMiddleware;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\Storage\InMemoryStateStore;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class CircuitBreakerMiddlewareTest extends TestCase
{
    public function test_allows_request_when_circuit_is_closed(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 3, recoveryTimeoutMs: 5000);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');
        $next = fn ($req, $opts) => new Response(200);

        $response = $middleware($request, [], $next);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_records_success_and_resets_failures(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 3, recoveryTimeoutMs: 5000);
        $store = new InMemoryStateStore();

        $store->recordFailure();
        $store->recordFailure();
        $this->assertFalse($store->isCircuitOpen(3, 5000));

        $middleware = new CircuitBreakerMiddleware($config, $store);
        $request = new Request('GET', 'https://example.com/api');
        $next = fn ($req, $opts) => new Response(200);

        $middleware($request, [], $next);

        $this->assertFalse($store->isCircuitOpen(3, 5000));
    }

    public function test_records_failure_and_rethrows_exception(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 3, recoveryTimeoutMs: 5000);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');
        $next = fn ($req, $opts) => throw new \RuntimeException('Server error');

        try {
            $middleware($request, [], $next);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            $this->assertFalse($store->isCircuitOpen(3, 5000));
        }
    }

    public function test_opens_circuit_after_reaching_failure_threshold(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 2, recoveryTimeoutMs: 5000);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');
        $next = fn ($req, $opts) => throw new \RuntimeException('Server error');

        try {
            $middleware($request, [], $next);
        } catch (\Throwable) {
        }

        try {
            $middleware($request, [], $next);
        } catch (\Throwable) {
        }

        $this->expectException(NetworkConnectionException::class);
        $this->expectExceptionMessage('Circuit Breaker is OPEN');
        $middleware($request, [], $next);
    }

    public function test_allows_request_in_half_open_state_after_recovery_timeout(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 2, recoveryTimeoutMs: 100);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');

        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }
        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }

        try {
            $middleware($request, [], fn ($req, $opts) => new Response(200));
            $this->fail('Expected NetworkConnectionException');
        } catch (NetworkConnectionException $e) {
            // expected
        }

        usleep(150 * 1000);

        $response = $middleware($request, [], fn ($req, $opts) => new Response(200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_handles_successful_response_in_half_open_state(): void
    {
        $config = new CircuitBreakerConfig(failureThreshold: 2, recoveryTimeoutMs: 100);
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);

        $request = new Request('GET', 'https://example.com/api');

        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }
        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }

        try {
            $middleware($request, [], fn ($req, $opts) => new Response(200));
        } catch (\Throwable) {
        }

        usleep(150 * 1000);

        $response = $middleware($request, [], fn ($req, $opts) => new Response(200));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_half_open_recovery_resets_circuit_after_success_threshold(): void
    {
        $config = new CircuitBreakerConfig(
            failureThreshold: 2,
            recoveryTimeoutMs: 50,
            successThreshold: 2
        );
        $store = new InMemoryStateStore();
        $middleware = new CircuitBreakerMiddleware($config, $store);
        $request = new Request('GET', 'https://example.com/api');

        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }
        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('fail'));
        } catch (\Throwable) {
        }
        try {
            $middleware($request, [], fn ($req, $opts) => throw new \RuntimeException('open'));
        } catch (NetworkConnectionException $e) {
            $this->assertSame('Circuit Breaker is OPEN', $e->getMessage());
        }

        usleep(100 * 1000);

        $middleware($request, [], fn ($req, $opts) => new Response(200));
        $response = $middleware($request, [], fn ($req, $opts) => new Response(200));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($store->isCircuitOpen(2, 50));
    }
}
