# JOOClient Class Reference

Complete reference for all classes in the JOOClient package.

## Core Classes

### Jooclient
**Location**: `src/Jooclient.php`
**Type**: Entry point / Static factory
**Purpose**: Convert Laravel configuration to configured Factory

**Key Method**:
- `fromConfig(array $config): Factory` - Build Factory from config

**Usage**:
```php
$factory = Jooclient::fromConfig(config('jooclient'));
```

---

### Factory
**Location**: `src/Factory/Factory.php`
**Type**: Immutable builder
**Purpose**: Build configured Guzzle clients with middleware

**Highlights**:
- Provides a fluent, immutable API for building preconfigured `GuzzleHttp\Client` instances.
- Supports retries, rich logging options (MySQL, MongoDB, Monolog, and the multi-logger aggregator), caching, custom middleware, and request history capture for tests.
- Follows the Immutable Builder pattern—every mutator returns a cloned instance to avoid side effects.
- Designed to be thread-safe and allow reuse of base configurations via method chaining.
- Exposes helpers such as `getHistory()` to inspect mocked requests and `make()` to produce a `Client` wrapper with the configured logger.

**Key Methods**:
- `addOptions(array $options): self` - Add Guzzle options
- `enableRetries(int $max, int $delay, int $minCode): self` - Enable retry logic
- `enableDbLogging(...): self` - Enable MySQL logging
- `enableMongoDbLogging(...): self` - Enable MongoDB logging
- `enableLogging(LoggerInterface $logger): self` - Enable PSR-3 logging
- `enableCache(callable $middleware): self` - Add cache middleware
- `addMiddleware(callable $mw, string $name): self` - Add custom middleware
- `fakeResponses(array $responses): self` - Mock responses for testing
- `make(): Client` - Create the client

**Usage**:
```php
$factory = (new Factory())
    ->addOptions(['timeout' => 30])
    ->enableRetries(3, 1, 500)
    ->enableDbLogging('127.0.0.1', 3306, 'logs');

$result = $factory->make();
```

**Testing with Mocks**:
```php
$factory = (new Factory())
    ->fakeResponses([new Response(200, [], 'OK')])
    ->enableLogging(['logging' => ['enabled' => true, 'driver' => 'mysql']]);

$result = $factory->make();
$result->get('/test'); // Uses mocked response
$history = $result->getHistory();
```

---

### Client
**Location**: `src/Factory/Client.php`
**Type**: Client wrapper
**Purpose**: Encapsulate client + logger + factory

**Properties**:
- `logger`: Logger instance (or null) - public readonly

**Note**: The `client` and `factory` properties are now private to maintain encapsulation. Use the public methods instead.

**Methods**:
- `getLogger(): ?LoggerInterface` - Get logger
- `flushLogger(): void` - Flush buffered logs
- `getGuzzleClient(): GuzzleClient` - Get underlying Guzzle client (for testing/internal use)
- `getFactory(): Factory` - Get factory instance (for internal use)
- `getHistory(): array` - Get request history (for testing)
- `get(string|UriInterface $uri, array $options = []): ResponseWrapper` - GET request
- `post(string|UriInterface $uri, array $options = []): ResponseWrapper` - POST request
- `request(string $method, string|UriInterface $uri, array $options = []): ResponseWrapper` - Generic request

---

## Configuration Classes

### ConfigParser
**Location**: `src/Config/ConfigParser.php`
**Purpose**: Parse config arrays to typed objects

**Methods**:
- `parseRetriesConfig(array $config): ?RetriesConfig`
- `parseDatabaseConfig(array $config): ?DatabaseConnectionConfig`
- `parseMongoDbConfig(array $config): ?MongoDbConfig`
- `isLoggingEnabled(array $config): bool`
- `getLoggingDriver(array $config): string`

---

### DatabaseConnectionConfig
**Location**: `src/Logging/Config/DatabaseConnectionConfig.php`
**Type**: Value object (immutable)
**Purpose**: MySQL connection configuration

**Properties**:
- `host`, `port`, `database`, `username`, `password`
- `table`, `batch`, `fallback`

**Factory Method**:
- `fromArray(array $config): self` - Create from array with validation

---

### MongoDbConfig
**Location**: `src/Logging/Config/MongoDbConfig.php`
**Type**: Value object (immutable)
**Purpose**: MongoDB connection configuration

**Properties**:
- `dsn`, `database`, `collection`
- `batch`, `fallback`, `options`

**Factory Method**:
- `fromArray(array $config): self` - Create from array with validation

---

### RetriesConfig
**Location**: `src/Logging/Config/RetriesConfig.php`
**Type**: Value object (immutable)
**Purpose**: Retry behavior configuration

**Properties**:
- `maxAttempts`, `delaySeconds`, `minErrorCode`
