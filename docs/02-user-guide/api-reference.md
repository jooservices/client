# API and Interface Reference

## Purpose

Complete API documentation for all public classes, methods, and interfaces in JOOservices HTTP Client.

## Audience

Developers integrating the library.

---

## Table of Contents

- [ClientBuilder API](#clientbuilder-api)
- [HttpClient API](#httpclient-api)
- [ResponseWrapper API](#responsewrapper-api)
- [Middleware Interfaces](#middleware-interfaces)
- [Configuration Objects](#configuration-objects)
- [Cache Implementations](#cache-implementations)
- [Logger Implementations](#logger-implementations)
- [Exceptions](#exceptions)

---

## ClientBuilder API

**Namespace**: `JOOservices\Client\Client`

**Purpose**: Fluent API for building configured HTTP clients

**Pattern**: Builder Pattern

---

### Static Methods

#### `create(): static`

**Description**: Start building a new client

**Returns**: New builder instance

**Example**:
```php
$builder = ClientBuilder::create();
```

---

### Configuration Methods

#### `withBaseUri(string $baseUri): static`

**Description**: Set base URI for all requests

**Parameters**:
- `$baseUri` (string): Base URL (e.g., `https://api.example.com`)

**Returns**: Builder instance (fluent)

**Example**:
```php
$builder->withBaseUri('https://api.example.com/v1');
```

---

#### `withTimeout(int $seconds): static`

**Description**: Set total request timeout

**Parameters**:
- `$seconds` (int): Timeout in seconds

**Returns**: Builder instance

**Default**: 30 seconds (Guzzle default)

**Example**:
```php
$builder->withTimeout(10);  // 10 second timeout
```

---

#### `withConnectTimeout(int $seconds): static`

**Description**: Set connection timeout

**Parameters**:
- `$seconds` (int): Connection timeout in seconds

**Returns**: Builder instance

**Default**: No timeout

**Example**:
```php
$builder->withConnectTimeout(3);  // 3 second connect timeout
```

---

#### `withHeader(string $name, string $value): static`

**Description**: Add single header

**Parameters**:
- `$name` (string): Header name
- `$value` (string): Header value

**Returns**: Builder instance

**Example**:
```php
$builder->withHeader('Authorization', 'Bearer ' . $token);
```

---

#### `withHeaders(array $headers): static`

**Description**: Add multiple headers

**Parameters**:
- `$headers` (array): Associative array of headers

**Returns**: Builder instance

**Example**:
```php
$builder->withHeaders([
    'Accept' => 'application/json',
    'X-API-Key' => 'secret',
]);
```

---

#### `withVerifySsl(bool $verify): static`

**Description**: Enable/disable SSL verification

**Parameters**:
- `$verify` (bool): True to verify SSL (default), false to skip

**Returns**: Builder instance

**Warning**: Never disable in production

**Example**:
```php
$builder->withVerifySsl(false);  // Development only!
```

---

#### `withHttpErrors(bool $throw): static`

**Description**: Control whether 4xx/5xx responses throw exceptions

**Parameters**:
- `$throw` (bool): True to throw exceptions (default), false to return responses

**Returns**: Builder instance

**Example**:
```php
$builder->withHttpErrors(false);  // Don't throw on 4xx/5xx
```

---

#### `withOption(string $key, mixed $value): static`

**Description**: Set custom Guzzle option

**Parameters**:
- `$key` (string): Option key (see Guzzle docs)
- `$value` (mixed): Option value

**Returns**: Builder instance

**Example**:
```php
$builder->withOption('allow_redirects', ['max' => 5]);
```

---

### Middleware Methods

#### `withRetry(RetryConfig $config): static`

**Description**: Add retry middleware with exponential backoff

**Parameters**:
- `$config` (RetryConfig): Retry configuration object

**Returns**: Builder instance

**Example**:
```php
use JOOservices\Client\Resilience\RetryConfig;

$builder->withRetry(new RetryConfig(
    maxAttempts: 3,
    baseDelayMs: 100,
    maxDelayMs: 2000
));
```

---

#### `withCircuitBreaker(CircuitBreakerConfig $config, ?StateStoreInterface $store = null): static`

**Description**: Add circuit breaker middleware

**Parameters**:
- `$config` (CircuitBreakerConfig): Circuit breaker configuration
- `$store` (StateStoreInterface|null): Optional custom storage (default: InMemoryStateStore)

**Returns**: Builder instance

**Example**:
```php
use JOOservices\Client\Resilience\CircuitBreakerConfig;

$builder->withCircuitBreaker(new CircuitBreakerConfig(
    failureThreshold: 5,
    recoveryTimeoutMs: 10000
));
```

---

#### `withCache(CacheInterface $cache, int $defaultTtl = 3600): static`

**Description**: Add response caching middleware (GET only)

**Parameters**:
- `$cache` (CacheInterface): PSR-16 cache implementation
- `$defaultTtl` (int): Default time-to-live in seconds

**Returns**: Builder instance

**Example**:
```php
use JOOservices\Client\Cache\FilesystemCache;

$cache = new FilesystemCache(__DIR__ . '/cache');
$builder->withCache($cache, 3600);
```

---

#### `withLogger(LoggerInterface $logger, bool $logBodies = false): static`

**Description**: Add logging middleware

**Parameters**:
- `$logger` (LoggerInterface): PSR-3 logger
- `$logBodies` (bool): Whether to log request/response bodies

**Returns**: Builder instance

**Automatic IP Metadata**:
- Enabling logger automatically collects and logs:
- `local_ip` (LAN/interface IP used for socket)
- `target_ip` (remote IP connected by transport)
- `target_hostname` (request URI host)
- `wan_ip` (public egress IP via cached resolver, fail-open)

**Example**:
```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('api');
$logger->pushHandler(new StreamHandler('php://stdout'));

$builder->withLogger($logger, logBodies: true);
```

---

#### `withDefaultLogging(string $domain, ?string $path = null): static`

**Description**: Add daily rotating file logger (Monolog)

**Parameters**:
- `$domain` (string): Log file prefix
- `$path` (string|null): Log directory (default: `./logs`)

**Returns**: Builder instance

**Example**:
```php
$builder->withDefaultLogging('my-app', __DIR__ . '/logs');
// Creates logs/my-app-2025-01-27.log
```

---

#### `withCorrelationId(string $headerName = 'X-Correlation-ID'): static`

**Description**: Add correlation ID middleware for distributed tracing

**Parameters**:
- `$headerName` (string): Header name (default: `X-Correlation-ID`)

**Returns**: Builder instance

**Example**:
```php
$builder->withCorrelationId('X-Trace-ID');
```

---

#### `withUserAgent(string $userAgent): static`

**Description**: Set custom User-Agent header

**Parameters**:
- `$userAgent` (string): User agent string

**Returns**: Builder instance

**Example**:
```php
$builder->withUserAgent('MyApp/1.0');
```

---

#### `onRequest(callable $callback): static`

**Description**: Add request interceptor

**Parameters**:
- `$callback` (callable): `function($request, $options): array`

**Returns**: Builder instance

**Example**:
```php
$builder->onRequest(function ($request, $options) {
    $options['headers']['X-Timestamp'] = time();
    return [$request, $options];
});
```

---

#### `onResponse(callable $callback): static`

**Description**: Add response interceptor

**Parameters**:
- `$callback` (callable): `function($response): $response`

**Returns**: Builder instance

**Example**:
```php
$builder->onResponse(function ($response) {
    error_log('Status: ' . $response->getStatusCode());
    return $response;
});
```

---

#### `addMiddleware(MiddlewareInterface $middleware, int $priority = 0): static`

**Description**: Add custom middleware

**Parameters**:
- `$middleware` (MiddlewareInterface): Custom middleware implementation
- `$priority` (int): Execution priority (higher = earlier)

**Returns**: Builder instance

**Example**:
```php
$builder->addMiddleware(new CustomMiddleware(), priority: 100);
```

---

### Build Method

#### `build(): HttpClient`

**Description**: Build and return configured HTTP client

**Returns**: HttpClient instance

**Throws**: `InvalidConfigurationException` if configuration invalid

**Example**:
```php
$client = $builder->build();
```

---

## HttpClient API

**Namespace**: `JOOservices\Client\Client`

**Contracts**: `HttpClientInterface`, `AsyncHttpClientInterface`

**Purpose**: Execute HTTP requests with configured middleware

---

### Synchronous Methods

#### `get(string $uri, array $options = []): ResponseWrapperInterface`

**Description**: Execute GET request

**Parameters**:
- `$uri` (string): Request URI (relative to base URI if set)
- `$options` (array): Request options (Guzzle format)

**Returns**: ResponseWrapper instance

**Throws**: `ClientException` subclasses on error

**Example**:
```php
$response = $client->get('/users/1');
$response = $client->get('/search', ['query' => ['q' => 'php']]);
```

---

#### `post(string $uri, array $options = []): ResponseWrapperInterface`

**Description**: Execute POST request

**Parameters**:
- `$uri` (string): Request URI
- `$options` (array): Request options

**Returns**: ResponseWrapper instance

**Example**:
```php
$response = $client->post('/users', [
    'json' => ['name' => 'John', 'email' => 'john@example.com']
]);
```

---

#### `put(string $uri, array $options = []): ResponseWrapperInterface`

**Description**: Execute PUT request

**Parameters**:
- `$uri` (string): Request URI
- `$options` (array): Request options

**Returns**: ResponseWrapper instance

**Example**:
```php
$response = $client->put('/users/1', [
    'json' => ['name' => 'Jane']
]);
```

---

#### `patch(string $uri, array $options = []): ResponseWrapperInterface`

**Description**: Execute PATCH request

**Parameters**:
- `$uri` (string): Request URI
- `$options` (array): Request options

**Returns**: ResponseWrapper instance

**Example**:
```php
$response = $client->patch('/users/1', [
    'json' => ['email' => 'new@example.com']
]);
```

---

#### `delete(string $uri, array $options = []): ResponseWrapperInterface`

**Description**: Execute DELETE request

**Parameters**:
- `$uri` (string): Request URI
- `$options` (array): Request options

**Returns**: ResponseWrapper instance

**Example**:
```php
$response = $client->delete('/users/1');
```

---

#### `request(string $method, string $uri, array $options = []): ResponseWrapperInterface`

**Description**: Execute request with custom HTTP method

**Parameters**:
- `$method` (string): HTTP method (GET, POST, etc.)
- `$uri` (string): Request URI
- `$options` (array): Request options

**Returns**: ResponseWrapper instance

**Example**:
```php
$response = $client->request('HEAD', '/users/1');
```

---

### Asynchronous Methods

#### `getAsync(string $uri, array $options = []): PromiseInterface`

**Description**: Execute async GET request

**Parameters**:
- `$uri` (string): Request URI
- `$options` (array): Request options

**Returns**: Guzzle PromiseInterface

**Example**:
```php
$promise = $client->getAsync('/users/1');
$response = $promise->wait();  // Block until complete
```

---

#### `postAsync(string $uri, array $options = []): PromiseInterface`

**Description**: Execute async POST request

**Parameters**:
- `$uri` (string): Request URI
- `$options` (array): Request options

**Returns**: PromiseInterface

**Example**:
```php
$promise = $client->postAsync('/users', ['json' => $data]);
$promise->then(function ($response) {
    echo "Success!";
});
```

---

#### `requestAsync(string $method, string $uri, array $ = []): PromiseInterface`

**Description**: Execute async request with custom method

**Parameters**:
- `$method` (string): HTTP method
- `$uri` (string): Request URI
- `$options` (array): Request options

**Returns**: PromiseInterface

**Example**:
```php
$promise = $client->requestAsync('PATCH', '/users/1', $options);
```

---

### Batch Processing

#### `batch(iterable $requests, int $concurrency = 25): array`

**Description**: Execute multiple requests in parallel

**Parameters**:
- `$requests` (iterable): Array/generator of callables returning promises
- `$concurrency` (int): Max simultaneous requests

**Returns**: Array of responses keyed by request keys

**Throws**: Exceptions from individual requests propagated

**Example**:
```php
$results = $client->batch([
    'user1' => fn() => $client->getAsync('/users/1'),
    'user2' => fn() => $client->getAsync('/users/2'),
    'user3' => fn() => $client->getAsync('/users/3'),
], concurrency: 10);

echo $results['user1']->json()['name'];
```

---

## ResponseWrapper API

**Namespace**: `JOOservices\Client\Response`

**Contract**: `ResponseWrapperInterface`

**Purpose**: Convenient wrapper around PSR-7 ResponseInterface

---

### Methods

#### `status(): int`

**Description**: Get HTTP status code

**Returns**: Status code (100-599)

**Example**:
```php
if ($response->status() === 200) {
    echo "Success!";
}
```

---

#### `json(): ?array`

**Description**: Decode JSON response body

**Returns**: Decoded array, or null if not JSON

**Throws**: `JsonDecodingException` if invalid JSON

**Example**:
```php
$data = $response->json();
echo $data['name'];
```

---

#### `header(string $name): ?string`

**Description**: Get response header value

**Parameters**:
- `$name` (string): Header name (case-insensitive)

**Returns**: Header value, or null if not present

**Example**:
```php
$contentType = $response->header('Content-Type');
$rateLimit = $response->header('X-RateLimit-Remaining');
```

---

#### `toDto(string $className): object`

**Description**: Convert JSON response to Data Transfer Object

**Parameters**:
- `$className` (string): Fully-qualified class name

**Returns**: Instance of `$className` populated from JSON

**Requires**: DTO constructor accepting array

**Example**:
```php
class UserDto {
    public function __construct(
        public readonly int $id,
        public readonly string $name
    ) {}
}

$user = $response->toDto(UserDto::class);
echo $user->name;
```

---

#### `toPsrResponse(): ResponseInterface`

**Description**: Get underlying PSR-7 response

**Returns**: PSR-7 ResponseInterface

**Example**:
```php
$psrResponse = $response->toPsrResponse();
$body = $psrResponse->getBody()->getContents();
```

---

## Middleware Interfaces

### MiddlewareInterface

**Namespace**: `JOOservices\Client\Contracts`

**Purpose**: Contract for custom middleware

**Method**:
```php
public function __invoke(callable $handler): callable;
```

**Example Implementation**:
```php
class CustomMiddleware implements MiddlewareInterface
{
    public function __invoke(callable $handler): callable
    {
        return function ($request, $options) use ($handler) {
            // Pre-request logic
            $options['headers']['X-Custom'] = 'value';
            
            $promise = $handler($request, $options);
            
            // Post-response logic
            return $promise->then(function ($response) {
                // Modify response if needed
                return $response;
            });
        };
    }
}
```

---

## Configuration Objects

### RetryConfig

**Namespace**: `JOOservices\Client\Resilience`

**Constructor**:
```php
public function __construct(
    public readonly int $maxAttempts = 3,
    public readonly int $baseDelayMs = 100,
    public readonly int $maxDelayMs = 2000,
    public readonly bool $useJitter = true,
    public readonly array $retryableStatuses = [429, 500, 502, 503, 504],
    public readonly array $retryableMethods = ['GET', 'HEAD', 'OPTIONS', 'PUT', 'DELETE'],
    public readonly array $retryableExceptions = [
        NetworkConnectionException::class,
        TimeoutException::class,
    ]
)
```

**Example**:
```php
$config = new RetryConfig(
    maxAttempts: 5,
    baseDelayMs: 200,
    maxDelayMs: 5000,
    useJitter: true
);
```

---

### CircuitBreakerConfig

**Namespace**: `JOOservices\Client\Resilience`

**Constructor**:
```php
public function __construct(
    public readonly int $failureThreshold = 5,
    public readonly int $recoveryTimeoutMs = 10000,
    public readonly int $successThreshold = 2,
)
```

**Example**:
```php
$config = new CircuitBreakerConfig(
    failureThreshold: 10,
    recoveryTimeoutMs: 30000,
    successThreshold: 3
);
```

---

### ClientConfig

**Namespace**: `JOOservices\Client\ValueObjects`

**Constructor**:
```php
public function __construct(
    public readonly string $baseUri = '',
    public readonly int $timeout = 30,
    public readonly int $connectTimeout = 0,
    public readonly array $headers = [],
    public readonly bool $verifySsl = true,
    public readonly bool $httpErrors = true,
    public readonly array $options = [],
)
```

**Example**:
```php
$config = new ClientConfig(
    baseUri: 'https://api.example.com',
    timeout: 10,
    headers: ['Accept' => 'application/json']
);
```

---

## Cache Implementations

### MemoryCache

**Namespace**: `JOOservices\Client\Cache`

**Contract**: PSR-16 CacheInterface

**Purpose**: In-memory cache (per-request)

**Constructor**:
```php
public function __construct()
```

**Methods**: Standard PSR-16 methods

**Example**:
```php
use JOOservices\Client\Cache\MemoryCache;

$cache = new MemoryCache();
$cache->set('key', 'value', ttl: 60);
$value = $cache->get('key');  // 'value'
```

---

### FilesystemCache

**Namespace**: `JOOservices\Client\Cache`

**Contract**: PSR-16 CacheInterface

**Purpose**: File-based persistent cache

**Constructor**:
```php
public function __construct(
    private readonly string $directory
)
```

**Methods**: Standard PSR-16 methods

**Example**:
```php
use JOOservices\Client\Cache\FilesystemCache;

$cache = new FilesystemCache(__DIR__ . '/cache');
$cache->set('user:1', $userData, ttl: 3600);
```

---

## Logger Implementations

### MonologFactory

**Namespace**: `JOOservices\Client\Logging`

**Purpose**: Create pre-configured Monolog loggers

**Method**:
```php
public static function createDaily(
    string $domain,
    ?string $path = null
): \Monolog\Logger
```

**Example**:
```php
use JOOservices\Client\Logging\MonologFactory;

$logger = MonologFactory::createDaily('api', __DIR__ . '/logs');
// Creates logs/api-2025-01-27.log
```

---

### MongoDbLogger

**Namespace**: `JOOservices\Client\Logging`

**Contract**: PSR-3 LoggerInterface

**Purpose**: Log to MongoDB collection

**Constructor**:
```php
public function __construct(
    private readonly string $connection = 'mongodb',
    private readonly string $collection = 'client_request_logs',
    private readonly int $maxRequestBodyBytes = 4096,
    private readonly int $maxResponseBodyBytes = 8192,
    private readonly array $redactKeys = ['authorization', 'cookie', 'set-cookie', 'token']
)
```

**Example**:
```php
use JOOservices\Client\Logging\MongoDbLogger;

$logger = new MongoDbLogger(
    connection: 'mongodb',
    collection: 'http_logs'
);

$client = ClientBuilder::create()
    ->withLogger($logger)
    ->build();
```

---

## Exceptions

### ClientException (Base)

**Namespace**: `JOOservices\Client\Exceptions`

**Extends**: `RuntimeException`

**Purpose**: Base exception for all library exceptions

**Usage**: Catch this to handle all library errors

**Example**:
```php
use JOOservices\Client\Exceptions\ClientException;

try {
    $response = $client->get('/users');
} catch (ClientException $e) {
    echo "HTTP error: " . $e->getMessage();
}
```

---

### InvalidConfigurationException

**Namespace**: `JOOservices\Client\Exceptions`

**Extends**: `ClientException`

**Purpose**: Thrown when configuration is invalid

**Example**:
```php
// Thrown if base URI invalid, timeout negative, etc.
```

---

### JsonDecodingException

**Namespace**: `JOOservices\Client\Exceptions`

**Extends**: `ClientException`

**Purpose**: Thrown when JSON decoding fails

**Example**:
```php
try {
    $data = $response->json();
} catch (JsonDecodingException $e) {
    echo "Invalid JSON: " . $e->getMessage();
}
```

---

### NetworkConnectionException

**Namespace**: `JOOservices\Client\Exceptions`

**Extends**: `ClientException`

**Purpose**: Thrown on network/connectivity failures

**Example**:
```php
try {
    $response = $client->get('/api');
} catch (NetworkConnectionException $e) {
    echo "Network error: " . $e->getMessage();
}
```

---

### TimeoutException

**Namespace**: `JOOservices\Client\Exceptions`

**Extends**: `ClientException`

**Purpose**: Thrown when request times out

**Example**:
```php
try {
    $response = $client->get('/slow-endpoint');
} catch (TimeoutException $e) {
    echo "Request timed out after " . $e->getMessage();
}
```

---

## Related Documents

- [Feature Inventory](../00-architecture/04-modules-and-domains.md)
- [System Architecture](../00-architecture/05-data-flow.md)
- [Local Setup and Installation](../01-getting-started/installation.md)
- [Examples](../03-examples/)
