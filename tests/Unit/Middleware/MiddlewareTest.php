<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Middleware;

use JOOservices\Client\Contracts\RequestSignerInterface;
use JOOservices\Client\Contracts\TokenProviderInterface;
use JOOservices\Client\Dto\AuthenticationConfig;
use JOOservices\Client\Dto\CacheConfig;
use JOOservices\Client\Dto\OAuthTokenRefreshConfig;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Dto\ResponseValidationConfig;
use JOOservices\Client\Exceptions\BulkheadRejectedException;
use JOOservices\Client\Exceptions\CircuitOpenException;
use JOOservices\Client\Exceptions\NetworkConnectionException;
use JOOservices\Client\Exceptions\RateLimitExceededException;
use JOOservices\Client\Exceptions\TimeoutException;
use JOOservices\Client\Middleware\ApiVersionMiddleware;
use JOOservices\Client\Middleware\AuthenticationMiddleware;
use JOOservices\Client\Middleware\BulkheadMiddleware;
use JOOservices\Client\Middleware\CacheMiddleware;
use JOOservices\Client\Middleware\CircuitBreakerMiddleware;
use JOOservices\Client\Middleware\CorrelationIdMiddleware;
use JOOservices\Client\Middleware\DeadlineMiddleware;
use JOOservices\Client\Middleware\FallbackMiddleware;
use JOOservices\Client\Middleware\IdempotencyKeyMiddleware;
use JOOservices\Client\Middleware\InterceptorMiddleware;
use JOOservices\Client\Middleware\LoggingMiddleware;
use JOOservices\Client\Middleware\MetricsMiddleware;
use JOOservices\Client\Middleware\OAuthTokenRefreshMiddleware;
use JOOservices\Client\Middleware\ProgressMiddleware;
use JOOservices\Client\Middleware\RateLimitMiddleware;
use JOOservices\Client\Middleware\RequestSigningMiddleware;
use JOOservices\Client\Middleware\RequestCoalescingMiddleware;
use JOOservices\Client\Middleware\ResponseValidationMiddleware;
use JOOservices\Client\Middleware\RetryMiddleware;
use JOOservices\Client\Middleware\TraceContextMiddleware;
use JOOservices\Client\Middleware\UserAgentMiddleware;
use JOOservices\Client\Resilience\BulkheadConfig;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\FallbackConfig;
use JOOservices\Client\Resilience\RateLimitConfig;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Resilience\Storage\InMemoryBulkheadStore;
use JOOservices\Client\Support\HostPartitionKeyResolver;
use JOOservices\Client\Support\NullSleeper;
use JOOservices\Client\Support\InMemoryMetricsRecorder;
use JOOservices\Client\Logging\LogSanitizer;
use JOOservices\Client\Testing\FakeTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class MiddlewareTest extends TestCase
{
    #[Test]
    public function testAuthenticationAddsBearerWithoutOverwritingCallerHeader(): void
    {
        $transport = (new FakeTransport())->push(new Response());
        $request = $this->request();
        (new AuthenticationMiddleware(new AuthenticationConfig('bearer', 'secret')))->process($request, new RequestOptions(), $transport);

        self::assertSame('Bearer secret', $transport->recorded()[0]['request']->getHeaderLine('Authorization'));

        $transport = (new FakeTransport())->push(new Response());
        (new AuthenticationMiddleware(new AuthenticationConfig('bearer', 'secret')))->process($request->withHeader('Authorization', 'caller'), new RequestOptions(), $transport);
        self::assertSame('caller', $transport->recorded()[0]['request']->getHeaderLine('Authorization'));

        $transport = (new FakeTransport())->push(new Response());
        (new AuthenticationMiddleware(new AuthenticationConfig('basic', 'user:pass')))->process($this->request(), new RequestOptions(), $transport);
        self::assertSame('Basic ' . base64_encode('user:pass'), $transport->recorded()[0]['request']->getHeaderLine('Authorization'));

        $transport = (new FakeTransport())->push(new Response());
        (new AuthenticationMiddleware(new AuthenticationConfig('api-key', 'raw-key', 'X-Api-Key')))->process($this->request(), new RequestOptions(), $transport);
        self::assertSame('raw-key', $transport->recorded()[0]['request']->getHeaderLine('X-Api-Key'));
    }

    #[Test]
    public function testAuthenticationConfigRejectsATypoedTypeInsteadOfSilentlySendingUnschemed(): void
    {
        $this->expectException(\JOOservices\Client\Exceptions\InvalidConfigurationException::class);
        new AuthenticationConfig('bearr', 'secret');
    }

    #[Test]
    public function testAuthenticationConfigRejectsAnEmptyValue(): void
    {
        $this->expectException(\JOOservices\Client\Exceptions\InvalidConfigurationException::class);
        new AuthenticationConfig('bearer', '');
    }

    #[Test]
    public function testUserAgentMiddlewareRejectsAnEmptyPool(): void
    {
        $this->expectException(\JOOservices\Client\Exceptions\InvalidConfigurationException::class);
        new UserAgentMiddleware([]);
    }

    #[Test]
    public function testCorrelationIdReplacesAnOversizedIncomingValue(): void
    {
        $transport = (new FakeTransport())->push(new Response());
        $oversized = str_repeat('a', 300);
        (new CorrelationIdMiddleware())->process($this->request()->withHeader('X-Correlation-ID', $oversized), new RequestOptions(), $transport);

        self::assertNotSame($oversized, $transport->recorded()[0]['request']->getHeaderLine('X-Correlation-ID'));
    }

    #[Test]
    public function testCorrelationIdPreservesAReasonableIncomingValue(): void
    {
        $transport = (new FakeTransport())->push(new Response());
        (new CorrelationIdMiddleware())->process($this->request()->withHeader('X-Correlation-ID', 'caller-supplied'), new RequestOptions(), $transport);

        self::assertSame('caller-supplied', $transport->recorded()[0]['request']->getHeaderLine('X-Correlation-ID'));
    }

    #[Test]
    public function testTraceContextReplacesAMalformedIncomingTraceparent(): void
    {
        $transport = (new FakeTransport())->push(new Response());
        (new TraceContextMiddleware())->process($this->request()->withHeader('traceparent', 'not-w3c-shaped'), new RequestOptions(), $transport);

        self::assertMatchesRegularExpression(
            '/^00-[a-f0-9]{32}-[a-f0-9]{16}-01$/',
            $transport->recorded()[0]['request']->getHeaderLine('traceparent'),
        );
    }

    #[Test]
    public function testTraceContextPreservesAWellFormedIncomingTraceparent(): void
    {
        $transport = (new FakeTransport())->push(new Response());
        $valid = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
        (new TraceContextMiddleware())->process($this->request()->withHeader('traceparent', $valid), new RequestOptions(), $transport);

        self::assertSame($valid, $transport->recorded()[0]['request']->getHeaderLine('traceparent'));
    }

    #[Test]
    public function testRetryReturnsFinalSuccessfulResponse(): void
    {
        $transport = (new FakeTransport())->push(new Response(503))->push(new Response(200));
        $response = (new RetryMiddleware(new RetryConfig(maxAttempts: 2), new NullSleeper()))->process($this->request(), new RequestOptions(), $transport);

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(2, $transport->recorded());
    }

    #[Test]
    public function testCacheCachesGetButNeverAuthorizedRequests(): void
    {
        $factory = new Psr17Factory();
        $cache = new CacheMiddleware($factory, $factory, new CacheConfig(60));
        $transport = (new FakeTransport())->push(new Response(200, [], 'cached'));
        $request = $this->request();

        $cache->process($request, new RequestOptions(), $transport);
        $response = $cache->process($request, new RequestOptions(), $transport);

        self::assertSame('cached', (string) $response->getBody());
        self::assertCount(1, $transport->recorded());

        $authorized = (new FakeTransport())->push(new Response(200));
        $cache->process($request->withHeader('Authorization', 'Bearer token'), new RequestOptions(), $authorized);
        self::assertCount(1, $authorized->recorded());
    }

    #[Test]
    public function testCacheDoesNotLeakResponsesBetweenDifferentApiKeyCallers(): void
    {
        $factory = new Psr17Factory();
        $cache = new CacheMiddleware($factory, $factory, new CacheConfig(60));
        $request = $this->request();

        $callerA = (new FakeTransport())->push(new Response(200, [], 'secret-for-caller-a'));
        $responseA = $cache->process($request->withHeader('X-Api-Key', 'caller-a-key'), new RequestOptions(), $callerA);
        self::assertSame('secret-for-caller-a', (string) $responseA->getBody());
        self::assertCount(1, $callerA->recorded());

        $callerB = (new FakeTransport())->push(new Response(200, [], 'secret-for-caller-b'));
        $responseB = $cache->process($request->withHeader('X-Api-Key', 'caller-b-key'), new RequestOptions(), $callerB);
        self::assertSame('secret-for-caller-b', (string) $responseB->getBody());
        self::assertCount(1, $callerB->recorded());

        $callerARepeat = (new FakeTransport())->push(new Response(200, [], 'should-not-be-used'));
        $responseARepeat = $cache->process($request->withHeader('X-Api-Key', 'caller-a-key'), new RequestOptions(), $callerARepeat);
        self::assertSame('secret-for-caller-a', (string) $responseARepeat->getBody());
        self::assertCount(0, $callerARepeat->recorded());
    }

    #[Test]
    public function testCacheKeyCannotBeForgedByInjectingTheDelimiterIntoASingleHeaderValue(): void
    {
        $factory = new Psr17Factory();
        $cache = new CacheMiddleware($factory, $factory, new CacheConfig(60, credentialHeaders: ['X-Api-Key', 'X-Auth-Token']));
        $request = $this->request();

        $victim = (new FakeTransport())->push(new Response(200, [], 'victim-secret'));
        $victimResponse = $cache->process(
            $request->withHeader('X-Api-Key', 'A')->withHeader('X-Auth-Token', 'B'),
            new RequestOptions(),
            $victim,
        );
        self::assertSame('victim-secret', (string) $victimResponse->getBody());

        // An attacker who only controls X-Api-Key (no X-Auth-Token of their own) tries to craft its
        // value so the old '&'-joined-then-hashed key would collide with the victim's two-header combo.
        $attacker = (new FakeTransport())->push(new Response(200, [], 'attacker-should-see-this'));
        $attackerResponse = $cache->process(
            $request->withHeader('X-Api-Key', 'A&x-auth-token=B'),
            new RequestOptions(),
            $attacker,
        );

        self::assertSame('attacker-should-see-this', (string) $attackerResponse->getBody());
        self::assertCount(1, $attacker->recorded());
    }

    #[Test]
    public function testCacheDoesNotShareEntriesAcrossDifferentCookieHeaders(): void
    {
        $factory = new Psr17Factory();
        $cache = new CacheMiddleware($factory, $factory, new CacheConfig(60));
        $request = $this->request();

        $sessionA = (new FakeTransport())->push(new Response(200, [], 'body-for-session-a'));
        $responseA = $cache->process($request->withHeader('Cookie', 'session=aaa'), new RequestOptions(), $sessionA);
        self::assertSame('body-for-session-a', (string) $responseA->getBody());
        self::assertCount(1, $sessionA->recorded());

        $sessionB = (new FakeTransport())->push(new Response(200, [], 'body-for-session-b'));
        $responseB = $cache->process($request->withHeader('Cookie', 'session=bbb'), new RequestOptions(), $sessionB);
        self::assertSame('body-for-session-b', (string) $responseB->getBody());
        self::assertCount(1, $sessionB->recorded());
    }

    #[Test]
    public function testCacheDoesNotReplaySetCookieFromAStoredResponse(): void
    {
        $factory = new Psr17Factory();
        $cache = new CacheMiddleware($factory, $factory, new CacheConfig(60));
        $request = $this->request();

        $origin = (new FakeTransport())->push(new Response(
            200,
            ['Set-Cookie' => 'session=secret; Path=/', 'Set-Cookie2' => 'legacy=1', 'X-Ok' => 'yes'],
            'cached-body',
        ));
        $first = $cache->process($request, new RequestOptions(), $origin);
        self::assertSame(['session=secret; Path=/'], $first->getHeader('Set-Cookie'));
        self::assertSame('cached-body', (string) $first->getBody());

        $cached = $cache->process($request, new RequestOptions(), (new FakeTransport())->push(new Response(200, [], 'should-not-be-used')));
        self::assertSame('cached-body', (string) $cached->getBody());
        self::assertSame([], $cached->getHeader('Set-Cookie'));
        self::assertSame([], $cached->getHeader('Set-Cookie2'));
        self::assertSame(['yes'], $cached->getHeader('X-Ok'));
    }

    #[Test]
    public function testCacheDoesNotStoreARedirectResponse(): void
    {
        $factory = new Psr17Factory();
        $cache = new CacheMiddleware($factory, $factory, new CacheConfig(60));
        $request = $this->request();

        $first = (new FakeTransport())->push(new Response(302, ['Location' => 'https://api.test/new']));
        $cache->process($request, new RequestOptions(), $first);

        $second = (new FakeTransport())->push(new Response(302, ['Location' => 'https://api.test/newer']));
        $cache->process($request, new RequestOptions(), $second);

        // If the first 302 had been cached, the second call would never have reached the transport.
        self::assertCount(1, $second->recorded());
    }

    #[Test]
    public function testCacheRespectsVaryAndDoesNotServeTheWrongVariant(): void
    {
        $factory = new Psr17Factory();
        $cache = new CacheMiddleware($factory, $factory, new CacheConfig(60));
        $request = $this->request();

        $gzipTransport = (new FakeTransport())->push(new Response(200, ['Vary' => 'Accept-Encoding'], 'compressed-body'));
        $gzipResponse = $cache->process($request->withHeader('Accept-Encoding', 'gzip'), new RequestOptions(), $gzipTransport);
        self::assertSame('compressed-body', (string) $gzipResponse->getBody());

        $plainTransport = (new FakeTransport())->push(new Response(200, ['Vary' => 'Accept-Encoding'], 'plain-body'));
        $plainResponse = $cache->process($request->withHeader('Accept-Encoding', 'identity'), new RequestOptions(), $plainTransport);

        // A different Accept-Encoding must be treated as a cache miss, not served the gzip variant.
        self::assertSame('plain-body', (string) $plainResponse->getBody());
        self::assertCount(1, $plainTransport->recorded());
    }

    #[Test]
    public function testCacheHonorsOriginMaxAgeAsACeilingOnTheConfiguredTtl(): void
    {
        $factory = new Psr17Factory();
        $fakeCache = new class {
            public ?int $capturedTtl = null;
            public function get(string $key): mixed
            {
                return null;
            }
            public function set(string $key, mixed $value, int $ttl): bool
            {
                $this->capturedTtl = $ttl;

                return true;
            }
        };
        $cache = new CacheMiddleware($factory, $factory, new CacheConfig(60), $fakeCache);

        $transport = (new FakeTransport())->push(new Response(200, ['Cache-Control' => 'max-age=5'], 'short-lived'));
        $cache->process($this->request(), new RequestOptions(), $transport);

        self::assertSame(5, $fakeCache->capturedTtl);
    }

    #[Test]
    public function testCircuitOpensAfterConfiguredFailures(): void
    {
        $breaker = new CircuitBreakerMiddleware(new CircuitBreakerConfig(failureThreshold: 1));
        $transport = (new FakeTransport())->push(new Response(503));
        $breaker->process($this->request(), new RequestOptions(), $transport);

        $request = $this->request();
        try {
            $breaker->process($request, new RequestOptions(), $transport);
            self::fail('Expected CircuitOpenException.');
        } catch (CircuitOpenException $exception) {
            self::assertSame($request, $exception->getRequest());
        }
    }

    #[Test]
    public function testCircuitBreakerPropagatesResetAfterSecondsAsTheEntryTtl(): void
    {
        $store = new class implements \JOOservices\Client\Resilience\Contracts\StateStoreInterface {
            public ?int $lastTtlSeconds = null;
            public function get(string $key): ?array
            {
                return null;
            }
            public function put(string $key, array $state, ?int $ttlSeconds = null): void
            {
                $this->lastTtlSeconds = $ttlSeconds;
            }
            public function forget(string $key): void
            {
            }
            public function mutate(string $key, callable $mutator, ?int $ttlSeconds = null): array
            {
                $this->lastTtlSeconds = $ttlSeconds;
                $state = $mutator($this->get($key));
                $this->put($key, $state, $ttlSeconds);

                return $state;
            }
        };

        $breaker = new CircuitBreakerMiddleware(new CircuitBreakerConfig(failureThreshold: 1, resetAfterSeconds: 120.0), $store);
        $breaker->process($this->request(), new RequestOptions(), (new FakeTransport())->push(new Response(503)));

        self::assertSame(120, $store->lastTtlSeconds);
    }

    #[Test]
    public function testCircuitBreakerRejectsAConcurrentProbeDuringHalfOpen(): void
    {
        $store = new \JOOservices\Client\Resilience\Storage\InMemoryStateStore();
        $breaker = new CircuitBreakerMiddleware(new CircuitBreakerConfig(failureThreshold: 1, resetAfterSeconds: 30.0), $store);

        // Seed an already-open circuit whose reset window is safely in the past, avoiding any
        // real-clock race against the assertion below.
        $store->put('https://api.example.test:0', ['failures' => 1, 'openedAt' => microtime(true) - 60]);

        $probeInFlightHandler = new class ($breaker) implements \JOOservices\Client\Contracts\RequestHandlerInterface {
            public bool $concurrentProbeThrew = false;
            public function __construct(private readonly CircuitBreakerMiddleware $breaker)
            {
            }
            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                try {
                    $this->breaker->process($request, $options, (new FakeTransport())->push(new Response(200)));
                } catch (CircuitOpenException) {
                    $this->concurrentProbeThrew = true;
                }

                return new Response(200);
            }
        };

        $breaker->process($this->request(), new RequestOptions(), $probeInFlightHandler);

        self::assertTrue($probeInFlightHandler->concurrentProbeThrew);
    }

    #[Test]
    public function testCircuitBreakerReleasesTheProbeSlotWhenTheProbeFailsWithANonNetworkException(): void
    {
        $store = new \JOOservices\Client\Resilience\Storage\InMemoryStateStore();
        $breaker = new CircuitBreakerMiddleware(new CircuitBreakerConfig(failureThreshold: 1, resetAfterSeconds: 30.0), $store);
        $store->put('https://api.example.test:0', ['failures' => 1, 'openedAt' => microtime(true) - 60]);

        $failingProbe = new class implements \JOOservices\Client\Contracts\RequestHandlerInterface {
            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                throw new RateLimitExceededException($request);
            }
        };

        try {
            $breaker->process($this->request(), new RequestOptions(), $failingProbe);
            self::fail('Expected the rate limit exception to propagate.');
        } catch (RateLimitExceededException) {
            // expected — a non-network failure during the probe must still propagate to the caller.
        }

        // The probe slot must have been released: a healthy handler right after must be allowed through,
        // not permanently rejected with CircuitOpenException.
        $healthyTransport = (new FakeTransport())->push(new Response(200));
        $response = $breaker->process($this->request(), new RequestOptions(), $healthyTransport);
        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $healthyTransport->recorded());
    }

    #[Test]
    public function testRateLimitRejectsRequestOverLimit(): void
    {
        $limiter = new RateLimitMiddleware(new RateLimitConfig(maxRequests: 1, perSeconds: 60));
        $transport = (new FakeTransport())->push(new Response());
        $limiter->process($this->request(), new RequestOptions(), $transport);

        try {
            $limiter->process($this->request(), new RequestOptions(), $transport);
            self::fail('Expected the client-side limiter to reject the second request.');
        } catch (RateLimitExceededException $error) {
            self::assertSame('api.example.test', $error->getRequest()->getUri()->getHost());
        }
    }

    #[Test]
    public function testRequestSigningUsesReturnedRequest(): void
    {
        $transport = (new FakeTransport())->push(new Response());
        $signer = new class implements RequestSignerInterface {
            public function sign(RequestInterface $request): RequestInterface
            {
                return $request->withHeader('X-Signed', 'yes');
            }
        };
        (new RequestSigningMiddleware($signer))->process($this->request(), new RequestOptions(), $transport);

        self::assertSame('yes', $transport->recorded()[0]['request']->getHeaderLine('X-Signed'));
    }

    #[Test]
    public function testTraceAndCorrelationHeadersAreAdded(): void
    {
        $transport = (new FakeTransport())->push(new Response());
        (new CorrelationIdMiddleware())->process($this->request(), new RequestOptions(), $transport);
        self::assertNotSame('', $transport->recorded()[0]['request']->getHeaderLine('X-Correlation-ID'));

        $transport = (new FakeTransport())->push(new Response());
        (new TraceContextMiddleware())->process($this->request(), new RequestOptions(), $transport);
        self::assertMatchesRegularExpression('/^00-[a-f0-9]{32}-[a-f0-9]{16}-01$/', $transport->recorded()[0]['request']->getHeaderLine('traceparent'));
    }

    #[Test]
    public function testValidationAndInterceptorCanChangeFlow(): void
    {
        $transport = (new FakeTransport())->push(new Response(200));
        (new ResponseValidationMiddleware(static fn(): bool => true, new ResponseValidationConfig()))->process($this->request(), new RequestOptions(), $transport);

        $transport = (new FakeTransport())->push(new Response(201));
        $interceptor = new InterceptorMiddleware(
            static fn(RequestInterface $request): RequestInterface => $request->withHeader('X-Request', 'changed'),
            static fn(ResponseInterface $response): ResponseInterface => $response->withStatus(202),
        );
        $response = $interceptor->process($this->request(), new RequestOptions(), $transport);

        self::assertSame('changed', $transport->recorded()[0]['request']->getHeaderLine('X-Request'));
        self::assertSame(202, $response->getStatusCode());
    }

    #[Test]
    public function testBulkheadFallbackAndDeadlineHandleTheirFlows(): void
    {
        $bulkhead = new BulkheadMiddleware(new BulkheadConfig(maxConcurrent: 1));
        $response = $bulkhead->process($this->request(), new RequestOptions(), (new FakeTransport())->push(new Response()));
        self::assertSame(200, $response->getStatusCode());

        $fallback = new FallbackMiddleware(static fn(): Response => new Response(200, [], 'fallback'), new FallbackConfig());
        $failed = (new FakeTransport())->push(new NetworkConnectionException($this->request(), 'offline'));
        self::assertSame('fallback', (string) $fallback->process($this->request(), new RequestOptions(), $failed)->getBody());

        $noFallbackOnNetworkFailure = new FallbackMiddleware(static fn(): Response => new Response(200, [], 'fallback'), new FallbackConfig(onNetworkFailure: false));
        $stillFailed = (new FakeTransport())->push(new NetworkConnectionException($this->request(), 'offline'));
        try {
            $noFallbackOnNetworkFailure->process($this->request(), new RequestOptions(), $stillFailed);
            self::fail('Expected the network exception to propagate.');
        } catch (NetworkConnectionException) {
            // Expected: onNetworkFailure is disabled, so the fallback must not run.
        }

        $deadline = new DeadlineMiddleware(1.0);
        self::assertSame(200, $deadline->process($this->request(), new RequestOptions(), (new FakeTransport())->push(new Response()))->getStatusCode());
    }

    #[Test]
    public function testDeadlineRethrowsAnUnrelatedFailureWithinBudgetAndFiresOnASlowSuccess(): void
    {
        $deadline = new DeadlineMiddleware(10.0);
        $failingHandler = new class implements \JOOservices\Client\Contracts\RequestHandlerInterface {
            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                throw new NetworkConnectionException($request, 'boom');
            }
        };
        try {
            $deadline->process($this->request(), new RequestOptions(), $failingHandler);
            self::fail('Expected the original exception to propagate.');
        } catch (NetworkConnectionException) {
            // Expected: elapsed time is well within the 10s budget, so the raw error passes through.
        }

        $slowSuccessHandler = new class implements \JOOservices\Client\Contracts\RequestHandlerInterface {
            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                usleep(30_000);

                return new Response();
            }
        };
        $this->expectException(TimeoutException::class);
        new DeadlineMiddleware(0.01)->process($this->request(), new RequestOptions(), $slowSuccessHandler);
    }

    #[Test]
    public function testBulkheadRejectsAtCapacityAndReleaseDecrementsRatherThanClearing(): void
    {
        $store = new InMemoryBulkheadStore();
        $keys = new HostPartitionKeyResolver();
        $request = $this->request();
        $key = $keys->resolve($request);

        // Occupy both slots of a 2-concurrent limit directly, then release one — the store must
        // decrement rather than fully clear the key, so a third acquire at the same limit still fails.
        self::assertTrue($store->tryAcquire($key, 2));
        self::assertTrue($store->tryAcquire($key, 2));
        self::assertFalse($store->tryAcquire($key, 2));
        $store->release($key);
        self::assertTrue($store->tryAcquire($key, 2));

        $bulkhead = new BulkheadMiddleware(new BulkheadConfig(maxConcurrent: 1), $store, $keys);
        try {
            $bulkhead->process($request, new RequestOptions(), (new FakeTransport())->push(new Response()));
            self::fail('Expected BulkheadRejectedException.');
        } catch (BulkheadRejectedException $exception) {
            self::assertSame($request, $exception->getRequest());
        }
    }

    #[Test]
    public function testDeadlineFiresEvenWhenTheWrappedHandlerThrows(): void
    {
        $deadline = new DeadlineMiddleware(0.01);
        $slowFailingHandler = new class implements \JOOservices\Client\Contracts\RequestHandlerInterface {
            public function handle(RequestInterface $request, RequestOptions $options): ResponseInterface
            {
                usleep(30_000);

                throw new NetworkConnectionException($request, 'boom');
            }
        };

        $this->expectException(TimeoutException::class);
        $deadline->process($this->request(), new RequestOptions(), $slowFailingHandler);
    }

    #[Test]
    public function testRemainingRequestMiddlewareAddsExpectedHeadersAndCallbacks(): void
    {
        $transport = (new FakeTransport())->push(new Response());
        (new UserAgentMiddleware('agent'))->process($this->request(), new RequestOptions(), $transport);
        self::assertSame('agent', $transport->recorded()[0]['request']->getHeaderLine('User-Agent'));

        $pool = ['agent-one', 'agent-two'];
        $rotating = new UserAgentMiddleware($pool);
        $seen = [];
        for ($i = 0; $i < 20; ++$i) {
            $transport = (new FakeTransport())->push(new Response());
            $rotating->process($this->request(), new RequestOptions(), $transport);
            $seen[$transport->recorded()[0]['request']->getHeaderLine('User-Agent')] = true;
        }
        self::assertSame($pool, array_values(array_intersect($pool, array_keys($seen))));

        $transport = (new FakeTransport())->push(new Response());
        (new UserAgentMiddleware('agent'))->process($this->request()->withHeader('User-Agent', 'caller-agent'), new RequestOptions(), $transport);
        self::assertSame('caller-agent', $transport->recorded()[0]['request']->getHeaderLine('User-Agent'));

        $transport = (new FakeTransport())->push(new Response());
        (new ApiVersionMiddleware('v1'))->process($this->request(), new RequestOptions(), $transport);
        self::assertSame('v1', $transport->recorded()[0]['request']->getHeaderLine('X-Api-Version'));

        $transport = (new FakeTransport())->push(new Response());
        (new IdempotencyKeyMiddleware())->process($this->request()->withMethod('POST'), new RequestOptions(), $transport);
        self::assertNotSame('', $transport->recorded()[0]['request']->getHeaderLine('Idempotency-Key'));

        $transport = (new FakeTransport())->push(new Response());
        (new IdempotencyKeyMiddleware())->process($this->request(), new RequestOptions(), $transport);
        self::assertSame('', $transport->recorded()[0]['request']->getHeaderLine('Idempotency-Key'));

        $calls = [];
        $transport = (new FakeTransport())->push(new Response(200, ['Content-Length' => '5']));
        (new ProgressMiddleware(static function (int $current, ?int $total) use (&$calls): void {
            $calls[] = [$current, $total];
        }))->process($this->request(), new RequestOptions(), $transport);
        self::assertSame([[0, 5]], $calls);

        self::assertSame(200, (new RequestCoalescingMiddleware())->process($this->request(), new RequestOptions(), (new FakeTransport())->push(new Response()))->getStatusCode());
    }

    #[Test]
    public function testOAuthRefreshAndMetricsWork(): void
    {
        $provider = new class implements TokenProviderInterface {
            public function token(): string
            {
                return 'old';
            }
            public function refresh(): string
            {
                return 'new';
            }
        };
        $healthyTransport = (new FakeTransport())->push(new Response(200));
        $healthyResponse = new OAuthTokenRefreshMiddleware($provider, new OAuthTokenRefreshConfig())->process($this->request(), new RequestOptions(), $healthyTransport);
        self::assertSame(200, $healthyResponse->getStatusCode());
        self::assertCount(1, $healthyTransport->recorded());

        $transport = (new FakeTransport())->push(new Response(401))->push(new Response(200));
        $refresher = new OAuthTokenRefreshMiddleware($provider, new OAuthTokenRefreshConfig());
        $response = $refresher->process($this->request(), new RequestOptions(), $transport);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Bearer new', $transport->recorded()[1]['request']->getHeaderLine('Authorization'));

        // A second 401 within the cooldown window must not trigger another refresh() call.
        $cooldownTransport = (new FakeTransport())->push(new Response(401));
        $cooldownResponse = $refresher->process($this->request(), new RequestOptions(), $cooldownTransport);
        self::assertSame(401, $cooldownResponse->getStatusCode());
        self::assertCount(1, $cooldownTransport->recorded());

        $metrics = new InMemoryMetricsRecorder();
        (new MetricsMiddleware($metrics))->process($this->request(), new RequestOptions(), (new FakeTransport())->push(new Response(201)));
        self::assertCount(2, $metrics->values());
    }

    #[Test]
    public function testMetricsRecorderEvictsTheOldestObservationOncePerKeyCapIsExceeded(): void
    {
        $metrics = new InMemoryMetricsRecorder(maxPerKey: 1);
        $metrics->observe('latency', 1.0);
        $metrics->observe('latency', 2.0);

        self::assertSame([2.0], $metrics->values()['latency:[]']);
    }

    #[Test]
    public function testInterceptorOnErrorIsCalledAndTheExceptionStillPropagates(): void
    {
        $seen = null;
        $interceptor = new InterceptorMiddleware(onError: function (\Throwable $error) use (&$seen): void {
            $seen = $error;
        });
        $failingTransport = (new FakeTransport())->push(new NetworkConnectionException($this->request(), 'boom'));

        try {
            $interceptor->process($this->request(), new RequestOptions(), $failingTransport);
            self::fail('Expected the network exception to propagate.');
        } catch (NetworkConnectionException $error) {
            self::assertSame($error, $seen);
        }
    }

    #[Test]
    public function testMetricsIncrementsRequestCounterOnTheExceptionPathToo(): void
    {
        $metrics = new InMemoryMetricsRecorder();
        $failingTransport = (new FakeTransport())->push(new NetworkConnectionException($this->request(), 'boom'));

        try {
            (new MetricsMiddleware($metrics))->process($this->request(), new RequestOptions(), $failingTransport);
            self::fail('Expected the network exception to propagate.');
        } catch (NetworkConnectionException) {
            // expected
        }

        $counters = array_filter(array_keys($metrics->values()), static fn(string $key): bool => str_starts_with($key, 'http_client_requests_total:'));
        self::assertCount(1, $counters);
    }

    #[Test]
    public function testLoggingFallbackAndValidationFailureBranches(): void
    {
        $logger = new \Psr\Log\NullLogger();
        self::assertSame(200, (new LoggingMiddleware($logger, new LogSanitizer()))->process($this->request(), new RequestOptions(), (new FakeTransport())->push(new Response()))->getStatusCode());

        $spyLogger = new class extends \Psr\Log\AbstractLogger {
            /** @var list<string> */
            public array $levels = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->levels[] = is_scalar($level) ? (string) $level : gettype($level);
            }
        };
        (new LoggingMiddleware($spyLogger))->process($this->request(), new RequestOptions(verifySsl: false), (new FakeTransport())->push(new Response()));
        self::assertContains('warning', $spyLogger->levels);

        $spyLogger->levels = [];
        (new LoggingMiddleware($spyLogger))->process($this->request(), new RequestOptions(verifySsl: true), (new FakeTransport())->push(new Response()));
        self::assertNotContains('warning', $spyLogger->levels);

        $spyLogger->levels = [];
        $failingTransport = (new FakeTransport())->push(new NetworkConnectionException($this->request(), 'boom'));
        try {
            (new LoggingMiddleware($spyLogger))->process($this->request(), new RequestOptions(), $failingTransport);
            self::fail('Expected the network exception to propagate.');
        } catch (NetworkConnectionException) {
            self::assertContains('error', $spyLogger->levels);
        }

        $fallback = new FallbackMiddleware(static fn(): Response => new Response(299), new FallbackConfig(onNetworkFailure: false, onServerError: true));
        self::assertSame(299, $fallback->process($this->request(), new RequestOptions(), (new FakeTransport())->push(new Response(503)))->getStatusCode());

        $this->expectException(\JOOservices\Client\Exceptions\ResponseValidationException::class);
        (new ResponseValidationMiddleware(static fn(): bool => false))->process($this->request(), new RequestOptions(), (new FakeTransport())->push(new Response()));
    }

    #[Test]
    public function testLoggingSanitizesSecretsInExceptionMessages(): void
    {
        $spyLogger = new class extends \Psr\Log\AbstractLogger {
            /** @var list<array{level: string, context: array<mixed>}> */
            public array $records = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = [
                    'level' => is_scalar($level) ? (string) $level : gettype($level),
                    'context' => $context,
                ];
            }
        };
        $failingTransport = (new FakeTransport())->push(new NetworkConnectionException(
            $this->request(),
            'Failed to connect to https://api.example.test/callback?oauth_token=abc&oauth_signature=xyz',
        ));

        try {
            (new LoggingMiddleware($spyLogger))->process($this->request(), new RequestOptions(), $failingTransport);
            self::fail('Expected the network exception to propagate.');
        } catch (NetworkConnectionException) {
            // expected
        }

        $errorRecords = array_values(array_filter(
            $spyLogger->records,
            static fn(array $record): bool => $record['level'] === 'error',
        ));
        self::assertCount(1, $errorRecords);
        $error = $errorRecords[0]['context']['error'] ?? null;
        self::assertIsString($error);
        self::assertStringNotContainsString('oauth_token=abc', $error);
        self::assertStringNotContainsString('oauth_signature=xyz', $error);
        self::assertStringContainsString('oauth_token=[redacted]', $error);
        self::assertStringContainsString('oauth_signature=[redacted]', $error);
    }

    private function request(): RequestInterface
    {
        return (new Psr17Factory())->createRequest('GET', 'https://api.example.test/users');
    }
}
