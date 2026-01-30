# Filesystem Cache

Cache HTTP responses to filesystem.

## Overview

Filesystem caching stores HTTP responses on disk, providing persistent caching without requiring Redis.

## Configuration

### Environment Variables

```env
JOOCLIENT_CACHE_ENABLED=true
JOOCLIENT_CACHE_DRIVER=filesystem
JOOCLIENT_CACHE_TTL=3600
JOOCLIENT_CACHE_PATH=/path/to/cache
```

### Code Configuration

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableCache([
        'cache' => [
            'enabled' => true,
            'driver' => 'filesystem',
            'ttl' => 3600,
            'path' => storage_path('cache/jooclient'),
        ],
    ]);
```

## Usage

```php
$factory = (new Factory())->enableCache();
$result = $factory->make();

// First request - cache miss, stores in cache
$response1 = $result->get('https://api.example.com/data');

// Second request - cache hit, returns from cache
$response2 = $result->get('https://api.example.com/data');
```

## Cache Key Generation

Cache keys are generated from:
- HTTP method
- Full URI (including query string)

## TTL (Time To Live)

Default TTL is 3600 seconds (1 hour). Configure via `JOOCLIENT_CACHE_TTL`.

## See Also

- **[Redis Cache](redis-cache.md)** - Faster alternative using Redis

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
