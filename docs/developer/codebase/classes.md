# JOOClient Classes and Traits Reference

Complete reference of all classes, interfaces, and traits in the JOOClient library.

## Entry Points

### Jooclient
**Location**: `src/Jooclient.php`  
**Type**: Final class  
**Purpose**: Static factory that converts Laravel configuration to Factory

**Key Methods**:
- `fromConfig(array $config): Factory` - Build Factory from config array

**Usage**:
```php
$factory = Jooclient::fromConfig(config('jooclient'));
```

---

## Factory Layer

### Factory
**Location**: `src/Factory/Factory.php`  
**Type**: Final class  
**Lines**: 956  
**Purpose**: Immutable builder for creating configured Guzzle clients

**Key Methods**:
- `addOptions(array $options): self` - Add Guzzle options
- `enableLogging(?array $config = null): self` - Enable logging
- `enableCache(?array $config = null): self` - Enable caching
- `enableRateLimiting(?array $config = null): self` - Enable rate limiting
- `enableCircuitBreaker(?array $config = null): self` - Enable circuit breaker
- `enableRetries(int $maxRetries, int $delayInSec, int $minErrorCode): self` - Enable retries
- `enableRequestSigning(array $config): self` - Enable request signing
- `enableDeduplication(array $config = []): self` - Enable deduplication
- `enableCompression(array $encodings): self` - Enable compression
- `onRequest(callable $interceptor): self` - Add request interceptor
- `onResponse(callable $interceptor): self` - Add response interceptor
- `onError(callable $interceptor): self` - Add error interceptor
- `registerTemplate(string $name, array $options): self` - Register request template
- `enableCorrelationIds(string $headerName, ?callable $generator): self` - Enable correlation IDs
- `make(): Client` - Create the client

**Dependencies**: 15+ classes (LoggingFactory, CacheFactory, RateLimitFactory, CircuitBreakerFactory, etc.)

### Client
**Location**: `src/Factory/Client.php`  
**Type**: Final class  
**Lines**: 533  
**Purpose**: Client wrapper implementing multiple interfaces

**Implements**:
- `HttpClientContract`
- `AsyncHttpClientContract`
- `StreamingHttpClientContract`
- `JsonHttpClientContract`
- `FormHttpClientContract`

**Key Methods**:
- `request(string $method, string|UriInterface $uri, array $options): ResponseWrapper`
- `get(string|UriInterface $uri, array $options): ResponseWrapper`
- `post(string|UriInterface $uri, array $options): ResponseWrapper`
- `put(string|UriInterface $uri, array $options): ResponseWrapper`
- `patch(string|UriInterface $uri, array $options): ResponseWrapper`
- `delete(string|UriInterface $uri, array $options): ResponseWrapper`
- `getJson(string|UriInterface $uri, array $options): array|null`
- `postJson(string|UriInterface $uri, array $data, array $options): array|null`
- `chain(): RequestChain` - Create request chain
- `createQueue(array $config): RequestQueue` - Create request queue
- `createReplay(): RequestReplay` - Create request replay
- `flushLogger(): void` - Flush buffered logs

### FactoryConfig
**Location**: `src/Factory/FactoryConfig.php`  
**Type**: Final class  
**Purpose**: Configuration value object for Factory

### HistoryManager
**Location**: `src/Factory/HistoryManager.php`  
**Type**: Final class  
**Purpose**: Manages request history for testing

---

## Factory Builders

### ClientBuilder
**Location**: `src/Factory/Builders/ClientBuilder.php`  
**Type**: Final class  
**Purpose**: Builds Guzzle client instances

### MiddlewareStackBuilder
**Location**: `src/Factory/Builders/MiddlewareStackBuilder.php`  
**Type**: Final class  
**Purpose**: Builds Guzzle middleware stack

### ConfigApplier
**Location**: `src/Factory/Builders/ConfigApplier.php`  
**Type**: Final class  
**Purpose**: Applies default configurations

---

## Factory Client Types

### AsyncClient
**Location**: `src/Factory/Client/AsyncClient.php`  
**Type**: Final class  
**Purpose**: Async HTTP client operations

### FormClient
**Location**: `src/Factory/Client/FormClient.php`  
**Type**: Final class  
**Purpose**: Form data HTTP client

### JsonClient
**Location**: `src/Factory/Client/JsonClient.php`  
**Type**: Final class  
**Purpose**: JSON HTTP client

