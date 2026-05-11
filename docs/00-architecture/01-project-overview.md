# Project Overview

## Purpose

Provides a technical overview of the JOOservices HTTP Client repository structure, technology stack, and major components.

## Audience

Developers, architects, and technical contributors.

---

## Repository Identity

**Name**: jooservices/client  
**Type**: PHP Library (Composer package)  
**Version**: 1.3.0  
**License**: MIT  
**PHP Requirement**: ^8.5  

**Evidence**: `composer.json`

---

## Repository Structure

```
/Users/vietvu/Sites/JOOservices/client/
├── src/                    # Library source code
│   ├── Client/             # Core HTTP client
│   ├── Middleware/         # Middleware implementations
│   ├── Contracts/          # interfaces and contracts
│   ├── Adapters/           # Transport adapters (Guzzle)
│   ├── Resilience/         # Retry, circuit breaker configs
│   ├── Cache/              # PSR-16 cache implementations
│   ├── Logging/            # PSR-3 logging implementations
│   ├── Response/           # Response wrapper
│   ├── ValueObjects/       # Configuration value objects
│   ├── Exceptions/         # Custom exceptions
│   ├── Support/            # Helper classes
│   └── Models/             # MongoDB models
├── tests/                  # Test suite
│   ├── Unit/               # Unit tests
│   ├── Feature/            # Integration/feature tests
│   └── Benchmark/          # Performance benchmarks
├── docs/                   # Documentation
├── scripts/                # Build/development scripts
├── .github/workflows/      # CI/CD pipelines
└── [config files]          # Quality tool configs
```

**Evidence**: Directory listing, file search results

---

## Technology Stack

### Core Dependencies

| Dependency | Version | Purpose | Evidence |
|------------|---------|---------|----------|
| PHP | ^8.5 | Runtime | `composer.json:require` |
| guzzlehttp/guzzle | ^7.9 | HTTP transport | `composer.json:require` |
| monolog/monolog | ^3.10 | PSR-3 logging | `composer.json:require` |
| mongodb/laravel-mongodb | ^5.6 | MongoDB integration | `composer.json:require` |
| jooservices/dto | ^1.0 | Data transfer objects | `composer.json:require` |
| psr/log | ^3.0 | Logging interface | `composer.json:require` |
| psr/simple-cache | ^3.0 | Caching interface | `composer.json:require` |
| symfony/options-resolver | ^7.0 | Options handling | `composer.json:require` |

### Development Dependencies

| Tool | Version | Purpose |
|------|---------|---------|
| phpunit/phpunit | ^12.0 | Testing framework |
| phpstan/phpstan | ^1.12 | Static analysis (level 9) |
| laravel/pint | ^1.18 | Code style fixer (PSR-12) |
| phpbench/phpbench | ^1.4 | Performance benchmarking |
| phpmd/phpmd | ^2.15 | Mess detector |
| squizlabs/php_codesniffer | ^3.10 | Code sniffer |

**Evidence**: `composer.json:require-dev`

---

## Major Components

###1. Client Layer

**Location**: `src/Client/`

**Components**:
- `ClientBuilder`: Fluent builder for constructing HTTP clients
- `HttpClient`: Main client implementation with sync/async support

**Responsibility**: Provide user-facing API for making HTTP requests

**Key Interfaces Implemented**:
- `HttpClientInterface`: Standard HTTP methods (GET, POST, etc.)
- `AsyncHttpClientInterface`: Async request methods

**Evidence**: `src/Client/ClientBuilder.php`, `src/Client/HttpClient.php`

### 2. Contracts Layer

**Location**: `src/Contracts/`

**Interfaces**:
- `HttpClientInterface`: HTTP client contract
- `AsyncHttpClientInterface`: Async HTTP operations
- `MiddlewareInterface`: Middleware signature
- `TransportAdapterInterface`: Transport abstraction
- `ResponseWrapperInterface`: Response wrapper contract

**Responsibility**: Define contracts for implementation flexibility and testing

**Evidence**: Files in `src/Contracts/`

### 3. Adapter Layer

**Location**: `src/Adapters/Guzzle/`

**Component**: `GuzzleHttpClientAdapter`

**Responsibility**: Adapt Guzzle HTTP client to `TransportAdapterInterface`, isolating Guzzle-specific code

