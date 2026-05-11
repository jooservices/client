# Feature Inventory

## Purpose

Comprehensive inventory of all features implemented in the JOOservices HTTP Client library.

## Audience

Product managers, developers, QA engineers, and users evaluating the library.

---

## Core HTTP Client Features

### F001: HTTP Request Methods

**Description**: Standard HTTP methods (GET, POST, PUT, PATCH, DELETE)

**Audience**: Library users

**Evidence**: `HttpClientInterface` in `src/Contracts/HttpClientInterface.php`

**Usage**:
```php
$client->get($uri, $options);
$client->post($uri, $options);
$client->put($uri, $options);
$client->patch($uri, $options);
$client->delete($uri, $options);
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Client/HttpClientTest.php`

**Confidence**: Confirmed

---

### F002: Fluent Client Builder

**Description**: Builder pattern API for constructing configured HTTP clients

**Audience**: Library users

**Evidence**: `ClientBuilder` class in `src/Client/ClientBuilder.php`

**API Methods**:
- `create()`: Factory method
- `withBaseUri(string)`: Set base URL
- `withTimeout(int)`: Request timeout
- `withConnectTimeout(int)`: Connection timeout
- `withHeader(string, string)`: Add header
- `withHeaders(array)`: Bulk headers
- `withVerifySsl(bool)`: SSL verification
- `withHttpErrors(bool)`: Error throwing behavior
- `withOption(string, mixed)`: Custom Guzzle option
- `build()`: Create client instance

**Usage**:
```php
$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->withTimeout(5)
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Client/ClientBuilderTest.php`

**Confidence**: Confirmed

---

### F003: Async HTTP Requests

**Description**: Non-blocking HTTP requests returning promises

**Audience**: Advanced users needing concurrency

**Evidence**: `AsyncHttpClientInterface` in `src/Contracts/AsyncHttpClientInterface.php`, implemented in `Http Client`

**API Methods**:
- `getAsync($uri, $options): PromiseInterface`
- `postAsync($uri, $options): PromiseInterface`
- `requestAsync($method, $uri, $options): PromiseInterface`

**Usage**:
```php
$promise = $client->getAsync('/users/1');
$response = $promise->wait();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Feature/AsyncTest.php`, `tests/Unit/Adapters/GuzzleHttpClientAdapterAsyncTest.php`

**Confidence**: Confirmed

---

### F004: Batch Processing

**Description**: Concurrent processing of multiple HTTP requests with configurable parallelism

**Audience**: Users needing to make multiple API calls efficiently

**Evidence**: `HttpClient::batch()` method in `src/Client/HttpClient.php`

**Parameters**:
- `$requests`: Iterable of promises, request objects, or callables
- `$concurrency`: Max parallel requests (default: 25)

**Returns**: Array of responses keyed by original request keys

**Usage**:
```php
$results = $client->batch([
    'user1' => fn() => $client->getAsync('/users/1'),
    'user2' => fn() => $client->getAsync('/users/2'),
], concurrency: 10);
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Client/HttpClientBatchTest.php`

**Confidence**: Confirmed

---

## Resilience Features

### F101: Retry with Exponential Backoff

**Description**: Automatically retry failed requests with increasing delays and optional jitter

**Audience**: Users building resilient systems

**Evidence**: `RetryMiddleware` in `src/Middleware/RetryMiddleware.php`

**Configuration**: `RetryConfig` value object

