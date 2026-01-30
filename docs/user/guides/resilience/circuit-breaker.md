# Circuit Breaker Guide

Complete guide to circuit breaker pattern for preventing cascading failures.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Error Handling](ERROR_HANDLING.md) - Error handling patterns
> - [Rate Limiting Guide](RATE_LIMITING_GUIDE.md) - Rate limiting

---

## Overview

Circuit breaker prevents cascading failures by "opening" the circuit after too many failures, temporarily stopping requests to a failing service.

**Key Features:**
- ✅ Automatic circuit state management (CLOSED → OPEN → HALF_OPEN)
- ✅ Per-domain circuit breakers
- ✅ Configurable failure thresholds
- ✅ Automatic recovery attempts
- ✅ Retry-after headers

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableCircuitBreaker([
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 5,
            'timeout' => 60,
        ],
    ]);

$client = $factory->make();
$response = $client->get('https://api.example.com/data');
```

---

## Configuration

### Via .env

```env
JOOCLIENT_CIRCUIT_BREAKER_ENABLED=true
JOOCLIENT_CIRCUIT_BREAKER_FAILURE_THRESHOLD=5
JOOCLIENT_CIRCUIT_BREAKER_TIMEOUT=60
JOOCLIENT_CIRCUIT_BREAKER_HALF_OPEN_MAX_CALLS=3
JOOCLIENT_CIRCUIT_BREAKER_PER_DOMAIN=true
```

### Via Code

```php
$factory = (new Factory())->enableCircuitBreaker([
    'circuit_breaker' => [
        'enabled' => true,
        'failure_threshold' => 5, // Open circuit after 5 failures
        'timeout' => 60, // Keep open for 60 seconds
        'half_open_max_calls' => 3, // Test with 3 calls in half-open
        'per_domain' => true, // Separate circuit per domain
    ],
]);
```

---

## Circuit States

### CLOSED (Normal Operation)

Circuit is closed, all requests pass through normally.

```php
// Circuit is closed, requests work normally
$response = $client->get('https://api.example.com/data');
```

### OPEN (Circuit Open)

After too many failures, circuit opens. Requests immediately return 503 without hitting the service.

```php
// Circuit is open, returns 503 immediately
$response = $client->get('https://api.example.com/data');
// Status: 503
// Header: X-Circuit-Breaker-State: open
// Header: Retry-After: 60
```

### HALF_OPEN (Testing Recovery)

After timeout period, circuit enters half-open state. Limited requests are allowed to test if service recovered.

```php
// Circuit is half-open, testing recovery
$response = $client->get('https://api.example.com/data');
// If successful, circuit closes
// If failed, circuit opens again
```

---

## Handling Circuit Open

### Check Response Status

```php
$response = $client->get('https://api.example.com/data');

if ($response->getStatusCode() === 503) {
    if ($response->getHeaderLine('X-Circuit-Breaker-State') === 'open') {
        $retryAfter = (int) $response->getHeaderLine('Retry-After');
        echo "Circuit breaker is open. Retry after {$retryAfter} seconds\n";
    }
}
```

### Exception Handling

```php
use JOOservices\Client\Exceptions\CircuitBreaker\CircuitBreakerOpenException;

try {
    $response = $client->get('https://api.example.com/data');
} catch (CircuitBreakerOpenException $e) {
    echo "Circuit breaker is open for: {$e->getKey()}\n";
    echo "Retry after: {$e->getRetryAfter()} seconds\n";
}
```

### Using ResponseWrapper

```php
$response = $client->get('https://api.example.com/data');

if (!$response->isSuccess()) {
    if ($response->getStatusCode() === 503) {
        $retryAfter = (int) $response->getHeaderLine('Retry-After');
        // Wait and retry
        sleep($retryAfter);
        $response = $client->get('https://api.example.com/data');
    }
}
```

---

## Per-Domain Circuit Breakers

By default, circuit breakers are applied per domain. Each API domain has its own circuit breaker.

```php
$factory = (new Factory())->enableCircuitBreaker([
    'circuit_breaker' => [
        'per_domain' => true, // Separate circuit per domain
        'failure_threshold' => 5,
        'timeout' => 60,
    ],
]);

$client = $factory->make();

// These have separate circuit breakers
$client->get('https://api1.example.com/data'); // Circuit 1
$client->get('https://api2.example.com/data'); // Circuit 2
```

To use a global circuit breaker:

```php
$factory = (new Factory())->enableCircuitBreaker([
    'circuit_breaker' => [
        'per_domain' => false, // Global circuit breaker
        'failure_threshold' => 5,
        'timeout' => 60,
    ],
]);
```

---

## Disable Circuit Breaker Per Request

You can disable circuit breaker for specific requests:

```php
$response = $client->get('https://api.example.com/data', [
    'no_circuit_breaker' => true,
]);
```

---

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | `false` | Enable circuit breaker |
| `failure_threshold` | int | `5` | Number of failures before opening circuit |
| `timeout` | int | `60` | Seconds to keep circuit open |
| `half_open_max_calls` | int | `3` | Max calls allowed in half-open state |
| `per_domain` | bool | `true` | Apply circuit breaker per domain |

---

## Best Practices

### 1. Set Appropriate Thresholds

```php
// For critical services, fail fast
$factory->enableCircuitBreaker([
    'circuit_breaker' => [
        'failure_threshold' => 3, // Open after 3 failures
        'timeout' => 30, // Quick recovery
    ],
]);

// For non-critical services, be more tolerant
$factory->enableCircuitBreaker([
    'circuit_breaker' => [
        'failure_threshold' => 10, // More failures allowed
        'timeout' => 120, // Longer timeout
    ],
]);
```

### 2. Combine with Retries

```php
$factory = (new Factory())
    ->withRetries([
        'retries' => [
            'enabled' => true,
            'max_attempts' => 3,
        ],
    ])
    ->enableCircuitBreaker([
        'circuit_breaker' => [
            'enabled' => true,
            'failure_threshold' => 5,
        ],
    ]);
```

### 3. Monitor Circuit State

```php
// Check circuit state in health checks
$response = $client->get('https://api.example.com/health');

if ($response->getHeaderLine('X-Circuit-Breaker-State') === 'open') {
    // Alert monitoring system
    logger()->warning('Circuit breaker is open', [
        'domain' => 'api.example.com',
    ]);
}
```

---

## Troubleshooting

### Circuit Opens Too Quickly

1. **Increase threshold:** Set `failure_threshold` higher
2. **Check error handling:** Ensure transient errors aren't counted as failures
3. **Review timeout:** Increase `timeout` to give service more time to recover

### Circuit Never Recovers

1. **Check service:** Verify the service is actually working
2. **Reduce timeout:** Lower `timeout` to test recovery more frequently
3. **Check half-open calls:** Ensure `half_open_max_calls` is sufficient

---

## API Reference

### Factory Methods

```php
$factory->enableCircuitBreaker(array $config): self
```

### Exceptions

```php
use JOOservices\Client\Exceptions\CircuitBreaker\CircuitBreakerOpenException;

// Thrown when circuit is open and throw_on_circuit_open is true
```

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