**Evidence**: `src/Adapters/Guzzle/GuzzleHttpClientAdapter.php`

### 4. Middleware Layer

**Location**: `src/Middleware/`

**Components**:
- `MiddlewarePipeline`: Manages middleware registration and execution
- `RetryMiddleware`: Retry failed requests with backoff
- `CircuitBreakerMiddleware`: Circuit breaker pattern
- `CacheMiddleware`: Response caching (PSR-16)
- `LoggingMiddleware`: Request/response logging (PSR-3)
- `CorrelationIdMiddleware`: Add/propagate correlation IDs
- `UserAgentMiddleware`: Set User-Agent header
- `InterceptorMiddleware`: Hook into request/response lifecycle

**Responsibility**: Implement cross-cutting concerns in composable pipeline

**Evidence**: Files in `src/Middleware/`

### 5. Resilience Layer

**Location**: `src/Resilience/`

**Components**:
- `RetryConfig`: Value object for retry configuration
- `CircuitBreakerConfig`: Value object for circuit breaker parameters
- `InMemoryStateStore`: In-memory state persistence for circuit breaker
- `StateStoreInterface`: Contract for state persistence

**Responsibility**: Configure and manage resilience patterns

**Evidence**: Files in `src/Resilience/`

### 6. Cache Layer

**Location**: `src/Cache/`

**Implementations**:
- `MemoryCache`: PSR-16 in-memory cache
- `FilesystemCache`: PSR-16 file-based cache

**Responsibility**: Provide PSR-16 compatible caching implementations

**Evidence**: `src/Cache/MemoryCache.php`, `src/Cache/FilesystemCache.php`

### 7. Logging Layer

**Location**: `src/Logging/`

**Components**:
- `MonologFactory`: Factory for creating Monolog loggers
- `MongoDbLogger`: PSR-3 logger writing to MongoDB

**Responsibility**: Provide logging implementations and factories

**Evidence**: `src/Logging/MonologFactory.php`, `src/Logging/MongoDbLogger.php`

### 8. Models Layer

**Location**: `src/Models/`

**Component**: `ClientRequestLog` (MongoDB Eloquent model)

**Responsibility**: MongoDB document model for storing HTTP request/response logs

**Fields**:
- `level`, `message`, `method`, `uri`, `status`
- `duration_ms`, `correlation_id`
- `request_payload`, `response_payload`, `payload_truncated`
- `context`, `exception`, `logged_at`

**Evidence**: `src/Models/Mongo/ClientRequestLog.php`

**Usage**: Used by `MongoDbLogger` for structured MongoDB logging

### 9. Response Layer

**Location**: `src/Response/`

**Component**: `ResponseWrapper`

**Responsibility**: Wrap PSR-7 responses with convenient methods (json(), status(), header(), toDto())

**Evidence**: `src/Response/ResponseWrapper.php`

### 10. Configuration Layer

**Location**: `src/ValueObjects/`

**Component**: `ClientConfig`

**Responsibility**: Immutable value object holding client configuration (readonly properties)

**Evidence**: `src/ValueObjects/ClientConfig.php`

### 11. Support Utilities

**Location**: `src/Support/`

**Component**: `OptionsMerger`

**Responsibility**: Merge base options with request-specific options, deep-merging headers

**Evidence**: `src/Support/OptionsMerger.php`, used by `HttpClient`

### 12. Exception Layer

**Location**: `src/Exceptions/`

**Components**:
- `ClientException`: Base exception
- `TimeoutException`: Request timeout
- `NetworkConnectionException`: Connection failure
- `JsonDecodingException`: JSON parsing failure
- `InvalidConfigurationException`: Configuration error

**Responsibility**: Type-safe exception hierarchy

**Evidence**: Files in `src/Exceptions/`

---

## Architectural Style

**Inferred**: The architecture follows several patterns:

1. **Layered Architecture**:
   - Clear separation of concerns across layers
   - Dependency direction: User API → Business Logic → Infrastructure

2. **Hexagonal Architecture (Ports and Adapters)**:
   - Core logic isolated from transport (Guzzle adapter)
   - Contracts define ports
   - Adapters implement ports

3. **Builder Pattern**:
   - `ClientBuilder` for fluent object construction

4. **Chain of Responsibility** (Middleware):
   - Middleware pipeline processes requests
   - Each middleware can handle or pass to next

