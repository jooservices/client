# Glossary

## Purpose

Defines technical and domain terms used throughout the JOOservices HTTP Client documentation and codebase.

## Audience

All readers, especially new contributors and users.

---

## Core Concepts

### Adapter Pattern
**Definition**: Design pattern that converts the interface of a class into another interface clients expect.

**In This Library**: `GuzzleHttpClientAdapter` (`src/Adapters/Guzzle/GuzzleHttpClientAdapter.php`) adapts Guzzle's PSR-18 HTTP client to our `TransportAdapterInterface`, isolating Guzzle-specific dependencies from core business logic.

### Async (Asynchronous)
**Definition**: Non-blocking operations that return immediately with a promise of future completion.

**In This Library**: Methods like `getAsync()`, `postAsync()` return Guzzle `PromiseInterface` objects that resolve to responses later. Enables concurrent requests.

### Batch Processing
**Definition**: Processing multiple operations concurrently within controlled concurrency limits.

**In This Library**: `HttpClient::batch()` method processes multiple HTTP requests concurrently with configurable parallelism (default: 25).

---

## Resilience Patterns

### Circuit Breaker
**Definition**: Pattern that prevents cascading failures by "opening" (blocking requests) after threshold failures, then allowing limited "half-open" testing before fully "closing" (resuming normal operation).

**In This Library**: `CircuitBreakerMiddleware` (`src/Middleware/CircuitBreakerMiddleware.php`) tracks failures and controls request flow through three states: Closed, Open, Half-Open.

**Configuration**: `CircuitBreakerConfig` (`src/Resilience/CircuitBreakerConfig.php`)

### Exponential Backoff
**Definition**: Retry strategy where delay between attempts increases exponentially (e.g., 100ms, 200ms, 400ms).

**In This Library**: `RetryMiddleware` implements exponential backoff with optional jitter. Base delay doubles on each retry up to max delay.

### Jitter
**Definition**: Random variation added to retry delays to prevent "thundering herd" when many clients retry simultaneously.

**In This Library**: RetryConfig enables decorrelated jitter by default to distribute retry attempts over time.

---

## Architecture Terms

### Builder Pattern
**Definition**: Creational design pattern that constructs complex objects step-by-step.

**In This Library**: `ClientBuilder` (`src/Client/ClientBuilder.php`) provides fluent interface for configuring and building `HttpClient` instances.

### Middleware
**Definition**: Component that intercepts requests/responses in a processing pipeline, optionally modifying them or taking actions.

**In This Library**: Classes implementing `MiddlewareInterface` (`src/Contracts/MiddlewareInterface.php`). Examples: `CacheMiddleware`, `LoggingMiddleware`, `RetryMiddleware`.

### Middleware Pipeline
**Definition**: Chain of middleware executed in sequence, where each middleware can process the request, call the next middleware, and process the response.

**In This Library**: `MiddlewarePipeline` (`src/Middleware/MiddlewarePipeline.php`) manages middleware registration and execution order.

### Value Object
**Definition**: Immutable object defined by its values rather than identity, used to model domain concepts.

**In This Library**: `ClientConfig`, `RetryConfig`, `CircuitBreakerConfig` are readonly value objects that encapsulate configuration.

---

## PSR Standards

### PSR-3 (Logging Interface)
**Definition**: PHP Standard Recommendation defining common interface for logging libraries.

**In This Library**: `MonologFactory` creates PSR-3 compatible loggers. `LoggingMiddleware` accepts any PSR-3 `LoggerInterface`.

**Evidence**: `psr/log: ^3.0` in `composer.json`

### PSR-7 (HTTP Message Interface)
**Definition**: PHP Standard Recommendation defining common interfaces for HTTP messages (requests and responses).

**In This Library**: Guzzle uses PSR-7 messages internally. Our middleware operates on PSR-7 `RequestInterface` and `ResponseInterface`.

### PSR-16 (Simple Cache)
**Definition**: PHP Standard Recommendation defining simple caching interface.

**In This Library**: `CacheMiddleware` accepts any PSR-16 `CacheInterface`. Includes `MemoryCache` and `FilesystemCache` implementations.

**Evidence**: `psr/simple-cache: ^3.0` in `composer.json`

### PSR-18 (HTTP Client)
**Definition**: PHP Standard Recommendation defining HTTP client interface.

**In This Library**: Guzzle implements PSR-18. Our adapter wraps it.

---

## HTTP Concepts

### Correlation ID
**Definition**: Unique identifier attached to requests to trace them through distributed systems.

