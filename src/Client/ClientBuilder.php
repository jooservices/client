<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\MetricsRecorderInterface;
use JOOservices\Client\Contracts\RequestSignerInterface;
use JOOservices\Client\Contracts\SleeperInterface;
use JOOservices\Client\Contracts\TokenProviderInterface;
use JOOservices\Client\Contracts\TransportInterface;
use JOOservices\Client\Contracts\WanIpProviderInterface;
use JOOservices\Client\Dto\AuthenticationConfig;
use JOOservices\Client\Dto\CacheConfig;
use JOOservices\Client\Dto\IdempotencyConfig;
use JOOservices\Client\Dto\OAuthTokenRefreshConfig;
use JOOservices\Client\Dto\ResponseValidationConfig;
use JOOservices\Client\Dto\TraceContextConfig;
use JOOservices\Client\Exceptions\InvalidConfigurationException;
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
use JOOservices\Client\Middleware\RequestCoalescingMiddleware;
use JOOservices\Client\Middleware\RequestSigningMiddleware;
use JOOservices\Client\Middleware\ResponseValidationMiddleware;
use JOOservices\Client\Middleware\RetryMiddleware;
use JOOservices\Client\Middleware\TraceContextMiddleware;
use JOOservices\Client\Middleware\UserAgentMiddleware;
use JOOservices\Client\Middleware\WanIpMiddleware;
use JOOservices\Client\Resilience\BulkheadConfig;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\FallbackConfig;
use JOOservices\Client\Resilience\RateLimitConfig;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Support\BaseUri;
use JOOservices\Client\Support\HeaderValidator;
use JOOservices\Client\Support\Psr17Bundle;
use JOOservices\Client\Support\UsleepSleeper;
use JOOservices\Client\Testing\FakeHttpClient;
use JOOservices\Client\Testing\HttpFakeRegistry;
use JOOservices\Client\Testing\RecordedRequest;
use JOOservices\Client\Testing\TestResponseSequence;
use JOOservices\Client\Testing\AssertionException;
use JOOservices\Client\Validation\JsonSchemaBodyValidator;
use JOOservices\Client\Transport\PsrTransport;
use JOOservices\Client\Transport\CurlTransport;
use JOOservices\Client\Transport\FailoverTransport;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

final class ClientBuilder
{
    private string $baseUri = '';

    private float $timeout = 30.0;

    private float $connectTimeout = 10.0;

    /** @var array<string, string|list<string>> */
    private array $headers = [];

    private bool $verifySsl = true;

    /** @var bool|array<string, mixed> */
    private bool|array $allowRedirects = true;

    /** @var string|array<string, mixed>|null */
    private string|array|null $proxy = null;

    /** @var array<string, bool> */
    private array $explicitCapabilities = [];

    private ?TransportInterface $transport = null;

    private ?Psr17Bundle $psr17 = null;

    private ?SleeperInterface $sleeper = null;

    /** @var array<string, MiddlewareInterface> */
    private array $middlewares = [];

    /** @var list<string>|null */
    private ?array $middlewareOrder = null;

    /** @var (\Closure(\Psr\Http\Message\RequestInterface): \Psr\Http\Message\RequestInterface)|null */
    private ?\Closure $onRequestCallback = null;

    /** @var (\Closure(\Psr\Http\Message\ResponseInterface): \Psr\Http\Message\ResponseInterface)|null */
    private ?\Closure $onResponseCallback = null;

    /** @var (\Closure(\Throwable): void)|null */
    private ?\Closure $onErrorCallback = null;

    private static ?HttpFakeRegistry $fakes = null;

    private static ?TestResponseSequence $pushedResponses = null;