**Parameters**:
- `maxAttempts`: Maximum retry attempts (default: 3)
- `baseDelayMs`: Initial delay in milliseconds (default: 100)
- `maxDelayMs`: Maximum delay cap (default: 2000)
- `useJitter`: Add random jitter (default: true)
- `retryableStatuses`: HTTP statuses to retry (default: [429, 500, 502, 503, 504])
- `retryableMethods`: HTTP methods to retry (default: ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'])
- `retryableExceptions`: Exception types to retry

**Usage**:
```php
$client = ClientBuilder::create()
    ->withRetry(new RetryConfig(
        maxAttempts: 3,
        baseDelayMs: 100
    ))
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Middleware/RetryMiddlewareTest.php`, `tests/Feature/Resilience/RetryTest.php`

**Confidence**: Confirmed

---

### F102: Circuit Breaker

**Description**: Prevent cascading failures by "opening" circuit after threshold failures, with half-open testing

**Audience**: Users building resilient distributed systems

**Evidence**: `CircuitBreakerMiddleware` in `src/Middleware/CircuitBreakerMiddleware.php`

**Configuration**: `CircuitBreakerConfig` value object

**Parameters**:
- `failureThreshold`: Failures before opening (default: 5)
- `recoveryTimeoutMs`: Time in open state before half-open (default: 10000)
- `successThreshold`: Successes in half-open to close (default: 2)

**States**:
- **Closed**: Normal operation
- **Open**: Blocking requests (fails fast)
- **Half-Open**: Testing if service recovered

**Usage**:
```php
$client = ClientBuilder::create()
    ->withCircuitBreaker(new CircuitBreakerConfig(
        failureThreshold: 5,
        recoveryTimeoutMs: 10000
    ))
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Middleware/CircuitBreakerMiddlewareTest.php`, `tests/Feature/Resilience/CircuitBreakerTest.php`

**Confidence**: Confirmed

---

## Caching Features

### F201: Response Caching

**Description**: Cache HTTP responses using PSR-16 compatible cache implementations

**Audience**: Users optimizing performance

**Evidence**: `CacheMiddleware` in `src/Middleware/CacheMiddleware.php`

**Cache Implementations**:
- `MemoryCache`: In-memory cache (per-request)
- `FilesystemCache`: File-based persistent cache

**Parameters**:
- `$cache`: PSR-16 CacheInterface
- `$defaultTtl`: Default time-to-live in seconds (default: 3600)

**Per-Request TTL**: Set via `cache_ttl` option

**Behavior**:
- Only caches GET requests
- Uses request URI as cache key
- Serializes ResponseWrapper to cache

**Usage**:
```php
use JOOservices\Client\Cache\FilesystemCache;

$cache = new FilesystemCache(__DIR__ . '/cache');
$client = ClientBuilder::create()
    ->withCache($cache, defaultTtl: 3600)
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Middleware/CacheMiddlewareTest.php`, `tests/Unit/Cache/FilesystemCacheTest.php`, `tests/Unit/Cache/MemoryCacheTest.php`

**Confidence**: Confirmed

---

### F202: Memory Cache

**Description**: PSR-16 in-memory cache implementation

**Evidence**: `src/Cache/MemoryCache.php`

**Features**:
- In-memory array storage
- TTL support with expiration
- PSR-16 compliant

**Limitations**: Data lost after request/process ends

**Tests**: `tests/Unit/Cache/MemoryCacheTest.php`

**Confidence**: Confirmed

---

### F203: Filesystem Cache

**Description**: PSR-16 file-based cache implementation

**Evidence**: `src/Cache/FilesystemCache.php`

**Features**:
- JSON-based file storage
- Automatic directory creation
- TTL support
- SHA256 key hashing
- Secure serialization (no `unserialize()`)

**Security**: Fixed CVE-2026-XXXX by replacing `unserialize()` with JSON

**Tests**: `tests/Unit/Cache/FilesystemCacheTest.php`, `tests/Feature/Cache/FilesystemCacheTest.php`

**Confidence**: Confirmed

---

## Logging Features

### F301: Request/Response Logging

**Description**: Log HTTP requests and responses via PSR-3 logger

**Audience**: Users needing observability

**Evidence**: `LoggingMiddleware` in `src/Middleware/LoggingMiddleware.php`

**Parameters**:
- `$logger`: PSR-3 LoggerInterface
- `$logBodies`: Whether to log request/response bodies (default: false)

**Logged Data**:
- HTTP method
- URI
- Status code
- Duration
- local_ip
- target_ip
- target_hostname
- wan_ip
- Headers (redacted sensitive ones)
- Body (if enabled)

**Usage**:
```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('api');
$logger->pushHandler(new StreamHandler('php://stdout'));

$client = ClientBuilder::create()
    ->withLogger($logger, logBodies: true)
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Middleware/LoggingMiddleware Test.php`

**Confidence**: Confirmed

---

### F302: Monolog Integration

**Description**: Factory for creating Monolog loggers

**Evidence**: `MonologFactory` in `src/Logging/MonologFactory.php`

**Helper Method**:
- `createDaily(string $domain, ?string $path)`: Create daily rotating file logger

**Usage**:
```php
$logger = MonologFactory::createDaily('my-app', __DIR__ . '/logs');
$client = ClientBuilder::create()
    ->withDefaultLogging('my-app', __DIR__ . '/logs')
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Feature/Logging/PhysicalLoggingTest.php`

**Confidence**: Confirmed

---

### F303: MongoDB Logging

**Description**: PSR-3 logger writing to MongoDB collections

**Audience**: Users with MongoDB infrastructure

**Evidence**: `MongoDbLogger` in `src/Logging/MongoDbLogger.php`, `ClientRequestLog` model in `src/Models/Mongo/ClientRequestLog.php`

**Configuration**:
- `connection`: MongoDB connection name (default: 'mongodb')
- `collection`: Collection name (default: 'client_request_logs')
- `maxRequestBodyBytes`: Request body truncation (default: 4096)
- `maxResponseBodyBytes`: Response body truncation (default: 8192)
- `redactKeys`: Headers to redact (default: ['authorization', 'cookie', 'set-cookie', 'token'])

**Document Structure**:
- `level`: Log level
- `message`: Log message
- `method`: HTTP method
- `uri`: Request URI
- `status`: HTTP status code
- `duration_ms`: Request duration
- `correlation_id`: Trace ID
- `request_payload`: Request body (truncated)
- `response_payload`: Response body (truncated)
- `context`: Additional context
- `logged_at`: Timestamp

**Status**: ✅ Confirmed (implementation exists)

**Unknown**: Production-readiness and intended use case

**Tests**: `tests/Unit/Logging/MongoDbLoggerTest.php`, `tests/Feature/Logging/MongoDbLoggingTest.php`

**Confidence**: Confirmed (exists), Unknown (production use)

---

## Middleware Features

### F401: Correlation ID Middleware

**Description**: Add/propagate correlation IDs for distributed tracing

**Evidence**: `CorrelationIdMiddleware` in `src/Middleware/CorrelationIdMiddleware.php`

**Behavior**:
- Adds `X-Correlation-ID` header if not present
- Generates UUID v4 by default
- Propagates existing correlation ID
- Configurable header name via `correlation_header` option

**Usage**:
```php
$client = ClientBuilder::create()
    ->withCorrelationId('X-Trace-ID')  // Custom header name
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Middleware/CorrelationIdMiddlewareTest.php`

**Confidence**: Confirmed

---

### F402: User-Agent Middleware

**Description**: Set custom User-Agent header

**Evidence**: `UserAgentMiddleware` in `src/Middleware/UserAgentMiddleware.php`

**Usage**:
```php
$client = ClientBuilder::create()
    ->withUserAgent('MyApp/1.0')
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Middleware/UserAgentMiddlewareTest.php`, middleware ordering test

**Confidence**: Confirmed

---

### F403: Request/Response Interceptors

**Description**: Hook into request/response lifecycle for custom processing

**Evidence**: `InterceptorMiddleware` in `src/Middleware/InterceptorMiddleware.php`

**API Methods**:
- `onRequest(callable)`: Modify request before sending
- `onResponse(callable)`: Process response after receiving

**Usage**:
```php
$client = ClientBuilder::create()
    ->onRequest(function ($request, $options) {
        // Modify request
        return [$request, $options];
    })
    ->onResponse(function ($response) {
        // Process response
        return $response;
    })
    ->build();
```

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Middleware/InterceptorMiddlewareTest.php`

**Confidence**: Confirmed

---

## Response Handling Features

### F501: Response Wrapper

**Description**: Convenient wrapper around PSR-7 responses

**Evidence**: `ResponseWrapper` in `src/Response/ResponseWrapper.php`

**Methods**:
- `status(): int` - Get HTTP status code
- `json(): array|null` - Decode JSON body
- `header(string): ?string` - Get header value
- `toDto(string): object` - Convert to DTO
- `toPsrResponse(): ResponseInterface` - Get underlying PSR-7 response

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/Response/ResponseWrapperTest.php`

**Confidence**: Confirmed

---

## Configuration Features

### F601: Type-Safe Configuration

**Description**: Immutable value object for client configuration

**Evidence**: `ClientConfig` in `src/ValueObjects/ClientConfig.php`

**Properties** (all public readonly):
- `baseUri`: string
- `timeout`: int
- `connectTimeout`: int
- `headers`: array
- `verifySsl`: bool
- `httpErrors`: bool
- `options`: array

**Benefits**:
- Type safety
- Immutability
- IDE autocomplete
- Static analysis coverage

**Status**: ✅ Confirmed

**Tests**: `tests/Unit/ValueObjects/ClientConfigTest.php`

**Confidence**: Confirmed

---

## Quality Features

### F701: Comprehensive Test Suite

**Description**: 150+ tests covering unit, integration, and benchmarks

**Evidence**: Test suite execution results, tests directory structure

**Test Types**:
- **Unit Tests** (`tests/Unit/`): 100+ tests
- **Feature Tests** (`tests/Feature/`): Integration tests with real components
- **Benchmarks** (`tests/Benchmark/`): Performance measurements

**Coverage**: Confirmed 100% core coverage per README

**Framework**: PHPUnit 12

**Status**: ✅ Confirmed

**Confidence**: Confirmed

---

### F702: Static Analysis

**Description**: PHPStan level 9 (maximum) static analysis

**Evidence**: `phpstan.neon`, `composer.json` scripts

**Benefits**:
- Catch type errors before runtime
- Enforce strict typing
- Improve code quality
- Prevent common bugs

**Status**: ✅ Confirmed

**Confidence**: Confirmed

---

### F703: Code Quality Tools

**Description**: Multiple code quality tools in development workflow

**Evidence**: `composer.json` scripts, config files

**Tools**:
- **Laravel Pint**: PSR-12 code style
- **PHPCS**: Code sniffing
- **PHPMD**: Mess detection
- **PHPBench**: Performance benchmarking

**Unified Commands**: `composer lint:all`, `composer test`, and `composer check`

**Status**: ✅ Confirmed

**Confidence**: Confirmed

---

## Feature Matrix

| Feature ID | Feature Name | Status | Tests | User-Facing |
|------------|--------------|--------|-------|-------------|
| F001 | HTTP Request Methods | ✅ | ✅ | Yes |
| F002 | Fluent Client Builder | ✅ | ✅ | Yes |
| F003 | Async HTTP Requests | ✅ | ✅ | Yes |
| F004 | Batch Processing | ✅ | ✅ | Yes |
| F101 | Retry with Backoff | ✅ | ✅ | Yes |
| F102 | Circuit Breaker | ✅ | ✅ | Yes |
| F201 | Response Caching | ✅ | ✅ | Yes |
| F202 | Memory Cache | ✅ | ✅ | Yes |
| F203 | Filesystem Cache | ✅ | ✅ | Yes |
| F301 | Request/Response Logging | ✅ | ✅ | Yes |
| F302 | Monolog Integration | ✅ | ✅ | Yes |
| F303 | MongoDB Logging | ✅ | ✅ | Yes* |
| F401 | Correlation ID | ✅ | ✅ | Yes |
| F402 | User-Agent | ✅ | ✅ | Yes |
| F403 | Interceptors | ✅ | ✅ | Yes |
| F501 | Response Wrapper | ✅ | ✅ | Yes |
| F601 | Type-Safe Config | ✅ | ✅ | Internal |
| F701 | Test Suite | ✅ | N/A | Internal |
| F702 | Static Analysis | ✅ | N/A | Internal |
| F703 | Code Quality Tools | ✅ | N/A | Internal |

*MongoDB logging exists but production-readiness unclear

---

## Features NOT Implemented

Based on repository analysis, the following are NOT present:

❌ Rate limiting  
❌ Request deduplication  
❌ Compression middleware  
❌ Progress tracking  
❌ Request signing (OAuth, HMAC)  
❌ Cookie jar management  
❌ Request templates  
❌ Health checks  
❌ Metrics collection  
❌ Distributed tracing (beyond correlation IDs)  

**Evidence**: Absence from source code, mentioned in old docs but not implemented

---

## Related Documents

- [Project Overview](01-project-overview.md)
- [System Architecture](05-data-flow.md)
- [API Reference](../02-user-guide/api-reference.md)
- [Testing Strategy](../04-development/testing.md)