**In This Library**: `CorrelationIdMiddleware` (`src/Middleware/CorrelationIdMiddleware.php`) adds `X-Correlation-ID` header to requests if not present.

### Idempotent
**Definition**: HTTP method that produces same result regardless of how many times it's executed.

**Examples**: GET, PUT, DELETE are idempotent. POST typically is not.

**In This Library**: `RetryConfig` retries GET, PUT, DELETE by default but not POST (see `retryableMethods` property).

### User-Agent
**Definition**: HTTP header identifying the client software making requests.

**In This Library**: `UserAgentMiddleware` (`src/Middleware/UserAgentMiddleware.php`) sets custom User-Agent header.

---

## Library-Specific Terms

### ClientConfig
**Evidence**: `src/ValueObjects/ClientConfig.php`

**Definition**: Immutable value object holding HTTP client configuration:
- `baseUri`: Base URL for requests
- `timeout`: Request timeout in seconds
- `connectTimeout`: Connection timeout in seconds
- `headers`: Default headers
- `verifySsl`: Whether to verify SSL certificates
- `httpErrors`: Whether to throw on HTTP errors
- `options`: Additional Guzzle options

### ResponseWrapper
**Evidence**: `src/Response/ResponseWrapper.php`

**Definition**: Wrapper around PSR-7 ResponseInterface providing convenient methods:
- `status()`: Get status code
- `json()`: Decode JSON body
- `header()`: Get header value
- `toDto()`: Convert to DTO
- `toPsrResponse()`: Get underlying PSR-7 response

### StateStore
**Evidence**: `src/Resilience/Contracts/StateStoreInterface.php`

**Definition**: Interface for persisting circuit breaker state. Implementations:
- `InMemoryStateStore`: Stores state in PHP memory (default)

### TransportAdapter
**Evidence**: `src/Contracts/TransportAdapterInterface.php`

**Definition**: Interface abstracting HTTP transport layer:
- `send()`: Synchronous request
- `sendAsync()`: Asynchronous request

**Implementation**: `GuzzleHttpClientAdapter`

---

## Testing Terminology

### Feature Test
**Definition**: Test that verifies end-to-end functionality with real components (e.g., actual file I/O, real cache).

**In This Library**: Tests in `tests/Feature/` use real filesystems, caches, but mock network with `Guzzle\MockHandler`.

### Unit Test
**Definition**: Test that verifies individual components in isolation, typically with mocked dependencies.

**In This Library**: Tests in `tests/Unit/` test classes in isolation with mocked dependencies.

### Benchmark
**Definition**: Performance test measuring execution time, memory usage, or throughput.

**In This Library**: `tests/Benchmark/CoreBench.php` measures middleware overhead using PHPBench.

---

## Vendor Dependencies

### Guzzle
**Full Name**: Guzzle PHP HTTP Client

**Purpose**: Underlying HTTP transport library

**Version**: ^7.9 (per `composer.json`)

**Documentation**: https://docs.guzzlephp.org/

### Monolog
**Purpose**: PSR-3 logging library

**Version**: ^3.10 (per `composer.json`)

**In This Library**: `MonologFactory` creates Monolog instances for logging

### MongoDB Laravel Library
**Purpose**: Laravel MongoDB integration

**Version**: ^5.6 (per `composer.json`)

**Usage**: `MongoDbLogger` and `ClientRequestLog` model for MongoDB logging

**Note**: Despite name, this library is NOT Laravel-specific in v1.0.0

### PHPUnit
**Purpose**: PHP testing framework

**Version**: ^12.0 (dev dependency)

**Usage**: All tests written in PHPUnit (unit, feature, integration, arch groups)

### PHPStan
**Purpose**: Static analysis tool

**Level**: 9 (maximum) per `phpstan.neon`

**Usage**: Enforces strict typing and code quality

---

## Abbreviations

- **API**: Application Programming Interface
- **DTO**: Data Transfer Object
- **FIFO**: First In First Out
- **HTTP**: Hypertext Transfer Protocol
- **LIFO**: Last In First Out
- **ORM**: Object-Relational Mapping
- **PSR**: PHP Standard Recommendation
- **SSL/TLS**: Secure Sockets Layer/Transport Layer Security
- **TTL**: Time To Live (cache duration)
- **URI/URL**: Uniform Resource Identifier/Locator

---

## Related Documents

- [Project Overview](../00-architecture/01-project-overview.md)
- [System Architecture](../00-architecture/05-data-flow.md)
- [Documentation Standards](documentation-standards.md)
