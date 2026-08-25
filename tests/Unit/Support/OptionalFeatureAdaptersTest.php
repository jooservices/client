<?php

declare(strict_types=1);

namespace JOOservices\Client\Tests\Unit\Support;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Dto\RequestOptions;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
use JOOservices\Client\Middleware\WanIpMiddleware;
use JOOservices\Client\Resilience\Storage\Psr16BulkheadStore;
use JOOservices\Client\Resilience\Storage\Psr16RateLimitStore;
use JOOservices\Client\Resilience\Storage\Psr16StateStore;
use JOOservices\Client\Support\CachedExternalWanIpProvider;
use JOOservices\Client\Support\NullSleeper;
use JOOservices\Client\Support\PackageVersion;
use JOOservices\Client\Testing\FakeTransport;
use JOOservices\Client\Validation\JsonSchemaBodyValidator;
use JOOservices\Client\Resilience\RetryConfig;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

final class OptionalFeatureAdaptersTest extends TestCase
{
    #[Test]
    public function testPsr16BackedResilienceStoresUseTheProvidedCache(): void
    {
        $cache = new class {
            /** @var array<string, mixed> */
            public array $values = [];

            public function get(string $key): mixed
            {
                return $this->values[$key] ?? null;
            }

            public function set(string $key, mixed $value): bool
            {
                $this->values[$key] = $value;

                return true;
            }

            public function delete(string $key): bool
            {
                unset($this->values[$key]);

                return true;
            }
        };
        $states = new Psr16StateStore($cache);
        $bulkhead = new Psr16BulkheadStore($states);
        $rateLimit = new Psr16RateLimitStore($states);

        self::assertTrue($bulkhead->tryAcquire('orders', 1));
        self::assertFalse($bulkhead->tryAcquire('orders', 1));
        $bulkhead->release('orders');
        self::assertTrue($bulkhead->tryAcquire('orders', 1));
        self::assertTrue($rateLimit->attempt('orders', 1, 60));
        self::assertFalse($rateLimit->attempt('orders', 1, 60));

        $cache->values['not-an-array'] = 'value';
        self::assertNull($states->get('not-an-array'));
        $states->put('temporary', ['ok' => true]);
        $states->forget('temporary');
        self::assertNull($states->get('temporary'));
    }

    #[Test]
    public function testBulkheadAndRateLimitStoresMutateAtomicallyThroughAnyStateStore(): void
    {
        $store = new class implements \JOOservices\Client\Resilience\Contracts\StateStoreInterface {
            /** @var array<string, array<string, mixed>> */
            public array $states = [];

            public int $mutateCalls = 0;

            public function get(string $key): ?array
            {
                return $this->states[$key] ?? null;
            }

            public function put(string $key, array $state, ?int $ttlSeconds = null): void
            {
                unset($ttlSeconds);
                $this->states[$key] = $state;
            }

            public function forget(string $key): void
            {
                unset($this->states[$key]);
            }

            public function mutate(string $key, callable $mutator, ?int $ttlSeconds = null): array
            {
                ++$this->mutateCalls;
                $state = $mutator($this->get($key));
                $this->put($key, $state, $ttlSeconds);

                return $state;
            }
        };

        $bulkhead = new Psr16BulkheadStore($store);
        $rateLimit = new Psr16RateLimitStore($store);

        self::assertTrue($bulkhead->tryAcquire('orders', 1));
        self::assertFalse($bulkhead->tryAcquire('orders', 1));
        $bulkhead->release('orders');
        self::assertTrue($rateLimit->attempt('orders', 1, 60));
        self::assertFalse($rateLimit->attempt('orders', 1, 60));

        self::assertSame(5, $store->mutateCalls);
    }

    #[Test]
    public function testRateLimitStorePropagatesThePeriodAsTheEntryTtlInsteadOfTheStoresFixedDefault(): void
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

        // A daily quota (86400s) must not be capped to a much shorter store-default TTL, or the count
        // silently resets to zero long before the actual window is up.
        new Psr16RateLimitStore($store)->attempt('orders', 1, 86400.0);

        self::assertSame(86400, $store->lastTtlSeconds);
    }

    #[Test]
    public function testCachedWanIpProviderAndMiddlewareOnlyResolveOnce(): void
    {
        $client = new class implements ClientInterface {
            public int $calls = 0;

            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                ++$this->calls;

                return new Response(200, [], '203.0.113.9');
            }
        };
        $factory = new Psr17Factory();
        $provider = new CachedExternalWanIpProvider($client, $factory);
        $transport = (new FakeTransport())->push(new Response(204));
        $http = ClientBuilder::create()
            ->withTransport($transport)
            ->withWanIpProvider($provider)
            ->build();

        $http->sendRequest($factory->createRequest('GET', 'https://api.test/orders'));

        self::assertSame('203.0.113.9', $transport->recorded()[0]['request']->getHeaderLine('X-Client-Wan-IP'));
        self::assertSame(1, $client->calls);
        self::assertSame('203.0.113.9', $provider->address());
    }

    #[Test]
    public function testWanIpProviderRejectsAnInvalidExternalResponse(): void
    {
        $client = new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): \Psr\Http\Message\ResponseInterface
            {
                return new Response(200, [], 'not-an-ip');
            }
        };

        $this->expectException(InvalidConfigurationException::class);
        (new CachedExternalWanIpProvider($client, new Psr17Factory()))->address();
    }

    #[Test]
    public function testWanIpMiddlewarePreservesAnExplicitHeader(): void
    {
        $provider = new class implements \JOOservices\Client\Contracts\WanIpProviderInterface {
            public function address(): string
            {
                return '203.0.113.9';
            }
        };
        $middleware = new WanIpMiddleware($provider);
        $request = (new Psr17Factory())->createRequest('GET', 'https://api.test')->withHeader('X-Client-Wan-IP', '198.51.100.7');
        $handler = new class implements \JOOservices\Client\Contracts\RequestHandlerInterface {
            public ?RequestInterface $handled = null;

            public function handle(RequestInterface $request, RequestOptions $options): \Psr\Http\Message\ResponseInterface
            {
                $this->handled = $request;

                return new Response();
            }
        };

        $middleware->process($request, new RequestOptions(), $handler);

        self::assertSame('198.51.100.7', $handler->handled?->getHeaderLine('X-Client-Wan-IP'));
    }

    #[Test]
    public function testJsonSchemaValidatorExplainsTheOptionalDependency(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        (new JsonSchemaBodyValidator(['type' => 'object']))->validate(new Response(200, [], '{}'));
    }

    #[Test]
    public function testBuilderSupportsIndividualPsr17FactoriesSleeperAndSchemaValidation(): void
    {
        $factory = new Psr17Factory();
        $transport = (new FakeTransport())->push(new Response(200, [], '{}'));
        $client = ClientBuilder::create()
            ->withRequestFactory($factory)
            ->withStreamFactory($factory)
            ->withUriFactory($factory)
            ->withSleeper(new NullSleeper())
            ->withRetry(new RetryConfig())
            ->withJsonSchemaValidation(['type' => 'object'])
            ->withTransport($transport)
            ->build();

        $this->expectException(InvalidConfigurationException::class);
        $client->sendRequest($factory->createRequest('GET', 'https://api.test/schema'));
    }

    #[Test]
    public function testPackageVersionReturnsAPackageOrDevelopmentVersion(): void
    {
        self::assertNotSame('', (new PackageVersion())->value());
    }
}