    public function __construct(
        private readonly HeaderValidator $headerValidator = new HeaderValidator(),
        private readonly BaseUri $baseUris = new BaseUri(),
    ) {
    }
    public static function create(): self
    {
        return new self();
    }
    public function withBaseUri(string $uri): self
    {
        $copy = clone $this;
        $copy->baseUri = $this->baseUris->normalize($uri);

        return $copy;
    }
    public function withTimeout(float $seconds): self
    {
        $this->assertTimeout($seconds);
        $copy = clone $this;
        $copy->timeout = $seconds;
        $copy->explicitCapabilities['timeout'] = true;
        return $copy;
    }
    public function withConnectTimeout(float $seconds): self
    {
        $this->assertTimeout($seconds);
        $copy = clone $this;
        $copy->connectTimeout = $seconds;
        $copy->explicitCapabilities['connectTimeout'] = true;
        return $copy;
    }
    public function withVerifySsl(bool $verify): self
    {
        $copy = clone $this;
        $copy->verifySsl = $verify;
        $copy->explicitCapabilities['verifySsl'] = true;
        return $copy;
    }
    /** @param bool|array<string, mixed> $allow */
    public function withRedirects(bool|array $allow): self
    {
        $copy = clone $this;
        $copy->allowRedirects = $allow;
        $copy->explicitCapabilities['allowRedirects'] = true;
        return $copy;
    }
    /** @param string|array<string, string> $proxy */
    public function withProxy(string|array $proxy): self
    {
        $copy = clone $this;
        $copy->proxy = $proxy;
        $copy->explicitCapabilities['proxy'] = true;
        return $copy;
    }
    public function withHeader(string $name, string $value): self
    {
        $this->headerValidator->assertPair($name, $value);
        $copy = clone $this;
        $copy->headers[$name] = $value;

        return $copy;
    }

    /** @param array<string, string|list<string>> $headers */
    public function withHeaders(array $headers): self
    {
        $copy = clone $this;
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $copy->headerValidator->assertPair($name, $item);
                    $existing = $copy->headers[$name] ?? [];
                    $copy->headers[$name] = is_array($existing) ? [...$existing, $item] : [$existing, $item];
                }

