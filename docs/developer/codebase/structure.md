# JOOClient Code Structure

## Overview

JOOClient is a PHP HTTP client library that wraps Guzzle with additional features like logging, caching, rate limiting, circuit breaking, and more. This document provides a comprehensive overview of the codebase structure.

## Directory Structure

```
src/
├── Jooclient.php                    # Main entry point (static factory)
├── Cache/                           # Caching system
│   ├── CacheFactory.php
│   ├── RedisCache.php
│   ├── Config/                      # Cache configuration
│   └── Middleware/                  # Cache middleware
├── CircuitBreaker/                  # Circuit breaker pattern
│   ├── CircuitBreaker.php
│   ├── CircuitBreakerFactory.php
│   ├── CircuitBreakerState.php
│   ├── Config/
│   ├── Contracts/
│   └── Middleware/
├── Config/                          # Configuration parsing
│   └── ConfigParser.php
├── Console/                         # Laravel console commands
│   └── Commands/
│       ├── InstallCommand.php
│       ├── LogsCommand.php
│       ├── PruneCommand.php
│       └── StatsCommand.php
├── Constants/                       # Application constants
│   └── LoggingConstants.php
├── Contracts/                       # Interfaces (8 interfaces)
│   ├── AsyncHttpClientContract.php
│   ├── CacheAdapterInterface.php
│   ├── FormHttpClientContract.php
│   ├── HttpClientContract.php
│   ├── JsonHttpClientContract.php
│   ├── LoggingAdapterInterface.php
│   └── StreamingHttpClientContract.php
├── Cookies/                         # Cookie management
│   └── CookieJarManager.php
├── Exceptions/                      # Exception hierarchy (20+ classes)
│   ├── Cache/
│   ├── CircuitBreaker/
│   ├── Factory/
│   ├── Health/
│   ├── Http/
│   ├── Logging/
│   ├── Monolog/
│   ├── RateLimit/
│   ├── Redis/
│   └── Validation/
├── Factory/                         # Factory pattern implementation
│   ├── Factory.php                  # Main factory (956 lines)
│   ├── Client.php                   # Client wrapper (533 lines)
│   ├── FactoryConfig.php
│   ├── HistoryManager.php
│   ├── Builders/                    # Builder classes
│   │   ├── ClientBuilder.php
│   │   ├── ConfigApplier.php
│   │   └── MiddlewareStackBuilder.php
│   ├── Client/                      # Specialized client types
│   │   ├── AsyncClient.php
│   │   ├── FormClient.php
│   │   ├── JsonClient.php
│   │   └── StreamingClient.php
│   └── Contracts/                   # Factory interfaces
│       ├── CacheFactoryInterface.php
│       ├── LoggingFactoryInterface.php
│       └── RateLimitFactoryInterface.php
├── Http/                            # HTTP utilities
│   ├── RequestChain.php             # Request chaining
│   ├── ResponseWrapper.php          # Response wrapper
│   ├── DomWrapper.php               # DOM manipulation
│   ├── Content/                     # Content adapters
│   │   ├── ContentTransformer.php
│   │   ├── HtmlContentAdapter.php
│   │   ├── JsonContentAdapter.php
│   │   ├── RawContentAdapter.php
│   │   └── ResponseContentAdapterInterface.php
│   ├── Debug/
│   │   └── RequestDebugger.php
│   └── Error/
│       └── ErrorExtractor.php
├── Logging/                         # Logging system (20+ files)
│   ├── LoggingManager.php           # Multi-logger manager
│   ├── LoggingFactory.php           # Logger factory
│   ├── ConditionalLoggingManager.php
│   ├── RequestResponseLogger.php
│   ├── DbLogger.php                 # MySQL logger
│   ├── MongoDbLogger.php            # MongoDB logger
│   ├── Buffers/                     # Log buffering
│   │   └── LogBuffer.php
│   ├── Concerns/                    # Traits
│   │   └── ProvidesPsrLoggingMethods.php
│   ├── Config/                      # Configuration value objects
│   │   ├── DatabaseConnectionConfig.php
│   │   ├── MongoDbConfig.php
│   │   ├── MonologConfig.php
│   │   └── RetriesConfig.php
│   ├── Contracts/                   # Logging interfaces
│   │   └── RequestResponseExtractorInterface.php
│   ├── Drivers/                     # Logging adapters
│   │   ├── DbLoggingAdapter.php
│   │   ├── MongoDbLoggingAdapter.php
│   │   └── MonologLoggingAdapter.php
│   ├── Enrichers/                   # Log enrichment
│   │   ├── PerformanceMetricsEnricher.php
│   │   └── StructuredMetadataEnricher.php
│   ├── Extractors/                  # Data extraction
│   │   └── RequestResponseExtractor.php
│   ├── Filters/                     # Log filtering
│   │   ├── LogLevelFilter.php
│   │   └── LogSampler.php
│   ├── Handlers/                    # Request handling
│   │   └── RequestBodyHandler.php
│   ├── Middlewares/                 # Middleware factories
│   │   ├── DbLoggingMiddlewareFactory.php
│   │   ├── MonologLoggingMiddlewareFactory.php
│   │   └── ErrorHandlerTrait.php
│   └── Sanitizers/                  # Data sanitization
│       └── DataSanitizer.php
├── Metrics/                         # Metrics collection
│   └── MetricsCollector.php
├── Middlewares/                     # Guzzle middlewares (7 files)
│   ├── CompressionMiddleware.php
│   ├── CorrelationIdMiddleware.php
│   ├── DeduplicationMiddleware.php
│   ├── DesktopUserAgentMiddleware.php
│   ├── InterceptorMiddleware.php
│   ├── ProgressTrackingMiddleware.php
│   └── RequestResponseLogger.php
├── Models/                          # Eloquent models
│   └── ClientRequestLog.php
├── Providers/                      # Laravel service providers
│   └── JooclientServiceProvider.php
├── Queue/                           # Request queuing
│   └── RequestQueue.php
├── RateLimit/                       # Rate limiting
│   ├── RateLimitFactory.php
│   ├── InMemoryCacheAdapter.php
│   ├── Middleware/
│   │   └── RateLimitingMiddleware.php
│   └── Strategies/
│       ├── RateLimitStrategyInterface.php
│       ├── FixedWindowStrategy.php
│       ├── SlidingWindowStrategy.php
│       ├── TokenBucketStrategy.php
│       └── RateLimitResult.php
├── Replay/                          # Request replay
│   └── RequestReplay.php
├── Repositories/                    # Data persistence
│   └── ClientRequestLogRepository.php
├── Signing/                         # Request signing
│   ├── RequestSignerInterface.php
│   ├── Middleware/
│   │   └── RequestSigningMiddleware.php
│   └── Signers/
│       ├── HmacSigner.php
│       └── OAuth1Signer.php
├── Streaming/                       # Server-sent events
│   ├── SSEEvent.php
│   └── SSEParser.php
├── Support/                         # Helper utilities
│   ├── DatabaseHelper.php
│   └── HealthCheck.php
├── Templates/                       # Request templates
│   ├── RequestTemplate.php
│   └── TemplateManager.php
└── Validation/                      # Response validation
    └── ResponseValidator.php
```

