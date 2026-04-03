# Local Setup and Installation

## Purpose

Guide developers through installing and verifying the JOOservices HTTP Client library.

## Audience

PHP developers integrating the library into their projects.

---

## System Requirements

### PHP Version

**Required**: PHP 8.5 or higher

**Evidence**: `composer.json` specifies `^8.5`

**Confidence**: Confirmed

---

### PHP Extensions

**Required**:
- `ext-json` - JSON encoding/decoding
- `ext-mbstring` - Multibyte string handling (Guzzle dependency)

**Optional**:
- `ext-mongodb` - If using MongoDbLogger
- `ext-zlib` - For gzip response decompression

**Evidence**: Guzzle requirements, optional MongoDB features  
**Confidence**: Inferred from dependencies

---

### Composer

**Required**: Composer 2.x

**Evidence**: Standard PHP dependency management  
**Confidence**: Confirmed

---

## Installation

### Install via Composer

**Command**:
```bash
composer require jooservices/client
```

**Expected Output**:
```
Using version ^1.1 for jooservices/client
./composer.json has been updated
Running composer update jooservices/client
Loading composer repositories with package information
Updating dependencies
Lock file operations: 8 installs, 0 updates, 0 removals
  - Locking jooservices/client (1.1.0)
  - Locking guzzlehttp/guzzle (7.9.x-dev)
  - Locking monolog/monolog (3.10.x-dev)
  ...
Writing lock file
Installing dependencies from lock file
Package operations: 8 installs, 0 updates, 0 removals
  ...
Generating autoload files
```

**Evidence**: Package name from `composer.json`  
**Confidence**: Confirmed

---

### Verify Installation

**Check Installed Version**:
```bash
composer show jooservices/client
```

**Expected Output**:
```
name     : jooservices/client
descrip. : A robust, layered HTTP Client wrapper for JOOservices
versions : * 1.1.0
type     : library
...
```

**Test Autoloading**:
```php
<?php

require __DIR__ . '/vendor/autoload.php';

use JOOservices\Client\Client\ClientBuilder;

echo "JOOservices Client installed successfully!\n";
```

**Confidence**: Standard verification steps

---

## Quick Start

### Basic GET Request

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withTimeout(5)
    ->build();

$response = $client->get('/posts/1');

echo "Status: " . $response->status() . "\n";
echo "Body: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
```

**Expected Output**:
```
Status: 200
Body: {
    "userId": 1,
    "id": 1,
    "title": "...",
    "body": "..."
}
```

**Evidence**: Example pattern from README  
**Confidence**: Inferred from README examples

---

### Basic POST Request

```php
<?php

use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://jsonplaceholder.typicode.com')
    ->withHeader('Content-Type', 'application/json')
    ->build();

$response = $client->post('/posts', [
    'json' => [
        'title' => 'foo',
        'body' => 'bar',
        'userId' => 1
    ]
]);

echo "Status: " . $response->status() . "\n";
echo "Created: " . json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
```

**Confidence**: Standard HTTP client usage

---

## Configuration Examples

### Timeouts

```php
$client = ClientBuilder::create()
    ->withTimeout(10)              // Total request timeout (seconds)
    ->withConnectTimeout(3)        // Connection timeout (seconds)
    ->build();
```

**Evidence**: `ClientBuilder` methods  
**Confidence**: Confirmed

---

### SSL/TLS Configuration

```php
// Disable SSL verification (NOT recommended for production)
$client = ClientBuilder::create()
    ->withVerifySsl(false)
    ->build();

// Enable SSL verification (default)
$client = ClientBuilder::create()
    ->withVerifySsl(true)
    ->build();
```

**Warning**: Never disable SSL verification in production.

**Evidence**: `ClientBuilder::withVerifySsl()`  
**Confidence**: Confirmed

---

### Custom Headers

```php
$client = ClientBuilder::create()
    ->withHeader('Authorization', 'Bearer ' . $token)
    ->withHeader('Accept', 'application/json')
    ->withHeaders([
        'X-Custom-1' => 'value1',
        'X-Custom-2' => 'value2',
    ])
    ->build();
```

**Evidence**: `ClientBuilder` header methods  
**Confidence**: Confirmed

---

### Base URI

```php
$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com/v1')
    ->build();

// Requests are relative to base URI
$response = $client->get('/users');  // → https://api.example.com/v1/users
```

**Evidence**: `ClientBuilder::withBaseUri()`  
**Confidence**: Confirmed

---

## Adding Resilience Features

### Retry with Exponential Backoff

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Resilience\RetryConfig;

$client = ClientBuilder::create()
    ->withRetry(new RetryConfig(
        maxAttempts: 3,
        baseDelayMs: 100,
        maxDelayMs: 2000,
        useJitter: true
    ))
    ->build();
```

