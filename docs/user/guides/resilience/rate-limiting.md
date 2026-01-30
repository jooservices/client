# Rate Limiting Guide

Complete guide to rate limiting HTTP requests to prevent 429 errors and respect API limits.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Caching Guide](CACHE_GUIDE.md) - Cache configuration
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Rate limiting prevents your application from exceeding API rate limits by automatically throttling requests. JOOClient supports multiple rate limiting strategies and integrates with your existing cache infrastructure.

**Key Features:**
- ✅ Multiple strategies (Token Bucket, Sliding Window, Fixed Window)
- ✅ Per-domain rate limiting
- ✅ Automatic retry-after calculation
- ✅ Uses existing cache (Redis/Filesystem)
- ✅ Falls back to in-memory cache if no cache configured

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableRateLimiting([
        'rate_limiting' => [
            'enabled' => true,
            'strategy' => 'token_bucket',
            'max_requests' => 100,
            'per_seconds' => 60,
        ],
    ]);

$client = $factory->make();
$response = $client->get('https://api.example.com/data');
```

---

## Configuration

### Via .env

```env
JOOCLIENT_RATE_LIMITING_ENABLED=true
JOOCLIENT_RATE_LIMITING_STRATEGY=token_bucket
JOOCLIENT_RATE_LIMITING_MAX_REQUESTS=100
JOOCLIENT_RATE_LIMITING_PER_SECONDS=60
JOOCLIENT_RATE_LIMITING_PER_DOMAIN=true
```

### Via Code

```php
$factory = (new Factory())->enableRateLimiting([
    'rate_limiting' => [
        'enabled' => true,
        'strategy' => 'token_bucket', // or 'sliding_window', 'fixed_window'
        'max_requests' => 100,
        'per_seconds' => 60,
        'per_domain' => true, // Separate limits per domain
    ],
]);
```

---

## Strategies

### Token Bucket (Recommended)

Allows bursts of requests up to the limit, then refills tokens at a steady rate.

**Best for:** APIs that allow bursts but have sustained rate limits.

```php
$factory = (new Factory())->enableRateLimiting([
    'rate_limiting' => [
        'strategy' => 'token_bucket',
        'max_requests' => 100,
        'per_seconds' => 60,
    ],
]);
```

### Sliding Window

Tracks requests in a sliding time window. More accurate but uses more memory.

**Best for:** Strict rate limits where accuracy is important.

```php
$factory = (new Factory())->enableRateLimiting([
    'rate_limiting' => [
        'strategy' => 'sliding_window',
        'max_requests' => 100,
        'per_seconds' => 60,
    ],
]);
```

### Fixed Window

Tracks requests in fixed time windows (e.g., per minute). Simple and efficient.

**Best for:** Simple rate limits where slight bursts are acceptable.

```php
$factory = (new Factory())->enableRateLimiting([
    'rate_limiting' => [
        'strategy' => 'fixed_window',
        'max_requests' => 100,
        'per_seconds' => 60,
    ],
]);
```

---

## Per-Domain Rate Limiting

By default, rate limiting is applied per domain. This means each API domain has its own rate limit bucket.

```php
$factory = (new Factory())->enableRateLimiting([
    'rate_limiting' => [
        'per_domain' => true, // Separate limits per domain
        'max_requests' => 100,
        'per_seconds' => 60,
    ],
]);

$client = $factory->make();

// These have separate rate limits
$client->get('https://api1.example.com/data');
$client->get('https://api2.example.com/data');
```

To use a global rate limit across all domains:

```php
$factory = (new Factory())->enableRateLimiting([
    'rate_limiting' => [
        'per_domain' => false, // Global limit
        'max_requests' => 100,
        'per_seconds' => 60,
    ],
]);
```

---

## Handling Rate Limit Exceeded

When rate limit is exceeded, the middleware returns a 429 response with `Retry-After` header.

### Check Response Status

```php
$response = $client->get('https://api.example.com/data');

if ($response->getStatusCode() === 429) {
    $retryAfter = (int) $response->getHeaderLine('Retry-After');
    echo "Rate limit exceeded. Retry after {$retryAfter} seconds\n";
}
```

### Using ResponseWrapper

```php
$response = $client->get('https://api.example.com/data');

if (!$response->isSuccess()) {
    if ($response->getStatusCode() === 429) {
        $retryAfter = (int) $response->getHeaderLine('Retry-After');
        // Wait and retry
        sleep($retryAfter);
        $response = $client->get('https://api.example.com/data');
    }
}
```

### Exception Handling

```php
use JOOservices\Client\Exceptions\RateLimit\RateLimitExceededException;

try {
    $response = $client->get('https://api.example.com/data');
} catch (RateLimitExceededException $e) {
    echo "Rate limit exceeded for: {$e->getKey()}\n";
    echo "Retry after: {$e->getRetryAfter()} seconds\n";
}
```

---

## Disable Rate Limiting Per Request

You can disable rate limiting for specific requests:

```php
$response = $client->get('https://api.example.com/data', [
    'no_rate_limit' => true,
]);
```

---

## Advanced Usage

### Custom Rate Limit Key

```php
// Rate limit based on custom key
$response = $client->get('https://api.example.com/data', [
    'rate_limit_key' => 'custom_key_' . $userId,
]);
```

### Different Limits Per Endpoint

```php
// Use request templates with different rate limits
$factory = (new Factory())
    ->registerTemplate('api_slow', [
        'rate_limiting' => [
            'max_requests' => 10,
            'per_seconds' => 60,
        ],
    ])
    ->registerTemplate('api_fast', [
        'rate_limiting' => [
            'max_requests' => 1000,
            'per_seconds' => 60,
        ],
    ]);
```

---

## Integration with Cache

Rate limiting uses your configured cache (Redis or Filesystem) to store rate limit state. If no cache is configured, it falls back to in-memory cache (per-request only).

**Best Practice:** Always configure a persistent cache (Redis or Filesystem) for rate limiting to work across multiple requests.

```php
$factory = (new Factory())
    ->enableCache([
        'cache' => [
            'enabled' => true,
            'driver' => 'redis',
        ],
    ])
    ->enableRateLimiting([
        'rate_limiting' => [
            'enabled' => true,
            'max_requests' => 100,
            'per_seconds' => 60,
        ],
    ]);
```

---

## Performance Considerations

- **Token Bucket:** Lowest overhead, allows bursts
- **Sliding Window:** Higher memory usage, most accurate
- **Fixed Window:** Low overhead, may allow slight bursts at window boundaries

**Recommendation:** Use Token Bucket for most use cases.

---

## Troubleshooting

### Rate Limits Not Working

1. **Check cache is enabled:** Rate limiting requires cache for persistence
2. **Check configuration:** Verify `enabled` is `true`
3. **Check strategy:** Ensure strategy name is correct

### Too Many 429 Errors

1. **Increase limit:** Adjust `max_requests` or `per_seconds`
2. **Use per-domain:** Enable `per_domain` to separate limits
3. **Check cache:** Ensure cache is working (rate limit state is lost if cache fails)

---

## API Reference

### Factory Methods

```php
$factory->enableRateLimiting(array $config): self
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable rate limiting |
| `strategy` | string | `token_bucket` | Strategy: `token_bucket`, `sliding_window`, `fixed_window` |
| `max_requests` | int | `100` | Maximum requests allowed |
| `per_seconds` | int | `60` | Time window in seconds |
| `per_domain` | bool | `true` | Apply limits per domain |

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

