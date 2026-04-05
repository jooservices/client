<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use JOOservices\Client\Adapters\Guzzle\GuzzleHttpClientAdapter;
use JOOservices\Client\Contracts\AsyncHttpClientInterface;
use JOOservices\Client\Contracts\HttpClientInterface;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\TransportAdapterInterface;
use JOOservices\Client\Contracts\WanIpProviderInterface;
use JOOservices\Client\Logging\MonologFactory;
use JOOservices\Client\Middleware\CacheMiddleware;
use JOOservices\Client\Middleware\CircuitBreakerMiddleware;
use JOOservices\Client\Middleware\CorrelationIdMiddleware;
use JOOservices\Client\Middleware\InterceptorMiddleware;
use JOOservices\Client\Middleware\LoggingMiddleware;
use JOOservices\Client\Middleware\MiddlewarePipeline;
use JOOservices\Client\Middleware\RetryMiddleware;
use JOOservices\Client\Middleware\UserAgentMiddleware;
use JOOservices\Client\Resilience\CircuitBreakerConfig;
use JOOservices\Client\Resilience\Contracts\StateStoreInterface;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Resilience\Storage\InMemoryStateStore;
use JOOservices\Client\Support\CachedExternalWanIpProvider;
use JOOservices\Client\ValueObjects\ClientConfig;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/** @SuppressWarnings("PHPMD.ExcessivePublicCount") */
final class ClientBuilder
{
    private string $baseUri = '';

    private int $timeout = 30;

    private int $connectTimeout = 10;

    /** @var array<string, mixed> */
    private array $headers = [];

    private bool $verifySsl = true;

    private bool $httpErrors = false;

    /** @var array<string, mixed> */
    private array $options = [];

    private ?TransportAdapterInterface $adapter = null;

    private ?MiddlewarePipeline $pipeline = null;

    private ?InterceptorMiddleware $interceptor = null;

    private ?WanIpProviderInterface $wanIpProvider = null;

    public static function create(): self
    {
        return new self();
    }

    public function withBaseUri(string $uri): self
    {
        $this->baseUri = $uri;

        return $this;
    }