### StreamingClient
**Location**: `src/Factory/Client/StreamingClient.php`  
**Type**: Final class  
**Purpose**: Streaming HTTP client

---

## Contracts (Interfaces)

### HttpClientContract
**Location**: `src/Contracts/HttpClientContract.php`  
**Extends**: PSR-7 RequestInterface methods  
**Purpose**: Base HTTP client contract

### AsyncHttpClientContract
**Location**: `src/Contracts/AsyncHttpClientContract.php`  
**Purpose**: Async HTTP operations contract

### StreamingHttpClientContract
**Location**: `src/Contracts/StreamingHttpClientContract.php`  
**Purpose**: Streaming HTTP operations contract

### JsonHttpClientContract
**Location**: `src/Contracts/JsonHttpClientContract.php`  
**Purpose**: JSON HTTP operations contract

### FormHttpClientContract
**Location**: `src/Contracts/FormHttpClientContract.php`  
**Purpose**: Form data HTTP operations contract

### LoggingAdapterInterface
**Location**: `src/Contracts/LoggingAdapterInterface.php`  
**Purpose**: Logging adapter contract

**Methods**:
- `log(array $data): void`
- `flush(): void`
- `getPsrLogger(): LoggerInterface`

### CacheAdapterInterface
**Location**: `src/Contracts/CacheAdapterInterface.php`  
**Purpose**: Cache adapter contract

### Factory Contracts

#### LoggingFactoryInterface
**Location**: `src/Factory/Contracts/LoggingFactoryInterface.php`  
**Purpose**: Factory for creating loggers

#### CacheFactoryInterface
**Location**: `src/Factory/Contracts/CacheFactoryInterface.php`  
**Purpose**: Factory for creating cache adapters

#### RateLimitFactoryInterface
**Location**: `src/Factory/Contracts/RateLimitFactoryInterface.php`  
**Purpose**: Factory for creating rate limit strategies

---

## Logging System

### LoggingFactory
**Location**: `src/Logging/LoggingFactory.php`  
**Type**: Final class  
**Purpose**: Creates logging adapters from configuration

**Key Methods**:
- `createFromConfig(array $config): ?LoggingAdapterInterface`

### LoggingManager
**Location**: `src/Logging/LoggingManager.php`  
**Type**: Final class  
**Purpose**: Multi-logger manager (composite pattern)

**Implements**: `LoggingAdapterInterface`, `LoggerInterface`

### ConditionalLoggingManager
**Location**: `src/Logging/ConditionalLoggingManager.php`  
**Type**: Final class  
**Purpose**: Conditional routing to different loggers

### DbLogger
**Location**: `src/Logging/DbLogger.php`  
**Type**: Final class  
**Implements**: `LoggerInterface`  
**Purpose**: MySQL database logger

### MongoDbLogger
**Location**: `src/Logging/MongoDbLogger.php`  
**Type**: Final class  
**Implements**: `LoggerInterface`  
**Purpose**: MongoDB logger

### RequestResponseLogger
**Location**: `src/Logging/RequestResponseLogger.php`  
**Type**: Final class  
**Purpose**: Logs requests and responses

### Logging Adapters

#### DbLoggingAdapter
**Location**: `src/Logging/Drivers/DbLoggingAdapter.php`  
**Implements**: `LoggingAdapterInterface`  
**Purpose**: MySQL logging adapter

#### MongoDbLoggingAdapter
**Location**: `src/Logging/Drivers/MongoDbLoggingAdapter.php`  
**Implements**: `LoggingAdapterInterface`  
**Purpose**: MongoDB logging adapter

#### MonologLoggingAdapter
**Location**: `src/Logging/Drivers/MonologLoggingAdapter.php`  
**Implements**: `LoggingAdapterInterface`  
**Purpose**: Monolog file logging adapter

### Logging Components

#### LogBuffer
**Location**: `src/Logging/Buffers/LogBuffer.php`  
**Purpose**: Batch log entries

#### RequestResponseExtractor
**Location**: `src/Logging/Extractors/RequestResponseExtractor.php`  
**Implements**: `RequestResponseExtractorInterface`  
**Purpose**: Extracts HTTP data for logging

#### RequestBodyHandler
**Location**: `src/Logging/Handlers/RequestBodyHandler.php`  
**Purpose**: Handles request body buffering

