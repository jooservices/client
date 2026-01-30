# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-01-30

### Added
- **Core**: Implementation of `HttpClient` with strict type safety using `ClientConfig`.
- **Builder**: Fluent `ClientBuilder` for easy instantiation and configuration.
- **Async**: Support for non-blocking requests (`getAsync`, `postAsync`) and concurrent **Batch Processing** with key preservation.
- **Middleware**: robust middleware pipeline including:
    - `RetryMiddleware`: Exponential backoff with jitter (Decorrelated Jitter).
    - `CircuitBreakerMiddleware`: State-aware failure handling (Closed/Open/Half-Open).
    - `CacheMiddleware`: PSR-16 integration with `MemoryCache` and `FilesystemCache`.
    - `LoggingMiddleware`: PSR-3 integration with flexible log levels and body logging.
    - `UserAgentMiddleware` & `CorrelationIdMiddleware`.
- **Adapters**: `GuzzleHttpClientAdapter` separating transport logic from core business rules.
- **Resilience**: Configurable `RetryConfig` and `CircuitBreakerConfig` value objects.
- **Tests**: Comprehensive Test Suite (100% Core coverage) including Unit, Feature/Integration, and Benchmark tests (0.01ms overhead).

### Changed
- Refactored `ClientBuilder` tests to verify configuration state directly.
- Optimized `HttpClient::batch` to properly handle various input types (`RequestInterface` objects, Callables, Promises).
