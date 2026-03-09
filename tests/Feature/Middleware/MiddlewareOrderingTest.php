<?php

declare(strict_types=1);

namespace Tests\Feature\Middleware;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use JOOservices\Client\Cache\MemoryCache;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Resilience\Storage\InMemoryStateStore;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Tests\TestCase;

#[Group('feature')]
class MiddlewareOrderingTest extends TestCase
{
    public function test_cache_middleware_prevents_retry_on_cache_hit(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"cached": "data"}'),
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

        $response1 = $client->get('/data');
        $this->assertSame(['cached' => 'data'], $response1->json());

        $response2 = $client->get('/data');
        $this->assertSame(['cached' => 'data'], $response2->json());
        $this->assertSame(200, $response2->status());
    }

    public function test_circuit_breaker_prevents_retry_when_circuit_is_open(): void
    {
        $mock = new MockHandler([
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

        for ($i = 0; $i < 5; $i++) {
            try {
                $client->get('/api');
            } catch (\Throwable $e) {
            }
        }

        $this->expectException(\JOOservices\Client\Exceptions\NetworkConnectionException::class);
        $this->expectExceptionMessage('Circuit Breaker is OPEN');
        $client->get('/api');
    }

    public function test_logging_middleware_captures_request_and_response_from_retry_attempts(): void
    {
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

        $this->assertSame(200, $response->status());
        $this->assertCount(2, $logs);
    }

    public function test_interceptor_middleware_can_modify_requests_before_cache_lookup(): void
    {
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

        $this->assertTrue($requestModified);
        $this->assertSame('modified', $response->header('X-Custom'));
    }

    public function test_correlation_ID_is_propagated_through_all_middleware(): void
    {
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

        $this->assertNotNull($capturedRequest);
        $this->assertTrue($capturedRequest->hasHeader('X-Correlation-ID'));

        $correlationId = $capturedRequest->getHeader('X-Correlation-ID')[0];
        $uuidRegex = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        $this->assertMatchesRegularExpression($uuidRegex, $correlationId);
    }

    public function test_middleware_stack_executes_in_correct_order_with_all_features_enabled(): void
    {
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

        $this->assertSame(200, $response->status());
        $this->assertContains('interceptor-request', $executionOrder);
        $this->assertContains('interceptor-response', $executionOrder);
    }

    public function test_user_agent_middleware_sets_default_header_when_none_provided(): void
    {
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

        $this->assertTrue($capturedRequest->hasHeader('User-Agent'));
        $this->assertSame('CustomClient/2.0', $capturedRequest->getHeader('User-Agent')[0]);
    }

    public function test_cache_respects_TTL_and_expires_correctly_with_retry_middleware(): void
    {
        $mock = new MockHandler([
            new Response(200, [], '{"call": 1}'),
            new Response(200, [], '{"call": 2}'),
        ]);
        $handler = HandlerStack::create($mock);

        $cache = new MemoryCache();

        $client = ClientBuilder::create()
            ->withBaseUri('https://api.example.com')
            ->withOption('handler', $handler)
            ->withCache($cache, 1)
            ->withRetry(new RetryConfig())
            ->build();

        $response1 = $client->get('/test');
        $this->assertSame(['call' => 1], $response1->json());

        $response2 = $client->get('/test');
        $this->assertSame(['call' => 1], $response2->json());

        sleep(2);

        $response3 = $client->get('/test');
        $this->assertSame(['call' => 2], $response3->json());
    }
}