#### DataSanitizer
**Location**: `src/Logging/Sanitizers/DataSanitizer.php`  
**Purpose**: Sanitizes log data

#### LogLevelFilter
**Location**: `src/Logging/Filters/LogLevelFilter.php`  
**Purpose**: Filters logs by level

#### LogSampler
**Location**: `src/Logging/Filters/LogSampler.php`  
**Purpose**: Samples logs (reduces volume)

#### PerformanceMetricsEnricher
**Location**: `src/Logging/Enrichers/PerformanceMetricsEnricher.php`  
**Purpose**: Adds performance metrics to logs

#### StructuredMetadataEnricher
**Location**: `src/Logging/Enrichers/StructuredMetadataEnricher.php`  
**Purpose**: Adds structured metadata to logs

### Logging Configuration

#### DatabaseConnectionConfig
**Location**: `src/Logging/Config/DatabaseConnectionConfig.php`  
**Type**: Value object  
**Purpose**: MySQL connection configuration

#### MongoDbConfig
**Location**: `src/Logging/Config/MongoDbConfig.php`  
**Type**: Value object  
**Purpose**: MongoDB connection configuration

#### MonologConfig
**Location**: `src/Logging/Config/MonologConfig.php`  
**Type**: Value object  
**Purpose**: Monolog configuration

#### RetriesConfig
**Location**: `src/Logging/Config/RetriesConfig.php`  
**Type**: Value object  
**Purpose**: Retry configuration

### Logging Middleware Factories

#### DbLoggingMiddlewareFactory
**Location**: `src/Logging/Middlewares/DbLoggingMiddlewareFactory.php`  
**Purpose**: Creates DB logging middleware

#### MonologLoggingMiddlewareFactory
**Location**: `src/Logging/Middlewares/MonologLoggingMiddlewareFactory.php`  
**Purpose**: Creates Monolog logging middleware

### Logging Traits

#### ProvidesPsrLoggingMethods
**Location**: `src/Logging/Concerns/ProvidesPsrLoggingMethods.php`  
**Purpose**: Provides PSR-3 logging methods

#### ErrorHandlerTrait
**Location**: `src/Logging/Middlewares/ErrorHandlerTrait.php`  
**Purpose**: Shared error handling logic

### Logging Interfaces

#### RequestResponseExtractorInterface
**Location**: `src/Logging/Contracts/RequestResponseExtractorInterface.php`  
**Purpose**: Interface for extracting request/response data

---

## Middlewares

### CompressionMiddleware
**Location**: `src/Middlewares/CompressionMiddleware.php`  
**Purpose**: Request/response compression

### CorrelationIdMiddleware
**Location**: `src/Middlewares/CorrelationIdMiddleware.php`  
**Purpose**: Adds correlation IDs to requests

### DeduplicationMiddleware
**Location**: `src/Middlewares/DeduplicationMiddleware.php`  
**Purpose**: Prevents duplicate requests

### DesktopUserAgentMiddleware
**Location**: `src/Middlewares/DesktopUserAgentMiddleware.php`  
**Purpose**: Adds desktop user agents

### InterceptorMiddleware
**Location**: `src/Middlewares/InterceptorMiddleware.php`  
**Purpose**: Request/response/error interceptors

### ProgressTrackingMiddleware
**Location**: `src/Middlewares/ProgressTrackingMiddleware.php`  
**Purpose**: Tracks upload/download progress

### RequestResponseLogger
**Location**: `src/Middlewares/RequestResponseLogger.php`  
**Purpose**: Logs requests and responses

---

## Rate Limiting

### RateLimitFactory
**Location**: `src/RateLimit/RateLimitFactory.php`  
**Purpose**: Creates rate limit strategies

### RateLimitingMiddleware
**Location**: `src/RateLimit/Middleware/RateLimitingMiddleware.php`  
**Purpose**: Rate limiting middleware

### InMemoryCacheAdapter
**Location**: `src/RateLimit/InMemoryCacheAdapter.php`  
**Purpose**: In-memory cache for rate limiting

### Rate Limit Strategies

#### RateLimitStrategyInterface
**Location**: `src/RateLimit/Strategies/RateLimitStrategyInterface.php`  
**Purpose**: Rate limit strategy contract

#### FixedWindowStrategy
**Location**: `src/RateLimit/Strategies/FixedWindowStrategy.php`  
**Purpose**: Fixed window rate limiting

