# Cache Guide

## Overview

JOOClient supports HTTP response caching with two drivers:
- **Redis** - Fast in-memory caching for distributed systems
- **Filesystem** - File-based caching for single-server setups

## Configuration

### Via .env (Recommended)

```env
# Enable caching
JOOCLIENT_CACHE_ENABLED=true
JOOCLIENT_CACHE_DRIVER=redis  # or 'filesystem'
JOOCLIENT_CACHE_TTL=3600  # Default TTL in seconds (1 hour)

# Redis Configuration
JOOCLIENT_REDIS_HOST=127.0.0.1
JOOCLIENT_REDIS_PORT=6379
JOOCLIENT_REDIS_PASSWORD=
JOOCLIENT_REDIS_DATABASE=0
JOOCLIENT_REDIS_TIMEOUT=5
JOOCLIENT_REDIS_PREFIX=jooclient:

# Filesystem Configuration
JOOCLIENT_CACHE_PATH=/path/to/cache/directory
```

### Via Code

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Cache\RedisCache;
use JOOservices\Client\Cache\Config\RedisCacheConfig;

// Configure Redis Cache
$cacheConfig = new RedisCacheConfig(
    host: '127.0.0.1',
    port: 6379,
    prefix: 'jooclient:'
);
$cache = new RedisCache($cacheConfig);

// Build Client with Cache
$client = ClientBuilder::create()
    ->withCache($cache, defaultTtl: 3600)
    ->build();
```

## Usage

### Basic Caching

```php
$client = ClientBuilder::create()
    ->withCache($cache)
    ->build();

// First request - hits the API
$response1 = $client->get('https://api.example.com/users');

// Second request - served from cache!
$response2 = $client->get('https://api.example.com/users');
```

### Custom TTL Per Request

```php
// Cache for 10 minutes (overrides default)
$response = $client->get('https://api.example.com/users', [
    'cache_ttl' => 600
]);
```

### Disable Cache for Specific Request

```php
// Bypass cache for this request
$response = $client->get('https://api.example.com/users', [
    'no_cache' => true
]);
```

---

## How It Works

### Cache Key Generation

Cache keys are generated from:
- HTTP method (GET only)
- Host
- Path
- Query parameters

Example:
```
GET https://api.example.com/users?page=1
→ Cache key: sha256("GET:api.example.com/users?page=1")
```

### Caching Rules

✅ **Cached:**
- GET requests only (safe & idempotent)
- 2xx responses (200-299)
- No `Cache-Control: no-store` or `no-cache` headers

❌ **NOT Cached:**
- POST, PUT, DELETE, PATCH requests
- Non-2xx responses (errors)
- Requests with `no_cache` option
- Responses with `Cache-Control: no-store`

---

## Redis Driver

### Features
- ✅ Fast in-memory storage
- ✅ Distributed caching across servers
- ✅ Automatic expiration
- ✅ Key prefixing for namespace isolation

### Requirements
```bash
# Install Redis extension
pecl install redis
```

### Configuration Class

```php
use JOOservices\Client\Cache\Config\RedisCacheConfig;

$config = new RedisCacheConfig(
    host: '127.0.0.1',
    port: 6379,
    password: null,
    database: 0,
    prefix: 'api_cache:'
);
```

### Benefits
- Very fast (in-memory)
- Shared cache across multiple servers
- Automatic memory management
- Built-in expiration

---

## Best Practices

### 1. Choose Appropriate TTL

```php
// Short-lived data (5 minutes)
$client = ClientBuilder::create()
    ->withCache($cache, defaultTtl: 300)
    ->build();
```

### 2. Use Different Cache Keys for Different Parameters

```php
// These create different cache entries ✅
$client->get('/users?page=1');
$client->get('/users?page=2');
$client->get('/users?sort=name');
```

### 3. Clear Cache When Data Changes

```php
use JOOservices\Client\Cache\RedisCache;

$cache = new RedisCache($config);

// Clear all cache
$cache->clear();

// Delete specific key
$cache->delete('specific_cache_key');
```

### 4. Monitor Cache Hit Rate

```php
// Log cache hits/misses by inspecting response headers or logs
$client = ClientBuilder::create()
    ->withDefaultLogging()
    ->withCache($cache)
    ->build();
```

### 5. Use Cache for Read-Heavy APIs

```php
// Good: GET requests for public data
$client->get('/api/products');  // ✅ Cached

// Bad: Authenticated user data
$client->get('/api/user/profile');  // ⚠️ Be careful with caching
```

---

## Troubleshooting

### Cache Not Working

1. **Check if caching is enabled** (Requires valid CacheInterface passed to `withCache`)
2. **Check Redis connection**
   ```bash
   redis-cli ping
   # Should return: PONG
   ```

### Redis Connection Errors

```php
// Error: "Redis extension is not installed"
// Solution: Install ext-redis
pecl install redis
```

---

## Examples

### Laravel Integration (Manual Binding)

```php
// AppServiceProvider.php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Cache\RedisCache;
use JOOservices\Client\Cache\Config\RedisCacheConfig;

$this->app->singleton(ClientBuilder::class, function ($app) {
    $cacheConfig = new RedisCacheConfig(
        host: env('REDIS_HOST', '127.0.0.1'),
        prefix: 'joo_client:'
    );
    
    return ClientBuilder::create()
        ->withCache(new RedisCache($cacheConfig));
});
```

### Different TTL for Different Endpoints

```php
// Cache user list for 5 minutes
$users = $client->get('/users', ['cache_ttl' => 300]);

// Cache product catalog for 1 hour
$products = $client->get('/products', ['cache_ttl' => 3600]);

// Don't cache orders
$orders = $client->get('/orders', ['no_cache' => true]);
```

---

## Testing

```php
// tests/Feature/ApiCachingTest.php
use Tests\TestCase;
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Cache\FilesystemCache;

class ApiCachingTest extends TestCase
{
    public function test_responses_are_cached()
    {
        $cache = new FilesystemCache('/tmp/test-cache');
        
        $client = ClientBuilder::create()
            ->withCache($cache)
            ->build();

        // Make same request twice
        $response1 = $client->get('https://api.example.com/test');
        $response2 = $client->get('https://api.example.com/test');

        // Both should return same data
        $this->assertEquals(
            (string)$response1->getBody(),
            (string)$response2->getBody()
        );
    }
}
```

---

## Conclusion

JOOClient's caching feature provides:
- ✅ Simple PSR-16 integration
- ✅ Built-in Redis & Filesystem support
- ✅ Respects HTTP cache headers
- ✅ PRODUCTION-READY

Choose Redis for performance and scalability, or Filesystem for simplicity!




