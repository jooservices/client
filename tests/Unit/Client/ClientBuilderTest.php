<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Client;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Contracts\RequestSignerInterface;
use JOOservices\Client\Contracts\TokenProviderInterface;
use JOOservices\Client\Dto\OAuthTokenRefreshConfig;
use JOOservices\Client\Resilience\BulkheadConfig;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\RateLimitConfig;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Support\InMemoryMetricsRecorder;
use JOOservices\Client\Support\NullSleeper;
use JOOservices\Client\Testing\FakeTransport;
use JOOservices\Client\Transport\CurlTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Psr\Http\Message\RequestInterface;

final class ClientBuilderTest extends TestCase
{
    #[Test]
    public function testCreateBuildUsesCurlTransportByDefault(): void
    {
        $client = ClientBuilder::create()->build();
        $transport = new ReflectionClass($client)->getProperty('transport')->getValue($client);

        self::assertInstanceOf(CurlTransport::class, $transport);
        self::assertSame('GET', $client->requestBuilder()->get('https://example.test')->toPsr()->getMethod());
    }

    #[Test]
    public function testRejectsCrlfInDefaultHeaders(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        ClientBuilder::create()->withHeader('X-Test', "ok\r\nInjected: 1");
    }

    #[Test]
    public function testRejectsHeaderNameWithColon(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        ClientBuilder::create()->withHeader('X:Name', 'value');
    }

    #[Test]
    public function testDoesNotExposeHttpErrorsOrGenericOptionHelpers(): void
    {
        $methods = array_map(
            static fn(\ReflectionMethod $method): string => $method->getName(),
            new ReflectionClass(ClientBuilder::class)->getMethods(),
        );
        self::assertNotContains('withHttpErrors', $methods);
        self::assertNotContains('withOption', $methods);
    }

    #[Test]
    public function testFakeTransportCanBeInjected(): void
    {
        $client = ClientBuilder::create()->withTransport(new FakeTransport())->build();
        self::assertSame('GET', $client->requestBuilder()->get('https://example.test')->toPsr()->getMethod());
    }

    #[Test]
    public function testPreservesMultipleSetCookieHeaderValues(): void
    {
        $transport = (new FakeTransport())->push(new \Nyholm\Psr7\Response());
        $client = ClientBuilder::create()
            ->withHeaders(['Set-Cookie' => ['a=1; Path=/', 'b=2; Path=/']])
            ->withTransport($transport)
            ->build();
        $client->sendRequest($client->requestBuilder()->get('https://example.test')->toPsr());

        self::assertSame(['a=1; Path=/', 'b=2; Path=/'], $transport->recorded()[0]['request']->getHeader('Set-Cookie'));
    }

    #[Test]
    public function testExposesTheCompleteFluentFeatureSurface(): void
    {
        $transport = new FakeTransport();
        $tokens = new class implements TokenProviderInterface {
            public function token(): string
            {
                return 'old';
            }
            public function refresh(): string
            {
                return 'new';
            }
        };
        $signer = new class implements RequestSignerInterface {
            public function sign(RequestInterface $request): RequestInterface
            {
                return $request;
            }
        };

        $builder = ClientBuilder::create()
            ->withTransport($transport)
            ->withRetry(new RetryConfig(), new NullSleeper())
            ->withCircuitBreaker(new CircuitBreakerConfig())
            ->withRateLimit(new RateLimitConfig())
            ->withBulkhead(new BulkheadConfig())
            ->withFallback(static fn(RequestInterface $request): \Psr\Http\Message\ResponseInterface => new \Nyholm\Psr7\Response())
            ->withDeadline(1)
            ->withCorrelationId()
            ->withTraceContext()
            ->withMetrics(new InMemoryMetricsRecorder())
            ->withLogger(new \Psr\Log\NullLogger())
            ->withCache()
            ->withBearerToken('token')
            ->withApiKey('key')
            ->withBasicAuth('user', 'password')
            ->withOAuthTokenRefresh($tokens, new OAuthTokenRefreshConfig())
            ->withRequestSigning($signer)
            ->withUserAgent('test-agent')
            ->withGeneratedUserAgent()
            ->withRotatingUserAgent(['one', 'two'])
            ->withApiVersion('2026-08')
            ->withIdempotencyKey()
            ->withProgress(static function (): void {
            })
            ->withResponseValidation(static fn(): bool => true)
            ->withRequestCoalescing()
            ->onRequest(static fn(RequestInterface $request): RequestInterface => $request)
            ->onResponse(static fn(\Psr\Http\Message\ResponseInterface $response): \Psr\Http\Message\ResponseInterface => $response)
            ->onError(static function (): void {
            })
            ->withStandardMiddlewareOrder()
            ->withProductionMiddlewareOrder();

        self::assertSame('GET', $builder->build()->requestBuilder()->get('https://api.test')->toPsr()->getMethod());
    }

    #[Test]
    public function testStandardOrderRunsInterceptorOnRequestBeforeSigning(): void
    {
        $signer = new class implements RequestSignerInterface {
            public ?RequestInterface $seenBySigner = null;
            public function sign(RequestInterface $request): RequestInterface
            {
                $this->seenBySigner = $request;

                return $request;
            }
        };
        $transport = (new FakeTransport())->push(new \Nyholm\Psr7\Response());

        $client = ClientBuilder::create()
            ->withTransport($transport)
            ->withRequestSigning($signer)
            ->onRequest(static fn(RequestInterface $request): RequestInterface => $request->withHeader('X-Intercepted', 'yes'))
            ->withStandardMiddlewareOrder()
            ->build();

        $client->sendRequest($client->requestBuilder()->get('https://api.test')->toPsr());

        self::assertInstanceOf(RequestInterface::class, $signer->seenBySigner);
        self::assertSame(['yes'], $signer->seenBySigner->getHeader('X-Intercepted'));
        self::assertSame(['yes'], $transport->recorded()[0]['request']->getHeader('X-Intercepted'));
    }

