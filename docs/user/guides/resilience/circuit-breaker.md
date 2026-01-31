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
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Resilience\CircuitBreakerConfig;

$client = ClientBuilder::create()
    ->withCircuitBreaker(new CircuitBreakerConfig(
        failureThreshold: 5,
        timeout: 60
    ))
    ->build();

$response = $client->get('https://api.example.com/data');
```

---

## Configuration

### Via Code

```php
use JOOservices\Client\Resilience\CircuitBreakerConfig;

$config = new CircuitBreakerConfig(
    failureThreshold: 5,   // Open circuit after 5 failures
    timeout: 60,           // Keep open for 60 seconds
    halfOpenMaxCalls: 3,   // Test with 3 calls in half-open
    perDomain: true        // Separate circuit per domain
);

$client = ClientBuilder::create()
    ->withCircuitBreaker($config)
    ->build();
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

if ($response->status() === 503) {
    if ($response->header('X-Circuit-Breaker-State') === 'open') {
        $retryAfter = (int) $response->header('Retry-After');
        echo "Circuit breaker is open. Retry after {$retryAfter} seconds\n";
    }
}
```

### Exception Handling

If configured to throw exceptions (optional, default is to return 503 response):

```php
// Not currently enabled by default in middleware, 
// but you can check status codes manually as above.
```

---

## Per-Domain Circuit Breakers

By default, circuit breakers are applied per domain. Each API domain has its own circuit breaker.

```php
$client = ClientBuilder::create()
    ->withCircuitBreaker(new CircuitBreakerConfig(
        perDomain: true,
        failureThreshold: 5
    ))
    ->build();

// These have separate circuit breakers
$client->get('https://api1.example.com/data'); // Circuit 1
$client->get('https://api2.example.com/data'); // Circuit 2
```

To use a global circuit breaker:

```php
$client = ClientBuilder::create()
    ->withCircuitBreaker(new CircuitBreakerConfig(
        perDomain: false
    ))
    ->build();
```

---

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `failureThreshold` | int | `5` | Number of failures before opening circuit |
| `timeout` | int | `60` | Seconds to keep circuit open |
| `halfOpenMaxCalls` | int | `3` | Max calls allowed in half-open state |
| `perDomain` | bool | `true` | Apply circuit breaker per domain |

---

## Best Practices

### 1. Set Appropriate Thresholds

```php
// For critical services, fail fast
$criticalClient = ClientBuilder::create()
    ->withCircuitBreaker(new CircuitBreakerConfig(
        failureThreshold: 3,
        timeout: 30
    ))
    ->build();
```

### 2. Combine with Retries

```php
$client = ClientBuilder::create()
    ->withRetry(new RetryConfig(maxAttempts: 3))
    ->withCircuitBreaker(new CircuitBreakerConfig(failureThreshold: 5))
    ->build();
```

### 3. Monitor Circuit State

```php
// Check circuit state in health checks
$response = $client->get('https://api.example.com/health');

if ($response->header('X-Circuit-Breaker-State') === 'open') {
    // Alert monitoring system
    // ...
}
```

---

## Troubleshooting

### Circuit Opens Too Quickly

1. **Increase threshold:** Set `failureThreshold` higher
2. **Review timeout:** Increase `timeout` to give service more time to recover

### Circuit Never Recovers

1. **Check service:** Verify the service is actually working
2. **Reduce timeout:** Lower `timeout` to test recovery more frequently

---

## API Reference

### Builder Methods

```php
$builder->withCircuitBreaker(CircuitBreakerConfig $config, ?StateStoreInterface $store = null): self
```


---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

