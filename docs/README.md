# JOOClient Documentation

Complete documentation for JOOClient - A robust, layered HTTP Client wrapper for PHP 8.2+.

> **Note**: Version 1.0.0 represents a complete rewrite focused on clean architecture and type safety. 
> Previous documentation has been archived. This documentation reflects the current v1.0.0 codebase.

## 📚 Documentation Structure

### 📖 [User Documentation](user/) - For End Users

- **[Examples](user/examples/)** - Working code examples
- **[API Reference](user/reference/)** - Class and method reference

## 🎯 Quick Start

### Basic Usage

```php
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->withTimeout(5)
    ->withHeader('Authorization', 'Bearer token')
    ->build();

$response = $client->get('/users/1');
echo $response->status(); // 200
```

### With Middleware

```php
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Cache\MemoryCache;

$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->withRetry(new RetryConfig(maxAttempts: 3))
    ->withCache(new MemoryCache(), defaultTtl: 3600)
    ->withDefaultLogging('my-app')
    ->build();
```

## 📖 Available Features

- **Strictly Typed Configuration** - Type-safe `ClientConfig` value object
- **Middleware Pipeline** - Extensible middleware architecture
- **Resilience Patterns** - Retry with backoff/jitter, Circuit Breaker
- **Caching** - PSR-16 compatible (Memory, Filesystem)
- **Logging** - PSR-3 compatible (Monolog integration)
- **Async Support** - Promise-based async requests and batch processing

## 🌐 Related Documentation

- **[Main README](../README.md)** - Package overview and features
- **[CHANGELOG](../CHANGELOG.md)** - Version history
- **[CONTRIBUTING](../CONTRIBUTING.md)** - Contribution guidelines

---

**Copyright (c) 2026 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
