# Request Deduplication Guide

Complete guide to preventing duplicate requests within a time window.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Cache Guide](CACHE_GUIDE.md) - Cache configuration

---

## Overview

Request deduplication prevents duplicate requests within a time window by caching responses.

**Key Features:**
- ✅ Automatic deduplication based on request signature
- ✅ Configurable TTL (time-to-live)
- ✅ Uses existing cache infrastructure
- ✅ Per-request control
- ✅ Falls back to in-memory cache if no cache configured

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableCache([
        'cache' => [
            'enabled' => true,
            'driver' => 'redis',
        ],
    ])
    ->enableDeduplication([
        'ttl' => 60, // Cache responses for 60 seconds
    ]);

$client = $factory->make();

// First request - hits the server
$response1 = $client->get('https://api.example.com/data');

// Second identical request - returns cached response
$response2 = $client->get('https://api.example.com/data');
```

---

## Configuration

### TTL (Time-To-Live)

Control how long responses are cached:

```php
$factory = (new Factory())
    ->enableCache([...])
    ->enableDeduplication([
        'ttl' => 300, // 5 minutes
    ]);
```

---

## How It Works

### Request Signature

Deduplication creates a unique signature from:
- HTTP method
- Request URI
- Request body
- Relevant options (json, form_params)

Identical requests within the TTL window return the cached response.

---

## Disable Deduplication Per Request

You can disable deduplication for specific requests:

```php
$response = $client->get('https://api.example.com/data', [
    'no_deduplication' => true,
]);
```

---

## Integration with Cache

Deduplication requires a cache to store responses:

```php
$factory = (new Factory())
    ->enableCache([
        'cache' => [
            'enabled' => true,
            'driver' => 'redis', // or 'filesystem'
        ],
    ])
    ->enableDeduplication([
        'ttl' => 60,
    ]);
```

If no cache is configured, deduplication falls back to in-memory cache (per-request only, not shared).

---

## Best Practices

### 1. Use Appropriate TTL

```php
// For frequently changing data: Short TTL
$factory->enableDeduplication(['ttl' => 10]); // 10 seconds

// For stable data: Longer TTL
$factory->enableDeduplication(['ttl' => 3600]); // 1 hour
```

### 2. Combine with Cache

```php
// Deduplication + caching for maximum efficiency
$factory = (new Factory())
    ->enableCache([...]) // HTTP response caching
    ->enableDeduplication([...]); // Request deduplication
```

### 3. Disable for Mutating Requests

```php
// GET requests: Enable deduplication
$response = $client->get('https://api.example.com/data');

// POST requests: Disable deduplication
$response = $client->post('https://api.example.com/data', [
    'json' => ['name' => 'New'],
    'no_deduplication' => true, // Always execute POST
]);
```

---

## API Reference

### Factory Methods

```php
$factory->enableDeduplication(array $config = []): self
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `ttl` | int | `60` | Time-to-live in seconds |

### Request Options

```php
$client->get($uri, [
    'no_deduplication' => true, // Disable for this request
]);
```

---

## Troubleshooting

### Deduplication Not Working

1. **Check cache:** Ensure cache is enabled
2. **Check TTL:** Verify TTL is appropriate
3. **Check request:** Ensure requests are identical (method, URI, body)

### Too Many Cache Hits

1. **Reduce TTL:** Lower TTL for more frequent updates
2. **Disable selectively:** Use `no_deduplication` for specific requests

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

