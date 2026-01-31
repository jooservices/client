# Correlation IDs Guide

Complete guide to correlation IDs for tracing requests across services.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Logging Guide](MULTI_LOGGER_GUIDE.md) - Logging with correlation IDs

---

## Overview

Correlation IDs allow you to trace requests across multiple services by adding a unique identifier to each request and response.

**Key Features:**
- ✅ Automatic correlation ID generation
- ✅ Custom correlation ID support
- ✅ Propagates to responses
- ✅ Custom header name
- ✅ Custom ID generator

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Middleware\CorrelationIdMiddleware;

$client = ClientBuilder::create()
    ->addMiddleware(new CorrelationIdMiddleware(), 'correlation_id')
    ->build();

$response = $client->get('https://api.example.com/data');

// Correlation ID is automatically added to request and response
$correlationId = $response->header('X-Correlation-ID');
```

---

## Configuration

### Custom Header Name

```php
use JOOservices\Client\Middleware\CorrelationIdMiddleware;

$middleware = new CorrelationIdMiddleware(
    headerName: 'X-Request-ID'
);

$client = ClientBuilder::create()
    ->addMiddleware($middleware, 'correlation_id')
    ->build();
```

### Custom ID Generator

You can use a custom function to generate correlation IDs:

```php
use JOOservices\Client\Middleware\CorrelationIdMiddleware;

$generator = function() {
    return 'req-' . uniqid() . '-' . time();
};

$middleware = new CorrelationIdMiddleware(
    headerName: 'X-Correlation-ID',
    generator: $generator
);
```

---

## Custom Correlation ID Per Request

You can provide your own correlation ID per request using options:

```php
// If the middleware supports it (check specific middleware implementation)
// Typically, Guzzle middleware might look for specific request options
```

> **Note**: The core `CorrelationIdMiddleware` automatically generates an ID if one isn't present in the request headers. To use a custom one, add the header manually to the request.

```php
$response = $client->get('https://api.example.com/data', [
    'headers' => [
        'X-Correlation-ID' => 'custom-id-123'
    ]
]);
```

---

## Integration with Logging

When using `ClientBuilder::withDefaultLogging` or `withLogger`, ensure the logging middleware comes *after* the correlation ID middleware in the stack if you want the ID to be logged (middleware stack order matters).

```php
$client = ClientBuilder::create()
    ->addMiddleware(new CorrelationIdMiddleware(), 'correlation_id')
    ->withDefaultLogging()
    ->build();
```

---

## Best Practices

### 1. Use Consistent Header Name

Use the same header name across all your microservices (e.g., `X-Correlation-ID` or `X-Request-ID`) to ensure traceability across the entire system.

### 2. Generate Unique IDs

Use UUIDs for distributed systems to avoid collisions.

```php
$generator = function() {
    return \Ramsey\Uuid\Uuid::uuid4()->toString();
};
```

---

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

