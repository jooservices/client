<?php

declare(strict_types=1);

namespace Tests\Unit\Middleware;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Middleware\RetryMiddleware;
use JOOservices\Client\Resilience\RetryConfig;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('unit')]
class RetryMiddlewareTest extends TestCase
{
    public function test_passes_through_on_success(): void
    {
        $config = new RetryConfig(maxAttempts: 3, baseDelayMs: 10, maxDelayMs: 100, useJitter: false);
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            return new Response(200);
        };

        $response = $middleware($request, [], $next);

        $this->assertSame(1, $callCount);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_retries_on_retryable_status_codes(): void
    {
        $config = new RetryConfig(
            maxAttempts: 3,
            baseDelayMs: 10,
            maxDelayMs: 100,
            useJitter: false,
            retryableStatuses: [503]
        );
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                return new Response(503);
            }
            return new Response(200);
        };

        $response = $middleware($request, [], $next);

        $this->assertSame(3, $callCount);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_returns_last_response_after_max_attempts_on_retryable_status(): void
    {
        $config = new RetryConfig(
            maxAttempts: 2,
            baseDelayMs: 10,
            maxDelayMs: 100,
            useJitter: false,
            retryableStatuses: [503]
        );
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            return new Response(503);
        };

        $response = $middleware($request, [], $next);

        $this->assertSame(2, $callCount);
        $this->assertSame(503, $response->getStatusCode());
    }

    public function test_retries_on_retryable_exceptions(): void
    {
        $config = new RetryConfig(
            maxAttempts: 3,
            baseDelayMs: 10,
            maxDelayMs: 100,
            useJitter: false,
            retryableExceptions: [NetworkConnectionException::class]
        );
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            if ($callCount < 3) {
                throw new NetworkConnectionException('Connection failed');
            }
            return new Response(200);
        };

        $response = $middleware($request, [], $next);

        $this->assertSame(3, $callCount);
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_throws_after_max_attempts_on_exception(): void
    {
        $config = new RetryConfig(
            maxAttempts: 2,
            baseDelayMs: 10,
            maxDelayMs: 100,
            useJitter: false,
            retryableExceptions: [NetworkConnectionException::class]
        );
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            throw new NetworkConnectionException('Connection failed');
        };

        try {
            $middleware($request, [], $next);
            $this->fail('Expected NetworkConnectionException');
        } catch (NetworkConnectionException $e) {
            // expected
        }
        $this->assertSame(2, $callCount);
    }

    public function test_does_not_retry_on_non_retryable_exceptions(): void
    {
        $config = new RetryConfig(
            maxAttempts: 3,
            baseDelayMs: 10,
            maxDelayMs: 100,
            retryableExceptions: [NetworkConnectionException::class]
        );
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            throw new \RuntimeException('Non-retryable');
        };

        try {
            $middleware($request, [], $next);
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertSame(1, $callCount);
        }
    }

    public function test_uses_exponential_backoff_with_jitter(): void
    {
        $config = new RetryConfig(
            maxAttempts: 2,
            baseDelayMs: 50,
            maxDelayMs: 1000,
            useJitter: true,
            retryableStatuses: [503]
        );
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            return new Response(503);
        };

        $start = microtime(true);
        $middleware($request, [], $next);
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertLessThan(200, $elapsed);
    }
}