5. **Value Object Pattern**:
   - Immutable configuration objects
   - Type-safe, no primitive obsession

**Evidence**: Directory structure, readonly value objects, interface-based design, adapter pattern

---

## Quality Assurance Infrastructure

### Static Analysis

**Tool**: PHPStan  
**Level**: 9 (maximum)  
**Config**: `phpstan.neon`  
**Command**: `composer analyse`

**Confirmed**: Strictest possible static analysis enabled

### Testing

**Framework**: PHPUnit 12  
**Config**: `phpunit.xml`, `tests/TestCase.php`  
**Commands**:
- `composer test`: Run all tests + coverage gate (98%)
- `composer test:unit`: Unit tests only
- `composer test:integration`: Integration tests
- `composer test:arch`: Architecture tests

**Test Organization**:
- `tests/Unit/`: Isolated unit tests with mocks
- `tests/Feature/`: Feature/integration tests with real components
- `tests/Integration/`: Integration tests
- `tests/Arch/`: Architecture tests
- `tests/Benchmark/`: Performance benchmarks (PHPBench)

**Coverage**: Enforced via `scripts/coverage-check.php` (98% minimum).

### Code Style

**Tool**: Laravel Pint (PSR-12)  
**Config**: `pint.json`  
**Commands**:
- `composer check:cs`: Check code style
- `composer fix:cs`: Auto-fix style issues

### Additional QA Tools

- **PHPMD** (`phpmd.xml`): Mess detection
- **PHPCS** (`phpcs.xml`): Code sniffing
- **PHPBench** (`phpbench.json`): Performance benchmarking

### CI/CD

**Platform**: GitHub Actions  
**Configs**: `.github/workflows/ci.yml`, `.github/workflows/release.yml`, `.github/workflows/semantic-pr.yml`, `.github/workflows/pr-labeler.yml`, `.github/workflows/secret-scanning.yml`, `.github/workflows/scorecard.yml`

**Inferred**: Automated quality checks on push/PR

---

## Development Workflow

### Quality Gate

**Confirmed**: the canonical quality flow is `composer lint:all`, `composer test`, and `composer check`:
1. Validate formatting and structure (`pint`, `phpcs`, `php-cs-fixer`)
2. Run static analysis and maintainability checks (`phpstan`, `phpmd`)
3. Run the standard test suite (`phpunit`)
4. Keep `composer quality` only as a compatibility alias for `composer check`

**Evidence**: `composer.json` scripts section

### Development Scripts

Located in `scripts/`:
- `scripts/git-hooks/pre-commit`: Git pre-commit hook

**Evidence**: Files in `scripts/` directory

---

## External Integrations

### Required

- **MongoDB**: For `MongoDbLogger` (optional feature)
- **Filesystem**: For `FilesystemCache`

### Optional

- None detected beyond MongoDB

**Unknown**: Whether MongoDB integration is production-ready or experimental

---

## Deployment Model

**Type**: Library (Composer package)

**Distribution**: Installed via Composer: `composer require jooservices/client`

**Runtime**: Runs within PHP applications as a dependency

**Not Applicable**:
- No standalone deployment
- No server/daemon process
- No Docker containers required
- No infrastructure provisioning

---

## Version Control and History

**Repository**: Git  
**Primary Branches**: `develop` (integration) and `master` (stable releases)  
**Changelog**: Maintained in `CHANGELOG.md` (Keep a Changelog format)  
**Versioning**: Semantic Versioning 2.0.0

**Version**: 1.3.0 (release branch target as of this document)

---

## Documentation Status

**Current Documentation**:
- ✅ README with quick start
- ✅ CONTRIBUTING guide
- ✅ CHANGELOG
- ✅ 5 working PHP examples in `docs/03-examples/`
- ✅ Basic API reference in `docs/02-user-guide/`
- ⚠️ Limited architecture documentation (being addressed by this initiative)

**This Documentation Initiative**: Comprehensive enterprise-level structured documentation across all concerns

---

## Related Documents

- [Business Context and Goals](business-context-and-goals.md)
- [Feature Inventory](04-modules-and-domains.md)
- [System Architecture](../00-architecture/05-data-flow.md)
- [Runtime and Dependencies](../00-architecture/03-tech-stack.md)
