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
use JOOservices\Client\Factory\Factory;

// Redis Cache
$factory = (new Factory())->enableCache([
    'cache' => [
        'enabled' => true,
        'driver' => 'redis',
        'default_ttl' => 3600,
        'redis' => [
            'host' => '127.0.0.1',
            'port' => 6379,
            'database' => 0,
            'prefix' => 'jooclient:',
        ]
    ]
]);

// Filesystem Cache
$factory = (new Factory())->enableCache([
    'cache' => [
        'enabled' => true,
        'driver' => 'filesystem',
        'default_ttl' => 3600,
        'filesystem' => [
            'path' => storage_path('framework/cache/jooclient'),
        ]
    ]
]);
```

## Usage

### Basic Caching

```php
use JOOservices\Client\Factory\Factory;

// Enable cache via config
$factory = (new Factory())->enableCache();
$result = $factory->make();

// First request - hits the API
$response1 = $result->get('https://api.example.com/users');

// Second request - served from cache!
$response2 = $result->get('https://api.example.com/users');
```

### Custom TTL Per Request

```php
$result = $factory->make();

// Cache for 10 minutes
$response = $result->get('https://api.example.com/users', [
    'cache_ttl' => 600
]);
```

### Disable Cache for Specific Request

```php
$result = $factory->make();

// Bypass cache for this request
$response = $result->get('https://api.example.com/users', [
    'no_cache' => true
]);
```

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

### Cache-Control Respecting

The middleware respects `Cache-Control` headers:

```php
// Response with Cache-Control: max-age=300
// Will be cached for 5 minutes (min of max-age and default_ttl)

// Response with Cache-Control: no-cache
// Will NOT be cached
```

## Redis Driver

### Features
- ✅ Fast in-memory storage
- ✅ Distributed caching across servers
- ✅ Automatic expiration
- ✅ Key prefixing for namespace isolation
- ✅ Connection pooling

### Requirements
```bash
# Install Redis extension
pecl install redis
```

### Configuration

```env
JOOCLIENT_CACHE_DRIVER=redis
JOOCLIENT_REDIS_HOST=127.0.0.1
JOOCLIENT_REDIS_PORT=6379
JOOCLIENT_REDIS_PASSWORD=your_password  # Optional
JOOCLIENT_REDIS_DATABASE=0
JOOCLIENT_REDIS_PREFIX=jooclient:
```

### Benefits
- Very fast (in-memory)
- Shared cache across multiple servers
- Automatic memory management
- Built-in expiration

### Use When
- Running multiple servers
- Need fast response times
- Have Redis available
- Want distributed caching

## Filesystem Driver

### Features
- ✅ No external dependencies
- ✅ Simple file-based storage
- ✅ Automatic directory creation
- ✅ TTL-based expiration
- ✅ Concurrent access safe

### Configuration

```env
JOOCLIENT_CACHE_DRIVER=filesystem
JOOCLIENT_CACHE_PATH=/var/www/storage/cache/jooclient
```

### Storage Structure

```
/cache/jooclient/
  ├── ab/
  │   └── cd/
  │       └── abcd...hash (cache file)
  └── ef/
      └── gh/
          └── efgh...hash (cache file)
```

Each file contains:
```
[expiration_timestamp]
[cached_content]
```

### Benefits
- No external dependencies
- Simple to set up
- Works on any filesystem
- Easy to inspect/debug

### Use When
- Single server deployment
- Redis not available
- Simple caching needs
- Development/testing

## Best Practices

### 1. Choose Appropriate TTL

```php
// Short-lived data (5 minutes)
$config['cache']['default_ttl'] = 300;

// Medium-lived data (1 hour)
$config['cache']['default_ttl'] = 3600;

// Long-lived data (24 hours)
$config['cache']['default_ttl'] = 86400;
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
use JOOservices\Client\Cache\Config\RedisCacheConfig;

$config = RedisCacheConfig::fromArray([/* ... */]);
$cache = new RedisCache($config);

// Clear all cache
$cache->clear();

// Delete specific key
$cache->delete('specific_cache_key');
```

### 4. Monitor Cache Hit Rate

```php
// Log cache hits/misses for monitoring
$factory = (new Factory())
    ->enableLogging()  // Log requests
    ->enableCache();    // Cache responses