**Behavior**:
- Retries on 429, 500, 502, 503, 504 status codes
- Exponential backoff: 100ms → 200ms → 400ms
- Jitter randomizes delay ± 50%

**Evidence**: `RetryConfig` class  
**Confidence**: Confirmed

---

### Circuit Breaker

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Resilience\CircuitBreakerConfig;

$client = ClientBuilder::create()
    ->withCircuitBreaker(new CircuitBreakerConfig(
        failureThreshold: 5,           // Open after 5 failures
        recoveryTimeoutMs: 10000,      // Wait 10s before half-open
        successThreshold: 2            // Close after 2 successes
    ))
    ->build();
```

**Behavior**:
- **Closed**: Normal operation
- **Open**: Fails fast (throws exception immediately)
- **Half-Open**: Tests if service recovered

**Evidence**: `CircuitBreakerConfig` class  
**Confidence**: Confirmed

---

### Response Caching

```php
use JOOservices\Client\Cache\FilesystemCache;
use JOOservices\Client\Client\ClientBuilder;

$cache = new FilesystemCache(__DIR__ . '/http-cache');

$client = ClientBuilder::create()
    ->withCache($cache, defaultTtl: 3600)  // 1 hour
    ->build();

// First request hits API
$response1 = $client->get('/users/1');

// Second request returns cached response
$response2 = $client->get('/users/1');
```

**Notes**:
- Only caches GET requests
- Per-request TTL via `['cache_ttl' => 600]` option

**Evidence**: `CacheMiddleware` implementation  
**Confidence**: Confirmed

---

### Logging

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use JOOservices\Client\Client\ClientBuilder;

$logger = new Logger('api-client');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

$client = ClientBuilder::create()
    ->withLogger($logger, logBodies: true)
    ->build();
```

**Logged Data**:
- HTTP method, URI, status
- Duration
- Headers (redacted: authorization, cookie, token)
- Bodies (if `logBodies: true`)

**Evidence**: `LoggingMiddleware` class  
**Confidence**: Confirmed

---

### Correlation IDs

```php
$client = ClientBuilder::create()
    ->withCorrelationId('X-Trace-ID')  // Custom header name
    ->build();

// Automatically adds X-Trace-ID header with UUID
$response = $client->get('/users');
```

**Behavior**:
- Generates UUID v4 if not present in options
- Propagates existing correlation ID
- Useful for distributed tracing

**Evidence**: `CorrelationIdMiddleware` class  
**Confidence**: Confirmed

---

## Advanced Usage

### Async Requests

```php
$promise = $client->getAsync('/users/1');

// Chain callbacks
$promise->then(
    function ($response) {
        echo "Success: " . $response->status() . "\n";
    },
    function ($exception) {
        echo "Error: " . $exception->getMessage() . "\n";
    }
);

// Or wait for result
$response = $promise->wait();
```

**Evidence**: `AsyncHttpClientInterface` implementation  
**Confidence**: Confirmed

---

### Batch Processing

```php
$results = $client->batch([
    'user1' => fn() => $client->getAsync('/users/1'),
    'user2' => fn() => $client->getAsync('/users/2'),
    'user3' => fn() => $client->getAsync('/users/3'),
], concurrency: 10);

echo $results['user1']->json()['name'];
echo $results['user2']->json()['name'];
```

**Benefits**:
- Parallel execution (up to concurrency limit)
- Faster than sequential requests
- Returns all results indexed by key

**Evidence**: `HttpClient::batch()` method  
**Confidence**: Confirmed

---

### Request/Response Interceptors

```php
$client = ClientBuilder::create()
    ->onRequest(function ($request, $options) {
        // Modify request
        $options['headers']['X-Timestamp'] = time();
        return [$request, $options];
    })
    ->onResponse(function ($response) {
        // Process response
        error_log('Received: ' . $response->getStatusCode());
        return $response;
    })
    ->build();
```

**Use Cases**:
- Custom authentication
- Request signing
- Response transformation
- Debugging

**Evidence**: `InterceptorMiddleware` class  
**Confidence**: Confirmed

---

## Troubleshooting

### Issue: "Class not found"

**Problem**:
```
Fatal error: Class 'JOOservices\Client\Client\ClientBuilder' not found
```