    #[Test]
    public function testStandardOrderKeepsFallbackOutsideRetrySoRetryStillRecovers(): void
    {
        $request = new \Nyholm\Psr7\Request('GET', 'https://api.test');
        $transport = (new FakeTransport())
            ->push(new \JOOservices\Client\Exceptions\NetworkConnectionException($request, 'boom'))
            ->push(new \Nyholm\Psr7\Response(200, [], 'recovered'));

        $client = ClientBuilder::create()
            ->withTransport($transport)
            ->withSleeper(new NullSleeper())
            ->withRetry(new RetryConfig(maxAttempts: 3), new NullSleeper())
            ->withFallback(static fn(RequestInterface $req): \Psr\Http\Message\ResponseInterface => new \Nyholm\Psr7\Response(200, [], 'fallback-used'))
            ->withStandardMiddlewareOrder()
            ->build();

        $response = $client->sendRequest($client->requestBuilder()->get('https://api.test')->toPsr());

        self::assertSame('recovered', (string) $response->getBody());
        self::assertCount(2, $transport->recorded());
    }

    #[Test]
    public function testCanonicalOrderAppliesWithoutAnOrderPresetSoFallbackStaysOutsideRetry(): void
    {
        $request = new \Nyholm\Psr7\Request('GET', 'https://api.test');
        $transport = (new FakeTransport())
            ->push(new \JOOservices\Client\Exceptions\NetworkConnectionException($request, 'boom'))
            ->push(new \Nyholm\Psr7\Response(200, [], 'recovered'));

        $client = ClientBuilder::create()
            ->withTransport($transport)
            ->withSleeper(new NullSleeper())
            ->withRetry(new RetryConfig(maxAttempts: 3), new NullSleeper())
            ->withFallback(static fn(RequestInterface $req): \Psr\Http\Message\ResponseInterface => new \Nyholm\Psr7\Response(200, [], 'fallback-used'))
            ->build();

        $response = $client->sendRequest($client->requestBuilder()->get('https://api.test')->toPsr());

        self::assertSame('recovered', (string) $response->getBody());
        self::assertCount(2, $transport->recorded());
    }

    #[Test]
    public function testBuildRejectsUnrankedCustomMiddleware(): void
    {
        $marker = new class implements \JOOservices\Client\Contracts\MiddlewareInterface {
            public function process(RequestInterface $request, \JOOservices\Client\Dto\RequestOptions $options, \JOOservices\Client\Contracts\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
            {
                return $handler->handle($request, $options);
            }
        };

        $this->expectException(InvalidConfigurationException::class);
        ClientBuilder::create()
            ->withTransport(new FakeTransport())
            ->withMiddleware($marker, 'my-custom')
            ->build();
    }

    #[Test]
    public function testInsertMiddlewareBeforeAnchorIsHonoredAfterAPresetOrderIsApplied(): void
    {
        $marker = new class implements \JOOservices\Client\Contracts\MiddlewareInterface {
            public int $calls = 0;
            public function process(RequestInterface $request, \JOOservices\Client\Dto\RequestOptions $options, \JOOservices\Client\Contracts\RequestHandlerInterface $handler): \Psr\Http\Message\ResponseInterface
            {
                ++$this->calls;

                return $handler->handle($request, $options);
            }
        };
        $transport = (new FakeTransport())->push(new \Nyholm\Psr7\Response(200, [], 'first'));

        $client = ClientBuilder::create()
            ->withTransport($transport)
            ->withCache()
            ->withStandardMiddlewareOrder()
            ->insertMiddlewareBefore('cache', $marker, 'my-marker')
            ->build();

        // "before cache" must place the marker outside the cache layer, so it still runs on a
        // cache HIT (the second identical request), not just on the cache-populating miss.
        $client->sendRequest($client->requestBuilder()->get('https://api.test')->toPsr());
        $client->sendRequest($client->requestBuilder()->get('https://api.test')->toPsr());

        self::assertSame(2, $marker->calls);
        self::assertCount(1, $transport->recorded());
    }

    #[Test]
    public function testSupportsFailoverCurlAndCustomPsr17Factories(): void
    {
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $bundle = new \JOOservices\Client\Support\Psr17Bundle($factory, $factory, $factory, $factory);
        $client = ClientBuilder::create()
            ->withPsr17($bundle)
            ->withFailoverTransport([new FakeTransport()])
            ->build();
        self::assertSame('GET', $client->requestBuilder()->get('https://api.test')->toPsr()->getMethod());
        self::assertSame('GET', ClientBuilder::create()->withCurlTransport()->build()->requestBuilder()->get('https://api.test')->toPsr()->getMethod());
    }

    #[Test]
    public function testGlobalFakesCanQueueResponsesAndRecordRequests(): void
    {
        ClientBuilder::clearFake();
        try {
            ClientBuilder::push(new \Nyholm\Psr7\Response(202));
            $client = ClientBuilder::create()->build();
            $request = $client->requestBuilder()->get('https://api.test/queued')->toPsr();

            self::assertSame(202, $client->sendRequest($request)->getStatusCode());
            self::assertTrue(ClientBuilder::isFaked());
            self::assertSame($request, ClientBuilder::lastRequest()?->request);
            ClientBuilder::assertSent(static fn(\JOOservices\Client\Testing\RecordedRequest $record): bool => $record->request->getUri()->getPath() === '/queued');
        } finally {
            ClientBuilder::clearFake();
        }

        self::assertFalse(ClientBuilder::isFaked());
    }
}
