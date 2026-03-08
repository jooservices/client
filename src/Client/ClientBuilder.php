<?php

declare(strict_types=1);

namespace JOOservices\Client\Client;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use JOOservices\Client\Adapters\Guzzle\GuzzleHttpClientAdapter;
use JOOservices\Client\Contracts\HttpClientInterface;
use JOOservices\Client\Contracts\MiddlewareInterface;
use JOOservices\Client\Contracts\TransportAdapterInterface;
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
use JOOservices\Client\ValueObjects\ClientConfig;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

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

    private ?InterceptorMiddleware $interceptorMiddleware = null;

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

    public function onRequest(callable $callback): self
    {
        $this->getInterceptorMiddleware()->onRequest($callback);
        return $this;
    }

    public function onResponse(callable $callback): self
    {
        $this->getInterceptorMiddleware()->onResponse($callback);
        return $this;
    }

    public function withLogger(LoggerInterface $logger, bool $logBodies = false): self
    {
        // Add LoggingMiddleware. Usually should be OUTER (early in stack) to capture everything?
        // OR Inner (late) to capture network time?
        // Phase 3 specs: "Measure duration; log success...".
        // If we put it outer, we measure total middleware time too.
        // Let's put it as 'logging'.
        return $this->withMiddleware(new LoggingMiddleware($logger, $logBodies), 'logging');
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
        if ($this->interceptorMiddleware === null) {
            $this->interceptorMiddleware = new InterceptorMiddleware();
            $this->getPipeline()->push($this->interceptorMiddleware, 'interceptor');
        }
        return $this->interceptorMiddleware;
    }

    public function build(): HttpClientInterface
    {
        // 1. Create Config
        $config = new ClientConfig(
            baseUri: $this->baseUri,
            timeout: $this->timeout,
            connectTimeout: $this->connectTimeout,
            headers: $this->headers,
            verifySsl: $this->verifySsl,
            httpErrors: $this->httpErrors,
            options: $this->options
        );

        // 2. Create Adapter (Default to Guzzle)
        $adapter = $this->adapter;
        if ($adapter === null) {
            // Phase 2: Middleware Pipeline
            $handlerStack = null;

            // If user passed a handler, we use it as the base/root of the stack.
            // But HandlerStack::create($handler) wraps it.
            // If passed as option 'handler', it might be a HandlerStack OR a callable.

            $userHandler = $this->options['handler'] ?? null;
            unset($this->options['handler']); // consume it so we can set the built one

            if ($userHandler instanceof HandlerStack) {
                $handlerStack = $userHandler;
            } elseif (is_callable($userHandler)) {
                $handlerStack = HandlerStack::create($userHandler);
            } else {
                $handlerStack = HandlerStack::create();
            }

            if ($this->pipeline !== null) {
                // Determine base stack (user provided or default)
                // We pass this stack to pipeline build
                $handlerStack = $this->pipeline->buildHandlerStack($handlerStack);
            }

            $guzzleOptions = $this->options;
            $guzzleOptions['handler'] = $handlerStack;

            // Prevent Guzzle from forcing its default User-Agent so our Middleware can handle it
            $headers = $guzzleOptions['headers'] ?? [];
            if (!is_array($headers)) {
                $headers = [];
            }
            if (!isset($headers['User-Agent'])) {
                $headers['User-Agent'] = '';
            }
            $guzzleOptions['headers'] = $headers;

            $guzzle = new GuzzleClient($guzzleOptions);
            $adapter = new GuzzleHttpClientAdapter($guzzle);
        }

        // 3. Create Client
        return new HttpClient($adapter, $config);
    }
}