**Solution**:
1. Verify installation: `composer show jooservices/client`
2. Regenerate autoloader: `composer dump-autoload`
3. Check `require __DIR__ . '/vendor/autoload.php';` is present

---

### Issue: "cURL error 60: SSL certificate problem"

**Problem**:
```
cURL error 60: SSL certificate problem: unable to get local issuer certificate
```

**Solution**:
```php
// Temporary (development only)
$client = ClientBuilder::create()
    ->withVerifySsl(false)
    ->build();

// Production solution: Update CA bundle
// Download: https://curl.se/ca/cacert.pem
// php.ini: curl.cainfo = "/path/to/cacert.pem"
```

---

### Issue: "Connection timeout"

**Problem**:
```
NetworkConnectionException: Connection timeout after 1 seconds
```

**Solution**:
```php
$client = ClientBuilder::create()
    ->withConnectTimeout(5)    // Increase connection timeout
    ->withTimeout(30)          // Increase total timeout
    ->build();
```

---

### Issue: "Circuit breaker is open"

**Problem**:
```
CircuitBreakerException: Circuit breaker is open
```

**Solution**:
- Wait for recovery timeout (default: 10 seconds)
- Check downstream service health
- Adjust thresholds:
  ```php
  new CircuitBreakerConfig(
      failureThreshold: 10,      // Higher threshold
      recoveryTimeoutMs: 5000    // Faster recovery
  )
  ```

---

### Issue: "MongoDB extension not loaded"

**Problem** (if using MongoDB logger):
```
PHP Fatal error: Class 'MongoDB\Driver\Manager' not found
```

**Solution**:
1. Install extension: `pecl install mongodb`
2. Enable in php.ini: `extension=mongodb.so`
3. Or don't use MongoDB features:
   ```php
   // Use regular logger instead
   $client->withLogger($monolog);
   ```

---

## Development Setup

### Clone Repository (for contributors)

```bash
git clone https://github.com/jooservices/client.git
cd client
composer install
```

---

### Run Tests

```bash
# All tests (includes 98% coverage gate)
composer test

# Unit tests only
vendor/bin/phpunit --group=unit

# Feature tests only
vendor/bin/phpunit tests/Feature

# Integration tests
vendor/bin/phpunit --group=integration
```

**Evidence**: `composer.json` scripts  
**Confidence**: Confirmed

---

### Run Code Quality Checks

```bash
# All quality checks
composer quality

# Individual tools
composer analyse   # PHPStan
composer format    # Laravel Pint
composer check:cs  # PHPCS code sniffer
composer fix:cs    # Pint fix
```

**Evidence**: `composer.json` scripts  
**Confidence**: Confirmed

---

### Run Benchmarks

```bash
composer benchmark
```

**Evidence**: `phpbench.json` configuration  
**Confidence**: Confirmed

---

## Docker Setup (Recommendation)

**Unknown**: No Dockerfile or docker-compose.yml found.

**Recommendation**:
```dockerfile
# Dockerfile
FROM php:8.3-cli

RUN pecl install mongodb \
    && docker-php-ext-enable mongodb

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

RUN composer install

CMD ["./vendor/bin/phpunit"]
```

**Confidence**: Recommendation (no Docker setup found)

---

## IDE Configuration

### PHPStorm

**Enable PHPStan**:
1. Settings → PHP → Quality Tools → PHPStan
2. Configuration file: `phpstan.neon`
3. Level: 9

**Enable Composer Dependencies**:
1. Settings → PHP → Composer
2. Path: `composer.json`
3. Synchronize IDE settings with composer.json: ✓

---

### VS Code

**Install Extensions**:
- PHP Intelephense
- PHP Debug (Xdebug)
- Composer

**Configure PHPStan**:
`.vscode/settings.json`:
```json
{
    "php.linting.enabled": true,
    "php.validate.executablePath": "/usr/bin/php",
    "php.suggest.basic": false,
    "intelephense.stubs": [
        "Core",
        "mongodb",
        "json"
    ]
}
```

---

## Environment Variables

**Unknown**: No `.env.example` file found.

**Recommendation**: If using MongoDB logger, document required variables:
```bash
MONGODB_URI=mongodb://localhost:27017
MONGODB_DATABASE=logs
MONGODB_COLLECTION=client_request_logs
```

**Confidence**: Recommendation

---

## Related Documents

- [Project Overview](../00-architecture/01-project-overview.md)
- [Feature Inventory](../00-architecture/04-modules-and-domains.md)
- [API Reference](../02-user-guide/api-reference.md)
- [Examples](../03-examples/)
- [Troubleshooting Guide](../02-user-guide/troubleshooting.md)