                continue;
            }

            $copy->headerValidator->assertPair($name, $value);
            $copy->headers[$name] = $value;
        }

        return $copy;
    }

    public function withPsr17(Psr17Bundle $psr17): self
    {
        $copy = clone $this;
        $copy->psr17 = $psr17;

        return $copy;
    }
    public function withRequestFactory(\Psr\Http\Message\RequestFactoryInterface $factory): self
    {
        $base = $this->psr17 ?? $this->defaultPsr17();

        return $this->withPsr17(new Psr17Bundle($factory, $base->streams, $base->uris, $base->responses));
    }
    public function withStreamFactory(\Psr\Http\Message\StreamFactoryInterface $factory): self
    {
        $base = $this->psr17 ?? $this->defaultPsr17();

        return $this->withPsr17(new Psr17Bundle($base->requests, $factory, $base->uris, $base->responses));
    }
    public function withUriFactory(\Psr\Http\Message\UriFactoryInterface $factory): self
    {
        $base = $this->psr17 ?? $this->defaultPsr17();

        return $this->withPsr17(new Psr17Bundle($base->requests, $base->streams, $factory, $base->responses));
    }
    public function withSleeper(SleeperInterface $sleeper): self
    {
        $copy = clone $this;
        $copy->sleeper = $sleeper;

        return $copy;
    }
    public function withWanIpProvider(WanIpProviderInterface $provider): self
    {
        return $this->withMiddleware(new WanIpMiddleware($provider), 'wan-ip');
    }
    public function withPsr18(ClientInterface $client): self
    {
        return $this->withTransport(new PsrTransport($client));
    }
    public function withTransport(TransportInterface $transport): self
    {
        $copy = clone $this;
        $copy->transport = $transport;
        return $copy;
    }

    /** @param list<TransportInterface> $transports */
    public function withFailoverTransport(array $transports): self
    {
        if (count($transports) === 0) {
            throw new InvalidConfigurationException('A failover transport requires at least one transport.');
        }

        return $this->withTransport(new FailoverTransport($transports));
    }

    public function withCurlTransport(): self
    {
        $factory = new Psr17Factory();

        return $this->withTransport(new CurlTransport($factory, $factory, $factory));
    }
    public function withMiddleware(MiddlewareInterface $middleware, string $name): self
    {
        $copy = clone $this;
        $copy->middlewares[$name] = $middleware;
        return $copy;
    }

    public function withRetry(RetryConfig $config, ?SleeperInterface $sleeper = null): self
    {
        return $this->withMiddleware(new RetryMiddleware($config, $sleeper ?? $this->sleeper ?? new UsleepSleeper()), 'retry');
    }

    public function withCircuitBreaker(CircuitBreakerConfig $config): self
    {
        return $this->withMiddleware(new CircuitBreakerMiddleware($config), 'circuit-breaker');
    }

    public function withRateLimit(RateLimitConfig $config): self
    {
        return $this->withMiddleware(new RateLimitMiddleware($config), 'rate-limit');
    }

    public function withBulkhead(BulkheadConfig $config): self
    {
        return $this->withMiddleware(new BulkheadMiddleware($config), 'bulkhead');
    }

    /** @param \Closure(\Psr\Http\Message\RequestInterface, \Throwable|null): \Psr\Http\Message\ResponseInterface $fallback */
    public function withFallback(\Closure $fallback, FallbackConfig $config = new FallbackConfig()): self
    {
        return $this->withMiddleware(new FallbackMiddleware($fallback, $config), 'fallback');
    }

    public function withDeadline(float $seconds): self
    {
        $this->assertTimeout($seconds);

        return $this->withMiddleware(new DeadlineMiddleware($seconds), 'deadline');
    }

    public function withCorrelationId(string $header = 'X-Correlation-ID'): self
    {
        return $this->withMiddleware(new CorrelationIdMiddleware($header), 'correlation-id');
    }

    public function withTraceContext(TraceContextConfig $config = new TraceContextConfig()): self
    {
        return $this->withMiddleware(new TraceContextMiddleware($config), 'trace-context');
    }

    public function withMetrics(MetricsRecorderInterface $metrics): self
    {
        return $this->withMiddleware(new MetricsMiddleware($metrics), 'metrics');
    }

    public function withLogger(LoggerInterface $logger): self
    {
        return $this->withMiddleware(new LoggingMiddleware($logger), 'logging');
    }

    public function withCache(CacheConfig $config = new CacheConfig(), ?object $cache = null): self
    {
        $factory = new Psr17Factory();

        return $this->withMiddleware(new CacheMiddleware($factory, $factory, $config, $cache), 'cache');
    }

    public function withBearerToken(string $token): self
    {
        return $this->withMiddleware(new AuthenticationMiddleware(new AuthenticationConfig('bearer', $token)), 'authentication');
    }

    public function withApiKey(string $key, string $header = 'X-Api-Key'): self
    {
        return $this->withMiddleware(new AuthenticationMiddleware(new AuthenticationConfig('api-key', $key, $header, '')), 'authentication');
    }

    public function withBasicAuth(string $username, string $password): self
    {
        return $this->withMiddleware(new AuthenticationMiddleware(new AuthenticationConfig('basic', $username . ':' . $password)), 'authentication');
    }

    public function withOAuthTokenRefresh(TokenProviderInterface $provider, OAuthTokenRefreshConfig $config = new OAuthTokenRefreshConfig()): self
    {
        return $this->withMiddleware(new OAuthTokenRefreshMiddleware($provider, $config), 'oauth-token-refresh');
    }

    public function withRequestSigning(RequestSignerInterface $signer): self
    {
        return $this->withMiddleware(new RequestSigningMiddleware($signer), 'request-signing');
    }

    public function withUserAgent(string $userAgent): self
    {
        return $this->withMiddleware(new UserAgentMiddleware($userAgent), 'user-agent');
    }

    public function withGeneratedUserAgent(string $userAgent = 'jooservices/client'): self
    {
        return $this->withUserAgent($userAgent . ' PHP/' . PHP_VERSION);
    }

    public function withApiVersion(string $version, string $header = 'X-Api-Version'): self
    {
        return $this->withMiddleware(new ApiVersionMiddleware($version, $header), 'api-version');
    }

    public function withIdempotencyKey(IdempotencyConfig $config = new IdempotencyConfig()): self
    {
        return $this->withMiddleware(new IdempotencyKeyMiddleware($config), 'idempotency-key');
    }

    /** @param \Closure(int, int|null): void $progress */
    public function withProgress(\Closure $progress): self
    {
        return $this->withMiddleware(new ProgressMiddleware($progress), 'progress');
    }

    /** @param \Closure(\Psr\Http\Message\ResponseInterface): bool $validator */
    public function withResponseValidation(\Closure $validator, ResponseValidationConfig $config = new ResponseValidationConfig()): self
    {
        return $this->withMiddleware(new ResponseValidationMiddleware($validator, $config), 'response-validation');
    }
    /** @param array<string, mixed> $schema */
    public function withJsonSchemaValidation(array $schema, ResponseValidationConfig $config = new ResponseValidationConfig()): self
    {
        $validator = new JsonSchemaBodyValidator($schema);

        return $this->withResponseValidation($validator->validate(...), $config);
    }

    /**
     * Registers an extension point for request deduplication — it does NOT itself deduplicate
     * concurrent identical requests. This library is synchronous, so one process can't have two
     * requests genuinely in flight to overlap; real coalescing needs an async transport. Provided so a
     * consumer can insertMiddlewareBefore/After('request-coalescing', ...) their own implementation
     * (e.g. process-level memoization) without needing a custom order preset.
     */
    public function withRequestCoalescing(): self
    {
        return $this->withMiddleware(new RequestCoalescingMiddleware(), 'request-coalescing');
    }

    /** @param \Closure(\Psr\Http\Message\RequestInterface): \Psr\Http\Message\RequestInterface $onRequest */
    public function onRequest(\Closure $onRequest): self
    {
        $copy = clone $this;
        $copy->onRequestCallback = $onRequest;

        return $copy;
    }

    /** @param \Closure(\Psr\Http\Message\ResponseInterface): \Psr\Http\Message\ResponseInterface $onResponse */
    public function onResponse(\Closure $onResponse): self
    {
        $copy = clone $this;
        $copy->onResponseCallback = $onResponse;

        return $copy;
    }

    /** @param \Closure(\Throwable): void $onError */
    public function onError(\Closure $onError): self
    {
        $copy = clone $this;
        $copy->onErrorCallback = $onError;

        return $copy;
    }

    /** @param list<string> $userAgents */
    /** @param list<string> $userAgents Picked at random on every request, not once at build time. */
    public function withRotatingUserAgent(array $userAgents): self
    {
        if (count($userAgents) === 0) {
            throw new InvalidConfigurationException('At least one user agent is required.');
        }

        return $this->withMiddleware(new UserAgentMiddleware($userAgents), 'user-agent');
    }

    /**
     * Canonical outermost-to-innermost position for every middleware name this builder can register.
     * Both order presets share it: correctness requires every registrable name to have a defined
     * rank (an absent name silently collapses to the innermost slot), while which subset is actually
     * present depends only on which with*() calls were made — uksort only touches present keys.
     *
     * @var list<string>
     */
    private const CANONICAL_MIDDLEWARE_ORDER = [
        'correlation-id',
        'trace-context',
        'user-agent',
        'api-version',
        'wan-ip',
        'metrics',
        'deadline',
        'fallback',
        'circuit-breaker',
        'rate-limit',
        'bulkhead',
        'request-coalescing',
        'interceptor',
        'oauth-token-refresh',
        'authentication',
        'request-signing',
        'idempotency-key',
        'retry',
        'cache',
        'response-validation',
        'progress',
        'logging',
    ];

    public function withStandardMiddlewareOrder(): self
    {
        return $this->ordered(self::CANONICAL_MIDDLEWARE_ORDER);
    }

    public function withProductionMiddlewareOrder(): self
    {
        return $this->ordered(self::CANONICAL_MIDDLEWARE_ORDER);
    }
    public function insertMiddlewareBefore(string $before, MiddlewareInterface $middleware, string $name): self
    {
        return $this->insert($before, $middleware, $name, false);
    }
    public function insertMiddlewareAfter(string $after, MiddlewareInterface $middleware, string $name): self
    {
        return $this->insert($after, $middleware, $name, true);
    }

    public function build(): HttpClient
    {
        $middlewares = $this->middlewares;
        if ($this->onRequestCallback !== null || $this->onResponseCallback !== null || $this->onErrorCallback !== null) {
            $middlewares['interceptor'] = new InterceptorMiddleware($this->onRequestCallback, $this->onResponseCallback, $this->onErrorCallback);
        }
        $order = $this->middlewareOrder ?? self::CANONICAL_MIDDLEWARE_ORDER;
        $unranked = array_diff(array_keys($middlewares), $order);
        if ($unranked !== []) {
            throw new InvalidConfigurationException(sprintf(
                'Middleware "%s" has no defined position in the configured middleware order; use insertMiddlewareBefore()/insertMiddlewareAfter() to place it.',
                implode('", "', $unranked),
            ));
        }
        $middlewares = self::applyOrder($middlewares, $order);

        $connection = new ClientConnection(
            $this->baseUri,
            $this->timeout,
            $this->connectTimeout,
            $this->headers,
            $this->verifySsl,
            $this->allowRedirects,
            $this->proxy,
        );
        $wiring = new ClientWiring(
            $this->explicitCapabilities,
            $this->transport ?? (self::$fakes === null ? null : new PsrTransport(new FakeHttpClient(self::$fakes))),
            $this->psr17,
            $middlewares,
        );
        $compiler = new ClientCompiler();

        return $compiler->compile($connection, $wiring);
    }

    private function assertTimeout(float $seconds): void
    {
        if ($seconds <= 0) {
            throw new InvalidConfigurationException('Timeout values must be greater than zero.');
        }
    }

    private function insert(string $anchor, MiddlewareInterface $middleware, string $name, bool $after): self
    {
        if (! array_key_exists($anchor, $this->middlewares)) {
            throw new InvalidConfigurationException(sprintf('Middleware "%s" does not exist.', $anchor));
        }
        $copy = clone $this;
        $result = [];
        foreach ($copy->middlewares as $key => $value) {
            if (! $after && $key === $anchor) {
                $result[$name] = $middleware;
            } $result[$key] = $value;
            if ($after && $key === $anchor) {
                $result[$name] = $middleware;
            }
        }
        $copy->middlewares = $result;
        $copy->middlewareOrder = self::spliceOrder(
            $copy->middlewareOrder ?? self::CANONICAL_MIDDLEWARE_ORDER,
            $anchor,
            $name,
            $after,
        );

        return $copy;
    }

    /**
     * @param list<string> $order
     * @return list<string>
     */
    private static function spliceOrder(array $order, string $anchor, string $name, bool $after): array
    {
        $position = array_search($anchor, $order, true);
        if ($position === false) {
            return [...$order, $name];
        }

        array_splice($order, $after ? $position + 1 : $position, 0, [$name]);

        return $order;
    }

    /** @param list<string> $order */
    private function ordered(array $order): self
    {
        $copy = clone $this;
        $copy->middlewareOrder = $order;
        $copy->middlewares = self::applyOrder($copy->middlewares, $order);

        return $copy;
    }

    /**
     * @param array<string, MiddlewareInterface> $middlewares
     * @param list<string> $order
     * @return array<string, MiddlewareInterface>
     */
    private static function applyOrder(array $middlewares, array $order): array
    {
        $rank = array_flip($order);
        uksort($middlewares, static fn(string $left, string $right): int => ($rank[$left] ?? PHP_INT_MAX) <=> ($rank[$right] ?? PHP_INT_MAX));

        return $middlewares;
    }

    public static function fake(?HttpFakeRegistry $registry = null): HttpFakeRegistry
    {
        self::$pushedResponses = null;

        return self::$fakes = $registry ?? new HttpFakeRegistry();
    }
    public static function clearFake(): void
    {
        self::$fakes?->clear();
        self::$fakes = null;
        self::$pushedResponses = null;
    }
    public static function isFaked(): bool
    {
        return self::$fakes !== null;
    }
    public static function respond(string $method, string $pattern, TestResponseSequence $responses): HttpFakeRegistry
    {
        return (self::$fakes ?? self::fake())->respond($method, $pattern, $responses);
    }
    public static function push(ResponseInterface|\Throwable $response): HttpFakeRegistry
    {
        $registry = self::$fakes ?? self::fake();
        if (self::$pushedResponses === null) {
            self::$pushedResponses = new TestResponseSequence();
            $registry->respond('*', '*', self::$pushedResponses);
        }
        self::$pushedResponses->push($response);

        return $registry;
    }
    /** @return list<RecordedRequest> */
    public static function recorded(): array
    {
        return self::$fakes?->recorded() ?? [];
    }
    public static function lastRequest(): ?RecordedRequest
    {
        $recorded = self::recorded();

        return $recorded === [] ? null : $recorded[array_key_last($recorded)];
    }
    /** @param \Closure(RecordedRequest): bool $predicate */
    public static function assertSent(\Closure $predicate): void
    {
        foreach (self::recorded() as $record) {
            if ($predicate($record)) {
                return;
            }
        }

        throw new AssertionException('No recorded HTTP request matched the assertion.');
    }

    private function defaultPsr17(): Psr17Bundle
    {
        $factory = new Psr17Factory();

        return new Psr17Bundle($factory, $factory, $factory, $factory);
    }
}