#### SlidingWindowStrategy
**Location**: `src/RateLimit/Strategies/SlidingWindowStrategy.php`  
**Purpose**: Sliding window rate limiting

#### TokenBucketStrategy
**Location**: `src/RateLimit/Strategies/TokenBucketStrategy.php`  
**Purpose**: Token bucket rate limiting

#### RateLimitResult
**Location**: `src/RateLimit/Strategies/RateLimitResult.php`  
**Purpose**: Rate limit result value object

---

## Circuit Breaker

### CircuitBreaker
**Location**: `src/CircuitBreaker/CircuitBreaker.php`  
**Purpose**: Circuit breaker implementation

### CircuitBreakerFactory
**Location**: `src/CircuitBreaker/CircuitBreakerFactory.php`  
**Purpose**: Creates circuit breakers

### CircuitBreakerState
**Location**: `src/CircuitBreaker/CircuitBreakerState.php`  
**Purpose**: Circuit breaker state management

### CircuitBreakerConfig
**Location**: `src/CircuitBreaker/Config/CircuitBreakerConfig.php`  
**Purpose**: Circuit breaker configuration

### CircuitBreakerMiddleware
**Location**: `src/CircuitBreaker/Middleware/CircuitBreakerMiddleware.php`  
**Purpose**: Circuit breaker middleware

### CircuitBreakerFactoryInterface
**Location**: `src/CircuitBreaker/Contracts/CircuitBreakerFactoryInterface.php`  
**Purpose**: Circuit breaker factory contract

---

## Caching

### CacheFactory
**Location**: `src/Cache/CacheFactory.php`  
**Purpose**: Creates cache adapters

### RedisCache
**Location**: `src/Cache/RedisCache.php`  
**Purpose**: Redis cache implementation

### CacheMiddleware
**Location**: `src/Cache/Middleware/CacheMiddleware.php`  
**Purpose**: Cache middleware

---

## Request Signing

### RequestSignerInterface
**Location**: `src/Signing/RequestSignerInterface.php`  
**Purpose**: Request signer contract

### RequestSigningMiddleware
**Location**: `src/Signing/Middleware/RequestSigningMiddleware.php`  
**Purpose**: Request signing middleware

### Signers

#### HmacSigner
**Location**: `src/Signing/Signers/HmacSigner.php`  
**Purpose**: HMAC request signing

#### OAuth1Signer
**Location**: `src/Signing/Signers/OAuth1Signer.php`  
**Purpose**: OAuth1 request signing

---

## HTTP Utilities

### RequestChain
**Location**: `src/Http/RequestChain.php`  
**Purpose**: Sequential request chaining

### ResponseWrapper
**Location**: `src/Http/ResponseWrapper.php`  
**Purpose**: Wraps PSR-7 responses with convenience methods

### DomWrapper
**Location**: `src/Http/DomWrapper.php`  
**Purpose**: DOM manipulation utilities

### Content Adapters

#### ContentTransformer
**Location**: `src/Http/Content/ContentTransformer.php`  
**Purpose**: Transforms response content

#### HtmlContentAdapter
**Location**: `src/Http/Content/HtmlContentAdapter.php`  
**Implements**: `ResponseContentAdapterInterface`  
**Purpose**: HTML content adapter

#### JsonContentAdapter
**Location**: `src/Http/Content/JsonContentAdapter.php`  
**Implements**: `ResponseContentAdapterInterface`  
**Purpose**: JSON content adapter

#### RawContentAdapter
**Location**: `src/Http/Content/RawContentAdapter.php`  
**Implements**: `ResponseContentAdapterInterface`  
**Purpose**: Raw content adapter

#### ResponseContentAdapterInterface
**Location**: `src/Http/Content/ResponseContentAdapterInterface.php`  
**Purpose**: Content adapter contract

### Error Extractor
**Location**: `src/Http/Error/ErrorExtractor.php`  
**Purpose**: Extracts error information

### Request Debugger
**Location**: `src/Http/Debug/RequestDebugger.php`  
**Purpose**: Debugs HTTP requests

---

## Request Management

### RequestQueue
**Location**: `src/Queue/RequestQueue.php`  
**Purpose**: Batch request processing

### RequestReplay
**Location**: `src/Replay/RequestReplay.php`  
**Purpose**: Replay recorded requests