## Key Components

### Entry Points

1. **Jooclient.php** - Static factory that converts Laravel config to Factory
2. **Factory/Factory.php** - Immutable builder for creating configured clients
3. **Factory/Client.php** - Client wrapper implementing multiple interfaces

### Core Patterns

1. **Factory Pattern** - `Factory.php` creates configured Guzzle clients
2. **Builder Pattern** - Immutable builder with method chaining
3. **Strategy Pattern** - Rate limiting strategies, logging adapters
4. **Adapter Pattern** - Logging adapters, content adapters
5. **Middleware Pattern** - Guzzle middleware stack

### Feature Modules

1. **Logging** - Multi-driver logging (MySQL, MongoDB, Monolog)
2. **Caching** - Redis and filesystem caching
3. **Rate Limiting** - Multiple strategies (fixed window, sliding window, token bucket)
4. **Circuit Breaker** - Fault tolerance pattern
5. **Request Signing** - OAuth1 and HMAC signing
6. **Request Chaining** - Sequential request execution
7. **Request Queuing** - Batch request processing
8. **Request Replay** - Debugging tool

## File Statistics

- **Total PHP Files**: ~142 files
- **Largest Classes**:
  - `Factory.php`: 956 lines
  - `Client.php`: 533 lines
- **Most Complex Directory**: `Logging/` with 20+ files
- **Exception Classes**: 20+ exception classes organized by domain

## Architecture Layers

1. **Entry Point Layer** - `Jooclient.php`, `JooclientServiceProvider.php`
2. **Factory Layer** - `Factory.php`, `Client.php`, builders
3. **Feature Layer** - Logging, caching, rate limiting, circuit breaker
4. **Middleware Layer** - Guzzle middleware implementations
5. **Support Layer** - Utilities, helpers, models, repositories

## Dependencies

### External Dependencies
- `guzzlehttp/guzzle` - HTTP client
- `illuminate/database` - Database abstraction
- `illuminate/support` - Laravel support
- `psr/log` - PSR-3 logging interface
- `symfony/dom-crawler` - DOM manipulation

### Internal Dependencies
- Factory depends on 15+ classes
- Client implements 5 interfaces
- Logging system has complex dependency graph

## Design Decisions

1. **Immutable Factory** - Factory methods return new instances (immutability)
2. **Interface Segregation** - Multiple small interfaces instead of one large interface
3. **Dependency Injection** - Constructor injection for testability
4. **Value Objects** - Configuration classes as value objects
5. **Exception Hierarchy** - Domain-specific exception classes

## Code Organization Principles

1. **PSR-4 Autoloading** - Namespace matches directory structure
2. **Single Responsibility** - Each class has one primary responsibility (in theory)
3. **Separation of Concerns** - Features organized into separate directories
4. **Interface-Based Design** - Contracts define interfaces, implementations in separate directories

## Potential Issues

1. **Factory.php Complexity** - 956 lines with 20+ methods and 15+ dependencies
2. **Logging Over-Engineering** - 20+ files for logging system
3. **Exception Proliferation** - 20+ exception classes (could be consolidated)
4. **Tight Coupling** - Factory depends on many concrete classes
5. **Missing Abstractions** - Some features lack unified interfaces

## Recommendations

See [SOLID_ANALYSIS.md](./SOLID_ANALYSIS.md) for detailed analysis and refactoring recommendations.
