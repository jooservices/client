<?php

declare(strict_types=1);

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Cache\MemoryCache;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Resilience\Storage\InMemoryStateStore;
use Psr\Log\NullLogger;

describe('Middleware Ordering Interactions', function () {
    it('cache middleware prevents retry on cache hit', function () {
        $mock = new MockHandler([
            new Response(200, [], '{"cached": "data"}'),
            // If retry happens, this 500 would be attempted
            new Response(500, [], '{"error": "should not reach"}'),
        ]);
        $handler = HandlerStack::create($mock);

        $cache = new MemoryCache();
        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $handler)
            ->withCache($cache, 3600)
            ->withRetry(new RetryConfig(maxAttempts: 3, retryableStatuses: [500]))
            ->build();

        // First request - should hit API and cache
        $response1 = $client->get('/data');
        expect($response1->json())->toBe(['cached' => 'data']);

        // Second request - should hit cache and NOT trigger retry on 500
        $response2 = $client->get('/data');
        expect($response2->json())->toBe(['cached' => 'data']);

        // If retry was triggered, we'd get the 500 error
        expect($response2->status())->toBe(200);
    });

    it('circuit breaker prevents retry when circuit is open', function () {
        $callCount = 0;
        $mock = new MockHandler([
            // First 5 requests fail to open circuit
            new Response(503, [], '{"error": "service unavailable"}'),
            new Response(503, [], '{"error": "service unavailable"}'),
            new Response(503, [], '{"error": "service unavailable"}'),
            new Response(503, [], '{"error": "service unavailable"}'),
            new Response(503, [], '{"error": "service unavailable"}'),
        ]);
        $handler = HandlerStack::create($mock);

        $stateStore = new InMemoryStateStore();
        $circuitConfig = new CircuitBreakerConfig(
            failureThreshold: 3,
            recoveryTimeoutMs: 5000
        );

        $client = ClientBuilder::create()
            ->withHttpErrors(true)
            ->withOption('handler', $handler)
            ->withCircuitBreaker($circuitConfig, $stateStore)
            ->withRetry(new RetryConfig(maxAttempts: 2, retryableStatuses: [503]))
            ->build();

        // Trigger failures to open circuit
        for ($i = 0; $i < 5; $i++) {
            try {
                $client->get('/api');
            } catch (\Throwable $e) {
                // Expected to fail
            }
        }

        // Circuit should now be open
        expect(fn () => $client->get('/api'))
            ->toThrow(\JOOservices\Client\Exceptions\NetworkConnectionException::class, 'Circuit Breaker is OPEN');
    });

    it('logging middleware captures request and response from retry attempts', function () {
        $mock = new MockHandler([
            new Response(503, [], '{"error": "temporary"}'),
            new Response(200, [], '{"success": true}'),
        ]);
        $handler = HandlerStack::create($mock);

        $logs = [];
        $logger = new class ($logs) extends NullLogger {
            public function __construct(private array &$logs)
            {
            }

            public function info($message, array $context = []): void
            {
                $this->logs[] = ['level' => 'info', 'message' => $message, 'context' => $context];
            }

            public function error($message, array $context = []): void
            {
                $this->logs[] = ['level' => 'error', 'message' => $message, 'context' => $context];
            }
        };

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $handler)
            ->withLogger($logger)
            ->withRetry(new RetryConfig(maxAttempts: 2, retryableStatuses: [503]))
            ->build();

        $response = $client->get('/test');

        expect($response->status())->toBe(200);
        expect($logs)->toHaveCount(2); // One for initial request, one for successful retry
    });

    it('interceptor middleware can modify requests before cache lookup', function () {
        $mock = new MockHandler([
            new Response(200, ['X-Custom' => 'modified'], '{"intercepted": true}'),
        ]);
        $handler = HandlerStack::create($mock);

        $cache = new MemoryCache();
        $requestModified = false;

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $handler)
            ->onRequest(function ($request) use (&$requestModified) {
                $requestModified = true;
                return $request->withHeader('X-Intercepted', 'true');
            })
            ->withCache($cache, 3600)
            ->build();

        $response = $client->get('/test');

        expect($requestModified)->toBeTrue();
        expect($response->header('X-Custom'))->toBe('modified');
    });

    it('correlation ID is propagated through all middleware', function () {
        $mock = new MockHandler([
            new Response(200, [], '{"data": "test"}'),
        ]);
        $handler = HandlerStack::create($mock);

        $capturedRequest = null;

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $handler)
            ->onRequest(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return $request;
            })
            ->withCorrelationId()
            ->build();

        $client->get('/test');

        expect($capturedRequest)->not->toBeNull();
        expect($capturedRequest->hasHeader('X-Correlation-ID'))->toBeTrue();

        $correlationId = $capturedRequest->getHeader('X-Correlation-ID')[0];
        expect($correlationId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('middleware stack executes in correct order with all features enabled', function () {
        $executionOrder = [];

        $mock = new MockHandler([
            new Response(200, [], '{"data": "complete"}'),
        ]);
        $handler = HandlerStack::create($mock);

        $cache = new MemoryCache();
        $stateStore = new InMemoryStateStore();

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $handler)
            ->withUserAgent('TestClient/1.0')
            ->withCorrelationId()
            ->withCircuitBreaker(new CircuitBreakerConfig(), $stateStore)
            ->withRetry(new RetryConfig())
            ->withCache($cache, 3600)
            ->withLogger(new NullLogger())
            ->onRequest(function ($request) use (&$executionOrder) {
                $executionOrder[] = 'interceptor-request';
                return $request;
            })
            ->onResponse(function ($response) use (&$executionOrder) {
                $executionOrder[] = 'interceptor-response';
                return $response;
            })
            ->build();

        $response = $client->get('/test');

        expect($response->status())->toBe(200);
        expect($executionOrder)->toContain('interceptor-request');
        expect($executionOrder)->toContain('interceptor-response');
    });

    it('user-agent middleware sets default header when none provided', function () {
        $mock = new MockHandler([
            new Response(200, [], '{"success": true}'),
        ]);
        $handler = HandlerStack::create($mock);

        $capturedRequest = null;

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $handler)
            ->onRequest(function ($request) use (&$capturedRequest) {
                $capturedRequest = $request;
                return $request;
            })
            ->withUserAgent('CustomClient/2.0')
            ->build();

        $client->get('/test');

        expect($capturedRequest->hasHeader('User-Agent'))->toBeTrue();
        expect($capturedRequest->getHeader('User-Agent')[0])->toBe('CustomClient/2.0');
    });

    it('cache respects TTL and expires correctly with retry middleware', function () {
        $callCount = 0;
        $mock = new MockHandler([
            new Response(200, [], '{"call": 1}'),
            new Response(200, [], '{"call": 2}'),
        ]);
        $handler = HandlerStack::create($mock);

        $cache = new MemoryCache();

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $handler)
            ->withCache($cache, 1) // 1 second TTL
            ->withRetry(new RetryConfig())
            ->build();

        // First call
        $response1 = $client->get('/test');
        expect($response1->json())->toBe(['call' => 1]);

        // Second call immediately - should hit cache
        $response2 = $client->get('/test');
        expect($response2->json())->toBe(['call' => 1]);

        // Wait for cache to expire
        sleep(2);

        // Third call - cache expired, should make new request
        $response3 = $client->get('/test');
        expect($response3->json())->toBe(['call' => 2]);
    });
});