---

## Templates

### TemplateManager
**Location**: `src/Templates/TemplateManager.php`  
**Purpose**: Manages request templates

### RequestTemplate
**Location**: `src/Templates/RequestTemplate.php`  
**Purpose**: Request template value object

---

## Streaming

### SSEParser
**Location**: `src/Streaming/SSEParser.php`  
**Purpose**: Server-sent events parser

### SSEEvent
**Location**: `src/Streaming/SSEEvent.php`  
**Purpose**: SSE event value object

---

## Validation

### ResponseValidator
**Location**: `src/Validation/ResponseValidator.php`  
**Purpose**: Validates API responses

---

## Support Classes

### DatabaseHelper
**Location**: `src/Support/DatabaseHelper.php`  
**Purpose**: Database connection helper

### HealthCheck
**Location**: `src/Support/HealthCheck.php`  
**Purpose**: Service health checking

### MetricsCollector
**Location**: `src/Metrics/MetricsCollector.php`  
**Purpose**: Collects performance metrics

### CookieJarManager
**Location**: `src/Cookies/CookieJarManager.php`  
**Purpose**: Cookie jar management

### ConfigParser
**Location**: `src/Config/ConfigParser.php`  
**Purpose**: Parses configuration arrays

---

## Models

### ClientRequestLog
**Location**: `src/Models/ClientRequestLog.php`  
**Extends**: `Illuminate\Database\Eloquent\Model`  
**Purpose**: Eloquent model for request logs

---

## Repositories

### ClientRequestLogRepository
**Location**: `src/Repositories/ClientRequestLogRepository.php`  
**Purpose**: Persists request logs

---

## Providers

### JooclientServiceProvider
**Location**: `src/Providers/JooclientServiceProvider.php`  
**Extends**: `Illuminate\Support\ServiceProvider`  
**Purpose**: Laravel service provider

---

## Console Commands

### InstallCommand
**Location**: `src/Console/Commands/InstallCommand.php`  
**Extends**: `Illuminate\Console\Command`  
**Purpose**: Installation command

### LogsCommand
**Location**: `src/Console/Commands/LogsCommand.php`  
**Extends**: `Illuminate\Console\Command`  
**Purpose**: View logs command

### PruneCommand
**Location**: `src/Console/Commands/PruneCommand.php`  
**Extends**: `Illuminate\Console\Command`  
**Purpose**: Prune old logs command

### StatsCommand
**Location**: `src/Console/Commands/StatsCommand.php`  
**Extends**: `Illuminate\Console\Command`  
**Purpose**: Statistics command

---

## Exceptions

### Base Exception Classes

#### HttpException
**Location**: `src/Exceptions/Http/HttpException.php`  
**Type**: Abstract class  
**Extends**: `RuntimeException`  
**Purpose**: Base HTTP exception

#### RedisException
**Location**: `src/Exceptions/Redis/RedisException.php`  
**Type**: Abstract class  
**Extends**: `RuntimeException`  
**Purpose**: Base Redis exception

#### MonologException
**Location**: `src/Exceptions/Monolog/MonologException.php`  
**Type**: Abstract class  
**Extends**: `RuntimeException`  
**Purpose**: Base Monolog exception

#### LogDirectoryException
**Location**: `src/Exceptions/Health/LogDirectoryException.php`  
**Type**: Abstract class  
**Extends**: `RuntimeException`  
**Purpose**: Base log directory exception

### Specific Exceptions

- `ClientException` - HTTP client errors (4xx)
- `ServerException` - HTTP server errors (5xx)
- `ConnectionException` - Connection errors
- `TimeoutException` - Request timeout
- `RateLimitExceededException` - Rate limit exceeded
- `CircuitBreakerOpenException` - Circuit breaker open
- `ValidationException` - Response validation failed
- `InvalidClientConfigurationException` - Invalid configuration
- And 20+ more domain-specific exceptions

---

## Constants

### LoggingConstants
**Location**: `src/Constants/LoggingConstants.php`  
**Purpose**: Logging-related constants

---

## Summary

- **Total Classes**: ~100+ classes
- **Interfaces**: 8+ interfaces
- **Traits**: 2 traits
- **Abstract Classes**: 4 abstract exception classes
- **Largest Classes**: Factory (956 lines), Client (533 lines)
- **Most Complex Module**: Logging system (20+ files)
