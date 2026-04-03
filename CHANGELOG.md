# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-04-03

### Added
- **CI/CD**: Split pipeline into focused workflows: `ci.yml`, `release.yml`, `semantic-pr.yml`, `pr-labeler.yml`, `secret-scanning.yml`, and `scorecard.yml`.
- **Quality Tooling**: Added CaptainHook, PHP-CS-Fixer, Gitleaks, phpDocumentor config, Sonar config, and Scrutinizer config.
- **AI Workflow**: Added repository AI scaffolding and guidance files (`AGENTS.md`, `CLAUDE.md`, `ai/skills`, `.claude/commands`, `.cursor/rules`).
- **Coverage**: Extended unit tests to fully cover `LoggingMiddleware` and `InMemoryStateStore` recovery branches.

### Changed
- **Version**: Updated package version to `1.2.0` in `composer.json`.
- **Docs**: Reorganized documentation into DTO-aligned indexed structure (`00-architecture` to `04-development`) and refreshed internal cross-links.
- **Composer Scripts**: Standardized lint/quality script matrix (`pint`, `phpcs`, `phpmd`, `phpstan`, `lint`, `quality`, hook install).
- **Static Analysis**: Enabled baseline include in `phpstan.neon` and tightened cache date parsing behavior in `FilesystemCache`.

### Removed
- **Legacy Docs Layout**: Removed deprecated documentation trees superseded by indexed structure.

## [1.1.0] - 2026-03-10

### Added
- **Logging**: Automatic IP metadata in request logs (`local_ip`, `target_ip`, `target_hostname`, `wan_ip`) when logging is enabled via `ClientBuilder::withLogger()`.
- **Quality**: Coverage gate script (`scripts/coverage-check.php`) enforcing 98% minimum coverage in CI.
- **Tests**: PHPUnit test groups (`unit`, `integration`, `arch`, `feature`) for targeted test runs.

### Changed
- **Tests**: Migrated test suite from Pest to PHPUnit 12; all tests converted to PHPUnit style.
- **Docs**: Documentation updated for PHPUnit, version 1.1.0, and PHP 8.5 requirement.
- **Quality**: `composer test` now runs PHPUnit and the coverage gate.

### Removed
- **Tests**: Removed Pest dependency and `tests/Pest.php`; consolidated bootstrap in `tests/TestCase.php`.

---

## [1.0.0] - 2026-03-08

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
- **Logging**: MongoDB logger with payload trimming/redaction and optional custom writer.
- **Adapters**: `GuzzleHttpClientAdapter` separating transport logic from core business rules.
- **Resilience**: Configurable `RetryConfig` and `CircuitBreakerConfig` value objects.
- **Tests**: Comprehensive Test Suite (100% Core coverage) including Unit, Feature/Integration, and Benchmark tests (0.01ms overhead).

### Fixed
- **CRITICAL**: Fixed `GuzzleHttpClientAdapter::handleConnectException()` return type from `void` to `never` to prevent fatal errors
- **SECURITY**: Replaced unsafe `unserialize()` with secure JSON encoding in `FilesystemCache` (CVE-2026-XXXX, CVSS 9.1/10)
- **SECURITY**: Upgraded hash algorithm from SHA1 to SHA256 in cache key generation
- **Feature**: Completed CircuitBreaker half-open recovery logic - circuit now properly transitions to closed state
- **State**: Added automatic circuit reopening on failure during half-open state in `InMemoryStateStore`
- **Code Quality**: Removed 2 unused methods and 1 unused property from `InMemoryStateStore`
- **Code Quality**: Removed unused import from `HttpClient`
- **Code Quality**: Removed unused `windowDurationMs` property from `CircuitBreakerConfig`
- **Documentation**: Added PHPDoc to all exception classes

### Changed
- Refactored `ClientBuilder` tests to verify configuration state directly
- Optimized `HttpClient::batch` to properly handle various input types (`RequestInterface` objects, Callables, Promises)
- Improved exception handling in `FilesystemCache` with proper JSON error handling
- Updated `StateStoreInterface` to remove unused method signatures
- **Documentation**: Reorganized docs tree and refreshed user-facing entry points.

### Tests
- Added 14 comprehensive unit tests for `OptionsMerger` (100% coverage)
- Added 5 async method tests for POST/PUT/PATCH/DELETE operations
- Added 8 middleware ordering integration tests
- Updated `InMemoryStateStore` tests to use public interface instead of internal state
- Added unit and integration coverage for MongoDB logging
- All 142 tests passing (100% success rate)
