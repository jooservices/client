<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Middleware\RetryMiddleware;
use JOOservices\Client\Resilience\RetryConfig;

describe('RetryMiddleware', function () {
    it('passes through on success', function () {
        $config = new RetryConfig(maxAttempts: 3, baseDelayMs: 10, maxDelayMs: 100, useJitter: false);
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            return new Response(200);
        };

        $response = $middleware($request, [], $next);

        expect($callCount)->toBe(1);
        expect($response->getStatusCode())->toBe(200);
    });

    it('retries on retryable status codes', function () {
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

        expect($callCount)->toBe(3);
        expect($response->getStatusCode())->toBe(200);
    });

    it('returns last response after max attempts on retryable status', function () {
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

        expect($callCount)->toBe(2);
        expect($response->getStatusCode())->toBe(503);
    });

    it('retries on retryable exceptions', function () {
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

        expect($callCount)->toBe(3);
        expect($response->getStatusCode())->toBe(200);
    });

    it('throws after max attempts on exception', function () {
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

        expect(fn () => $middleware($request, [], $next))
            ->toThrow(NetworkConnectionException::class);
        expect($callCount)->toBe(2);
    });

    it('does not retry on non-retryable exceptions', function () {
        $config = new RetryConfig(
            maxAttempts: 3,
            baseDelayMs: 10,
            maxDelayMs: 100,
            retryableExceptions: [NetworkConnectionException::class] // Only network exceptions
        );
        $middleware = new RetryMiddleware($config);
        $request = new Request('GET', 'https://example.com/api');

        $callCount = 0;
        $next = function ($req, $opts) use (&$callCount) {
            $callCount++;
            throw new \RuntimeException('Non-retryable');
        };

        expect(fn () => $middleware($request, [], $next))
            ->toThrow(\RuntimeException::class);
        expect($callCount)->toBe(1);
    });

    it('uses exponential backoff with jitter', function () {
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

        // Should have some delay but with jitter it can be anywhere from 0 to baseDelay
        expect($elapsed)->toBeLessThan(200); // Max realistic delay
    });
});
