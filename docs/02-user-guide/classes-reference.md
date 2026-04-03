# JOOClient Class Reference

Complete reference for all classes in the JOOClient package.

## Core Classes

### ClientBuilder
**Location**: `src/Client/ClientBuilder.php`
**Type**: Builder Pattern
**Purpose**: Fluent interface for constructing configured `HttpClient` instances.

**Key Methods**:
- `create(): self` - Static factory method.
- `withBaseUri(string $uri): self` - Set base URI.
- `withTimeout(int $seconds): self` - Set request timeout.
- `withHeader(string $name, string $value): self` - Add default header.
- `withDefaultLogging(string $domain, ?string $path = null): self` - Enable Monolog logging.
- `withRetry(RetryConfig $config): self` - Enable retries.
- `withCircuitBreaker(CircuitBreakerConfig $config): self` - Enable circuit breaker.
- `withCache(CacheInterface $cache, int $ttl): self` - Enable caching.
- `withOption(string $key, mixed $value): self` - Set Guzzle option.
- `build(): HttpClientInterface` - Create the client.

**Usage**:
```php
$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->build();
```

---

### HttpClient
**Location**: `src/Client/HttpClient.php`
**Type**: Client Implementation
**Purpose**: Executes HTTP requests.

**Methods**:
- `get(string $uri, array $options = []): ResponseWrapper`
- `post(string $uri, array $options = []): ResponseWrapper`
- `put(string $uri, array $options = []): ResponseWrapper`
- `delete(string $uri, array $options = []): ResponseWrapper`
- `patch(string $uri, array $options = []): ResponseWrapper`
- `request(string $method, string $uri, array $options = []): ResponseWrapper`

All methods return a `ResponseWrapper`.

---

### ClientConfig
**Location**: `src/ValueObjects/ClientConfig.php`
**Type**: Value Object
**Purpose**: Holds immutable configuration for the client.

**Properties**:
- `baseUri`: string
- `timeout`: int
- `connectTimeout`: int
- `headers`: array
- `verifySsl`: bool
- `httpErrors`: bool
- `options`: array

---

## Configuration Classes

### RetryConfig
**Location**: `src/Resilience/RetryConfig.php`
**Type**: Value Object
**Purpose**: Configuration for RetryMiddleware.

**Properties**:
- `maxAttempts`: int
- `delaySeconds`: int
- `minErrorCode`: int

### CircuitBreakerConfig
**Location**: `src/Resilience/CircuitBreakerConfig.php`
**Type**: Value Object
**Purpose**: Configuration for CircuitBreakerMiddleware.

**Properties**:
- `failureThreshold`: int
- `recoveryTimeSeconds`: int

---

## Middleware Classes

All middleware implements `JOOservices\Client\Contracts\MiddlewareInterface`.

- **RetryMiddleware**: Handles exponential backoff retries.
- **CircuitBreakerMiddleware**: Implements circuit breaker pattern.
- **CacheMiddleware**: Handles response caching.
- **LoggingMiddleware**: Logs requests and responses.
- **CorrelationIdMiddleware**: Manages correlation IDs.
- **InterceptorMiddleware**: Allows modification of requests/responses/errors via callbacks.
