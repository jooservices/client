# User Documentation

Complete guide for using JOOClient v1.0.0.

> **Note**: Version 1.0.0 is a complete rewrite. If you're looking for documentation for earlier versions, please refer to legacy documentation.

## 📚 Contents

### 📖 [Examples](examples/)
Working code examples you can run immediately:
- **[01-basic-get.php](examples/01-basic-get.php)** - Simple GET request
- **[02-post-with-json.php](examples/02-post-with-json.php)** - POST request with JSON
- **[03-async-requests.php](examples/03-async-requests.php)** - Concurrent async requests
- **[04-error-handling.php](examples/04-error-handling.php)** - Error handling patterns
- **[05-middleware-logging.php](examples/05-middleware-logging.php)** - Monolog logging setup

### 📚 [API Reference](reference/)
- **[Class Reference](reference/classes.md)** - Complete API documentation for all classes

## 🚀 Quick Start

### Installation

```bash
composer require jooservices/client
```

### Basic Usage

```php
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->withTimeout(5)
    ->build();

$response = $client->get('/users/1');
echo $response->json()['name'];
```

### With Resilience

```php
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Resilience\CircuitBreakerConfig;

$client = ClientBuilder::create()
    ->withRetry(new RetryConfig(maxAttempts: 3, baseDelayMs: 100))
    ->withCircuitBreaker(new CircuitBreakerConfig(failureThreshold: 5))
    ->build();
```

### With Caching

```php
use JOOservices\Client\Cache\MemoryCache;
use JOOservices\Client\Cache\FilesystemCache;

// In-memory cache
$client = ClientBuilder::create()
    ->withCache(new MemoryCache(), defaultTtl: 3600)
    ->build();

// Filesystem cache
$cache = new FilesystemCache(__DIR__ . '/cache');
$client = ClientBuilder::create()
    ->withCache($cache, defaultTtl: 3600)
    ->build();
```

### With Logging

```php
// Simple Monolog logging
$client = ClientBuilder::create()
    ->withDefaultLogging('my-app', __DIR__ . '/logs')
    ->build();

// Custom logger
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$logger = new Logger('api');
$logger->pushHandler(new StreamHandler('php://stdout'));

$client = ClientBuilder::create()
    ->withLogger($logger, logBodies: true)
    ->build();

// IP metadata is captured automatically when logging is enabled:
// local_ip, target_ip, target_hostname, wan_ip
```

## 🔗 See Also

- **[Main README](../../README.md)** - Project overview
- **[CHANGELOG](../../CHANGELOG.md)** - Version history
- **[CONTRIBUTING](../../CONTRIBUTING.md)** - Contributing guidelines

---

**Copyright (c) 2026 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

#### Integration
- **[Laravel Integration](guides/integration/laravel-integration.md)** - Laravel-specific integration
- **[Testing](guides/integration/testing.md)** - Testing and debugging

### 💡 [Examples](examples/)
Runnable code examples demonstrating all features.

### 📚 [Reference](reference/)
Complete API reference documentation.

### 🚀 [Deployment](deployment/)
Production deployment guides and best practices.

### 🔧 [Troubleshooting](troubleshooting/)
Common issues and solutions.

---

## 🎯 Quick Start

1. **[Install JOOClient](getting-started/installation.md)**
2. **[Make your first request](getting-started/first-request.md)**
3. **[Explore features](guides/)**

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