    public function withTimeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    public function withConnectTimeout(int $seconds): self
    {
        $this->connectTimeout = $seconds;

        return $this;
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    public function withHeaders(array $headers): self
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    public function withVerifySsl(bool $verify): self
    {
        $this->verifySsl = $verify;

        return $this;
    }

    public function withHttpErrors(bool $enable): self
    {
        $this->httpErrors = $enable;

        return $this;
    }

    public function withOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Override the default adapter (Dependency Injection).
     */
    public function withAdapter(TransportAdapterInterface $adapter): self
    {
        $this->adapter = $adapter;

        return $this;
    }

    public function withMiddleware(MiddlewareInterface $middleware, string $name): self
    {
        $this->getPipeline()->push($middleware, $name);
        return $this;
    }

    public function withCorrelationId(string $headerName = CorrelationIdMiddleware::HEADER_NAME): self
    {
        // Add default correlation middleware
        // We use options to pass config if needed, but for now middleware is simple.
        // If we want to configure the header name, we can pass it via options OR constructor.
        // Our CorrelationIdMiddleware reads from options.
        $this->withOption('correlation_header', $headerName);
        return $this->withMiddleware(new CorrelationIdMiddleware(), 'correlation_id');
    }

    public function withUserAgent(string $userAgent): self
    {
        return $this->withMiddleware(new UserAgentMiddleware($userAgent), 'user_agent');
    }

    /**
     * @param callable(RequestInterface, array<string, mixed>): RequestInterface $callback
     */
    public function onRequest(callable $callback): self
    {
        $this->getInterceptorMiddleware()->onRequest($callback);
        return $this;
    }

    /**
     * @param callable(ResponseInterface, array<string, mixed>): ResponseInterface $callback
     */
    public function onResponse(callable $callback): self
    {
        $this->getInterceptorMiddleware()->onResponse($callback);
        return $this;
    }

    public function withLogger(
        LoggerInterface $logger,
        bool $logBodies = false
    ): self {
        $provider = $this->wanIpProvider;
        if ($provider === null) {
            $provider = new CachedExternalWanIpProvider();
        }
        $this->wanIpProvider = $provider;

        // Add LoggingMiddleware. Usually should be OUTER (early in stack) to capture everything?
        // OR Inner (late) to capture network time?
        // Phase 3 specs: "Measure duration; log success...".
        // If we put it outer, we measure total middleware time too.
        // Let's put it as 'logging'.
        return $this->withMiddleware(new LoggingMiddleware($logger, $logBodies, $provider), 'logging');
    }

    public function withDefaultLogging(string $domain, ?string $path = null): self
    {
        $logger = MonologFactory::createDaily($domain, $path);
        return $this->withLogger($logger);
    }

    public function withCache(CacheInterface $cache, int $defaultTtl = 3600): self
    {
        return $this->withMiddleware(new CacheMiddleware($cache, $defaultTtl), 'cache');
    }

    public function withRetry(RetryConfig $config): self
    {
        // Note: Middleware order matters. The last added wraps previous ones.
        // Typical order: Cache (outer) -> Retry (inner) -> Network
        // Call withRetry() before withCache() for proper behavior.
        return $this->withMiddleware(new RetryMiddleware($config), 'retry');
    }

    public function withCircuitBreaker(CircuitBreakerConfig $config, ?StateStoreInterface $store = null): self
    {
        // Circuit breaker wraps other middleware. Recommended order: CB -> Cache -> Retry -> Network
        $store = $store ?? new InMemoryStateStore();
        return $this->withMiddleware(new CircuitBreakerMiddleware($config, $store), 'circuit_breaker');
    }

    private function getPipeline(): MiddlewarePipeline
    {
        if ($this->pipeline === null) {
            $this->pipeline = new MiddlewarePipeline();
        }
        return $this->pipeline;
    }

    private function getInterceptorMiddleware(): InterceptorMiddleware
    {
        if ($this->interceptor === null) {
            $this->interceptor = new InterceptorMiddleware();
            $this->getPipeline()->push($this->interceptor, 'interceptor');
        }

        return $this->interceptor;
    }

    public function build(): HttpClientInterface&AsyncHttpClientInterface
    {
        $config = new ClientConfig(
            baseUri: $this->baseUri,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            headers: $this->headers,
            verifySsl: $this->verifySsl,
            httpErrors: $this->httpErrors,
            options: $this->options
        );

        $adapter = $this->adapter ?? $this->createDefaultAdapter();

        return new HttpClient($adapter, $config);
    }

    private function createDefaultAdapter(): TransportAdapterInterface
    {
        $guzzleOptions = $this->options;
        $guzzleOptions['handler'] = $this->createHandlerStack($guzzleOptions);
        $guzzleOptions['headers'] = $this->normalizeHeaders($guzzleOptions['headers'] ?? []);

        $guzzle = new GuzzleClient($guzzleOptions);

        return new GuzzleHttpClientAdapter($guzzle);
    }

    /**
     * @param array<string, mixed> $guzzleOptions
     */
    private function createHandlerStack(array &$guzzleOptions): HandlerStack
    {
        $userHandler = $guzzleOptions['handler'] ?? null;
        unset($guzzleOptions['handler']);

        if ($userHandler instanceof HandlerStack) {
            $handlerStack = $userHandler;
        } elseif (is_callable($userHandler)) {
            $handlerStack = HandlerStack::create($userHandler);
        } else {
            $handlerStack = HandlerStack::create();
        }

        if ($this->pipeline !== null) {
            return $this->pipeline->buildHandlerStack($handlerStack);
        }

        return $handlerStack;
    }

    /**
     * @param mixed $headers
     * @return array<string, mixed>
     */
    private function normalizeHeaders(mixed $headers): array
    {
        if (!is_array($headers)) {
            $headers = [];
        }

        $normalized = [];
        foreach ($headers as $name => $value) {
            $normalized[(string) $name] = $value;
        }

        if (!isset($normalized['User-Agent'])) {
            $normalized['User-Agent'] = '';
        }

        return $normalized;
    }
}