// Check logs to see cache performance
```

### 5. Use Cache for Read-Heavy APIs

```php
// Good: GET requests for public data
$client->get('/api/products');  // ✅ Cached

// Bad: Authenticated user data
$client->get('/api/user/profile');  // ⚠️ Be careful with caching
```

## Troubleshooting

### Cache Not Working

1. **Check if caching is enabled**
   ```env
   JOOCLIENT_CACHE_ENABLED=true
   ```

2. **Verify driver configuration**
   ```env
   JOOCLIENT_CACHE_DRIVER=redis  # or filesystem
   ```

3. **Check Redis connection** (if using Redis)
   ```bash
   redis-cli ping
   # Should return: PONG
   ```

4. **Verify filesystem permissions** (if using filesystem)
   ```bash
   ls -la /path/to/cache
   # Should be writable by PHP
   ```

### Redis Connection Errors

```php
// Error: "Redis extension is not installed"
// Solution: Install ext-redis
pecl install redis

// Error: "Failed to connect to Redis server"
// Solution: Check Redis is running
systemctl status redis

// Error: "Redis authentication failed"
// Solution: Check password in .env
JOOCLIENT_REDIS_PASSWORD=correct_password
```

### Filesystem Permission Errors

```bash
# Make cache directory writable
chmod 755 /path/to/cache
chown www-data:www-data /path/to/cache
```

## Performance Tips

### 1. Use Redis for Production

Redis is significantly faster than filesystem caching:
- Redis: ~0.1ms per operation
- Filesystem: ~10ms per operation

### 2. Set Appropriate TTL

Longer TTL = Less API calls = Better performance
But: Stale data risk increases

### 3. Use Cache for Expensive Operations

```php
// Good: Expensive API calls
$client->get('/api/heavy-computation');

// Not needed: Fast endpoints
$client->get('/api/health-check');  // Skip cache
```

### 4. Monitor Cache Size

```bash
# Redis memory usage
redis-cli info memory

# Filesystem cache size
du -sh /path/to/cache
```

## Examples

### Laravel Integration

```php
// In a controller
use JOOservices\Client\Factory\Factory;

class ApiController extends Controller
{
    public function getUsers()
    {
        $factory = (new Factory())
            ->enableCache()  // Uses config from .env
            ->enableLogging();

        $result = $factory->make();
        $response = $result->get('https://api.example.com/users');

        return response()->json(json_decode($response->getBody()));
    }
}
```

### Different TTL for Different Endpoints

```php
$factory = (new Factory())->enableCache();
$result = $factory->make();

// Cache user list for 5 minutes
$users = $result->get('/users', ['cache_ttl' => 300]);

// Cache product catalog for 1 hour
$products = $result->get('/products', ['cache_ttl' => 3600]);

// Don't cache orders
$orders = $result->get('/orders', ['no_cache' => true]);
```

### Using Both Redis and Filesystem

```php
// Production: Use Redis
if (app()->environment('production')) {
    $config['cache']['driver'] = 'redis';
}

// Development: Use Filesystem
if (app()->environment('local')) {
    $config['cache']['driver'] = 'filesystem';
}

$factory = (new Factory())->enableCache($config);
```

## Testing

```php
// tests/Feature/ApiCachingTest.php
use Tests\TestCase;
use JOOservices\Client\Factory\Factory;

class ApiCachingTest extends TestCase
{
    public function test_responses_are_cached()
    {
        $factory = (new Factory())->enableCache([
            'cache' => [
                'enabled' => true,
                'driver' => 'filesystem',
                'filesystem' => [
                    'path' => storage_path('framework/cache/test'),
                ],
            ],
        ]);

        $result = $factory->make();

        // Make same request twice
        $response1 = $result->get('https://api.example.com/test');
        $response2 = $result->get('https://api.example.com/test');

        // Both should return same data
        $this->assertEquals(
            (string)$response1->getBody(),
            (string)$response2->getBody()
        );
    }
}
```

## Conclusion

JOOClient's caching feature provides:
- ✅ Easy configuration via .env
- ✅ Multiple driver support (Redis & Filesystem)
- ✅ Respects HTTP cache headers
- ✅ SOLID architecture
- ✅ Production-ready

Choose Redis for performance and scalability, or Filesystem for simplicity!



