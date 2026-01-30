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
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableCorrelationId();

$client = $factory->make();
$response = $client->get('https://api.example.com/data');

// Correlation ID is automatically added to request and response
$correlationId = $response->getHeaderLine('X-Correlation-ID');
```

---

## Configuration

### Via .env

```env
JOOCLIENT_CORRELATION_ID_ENABLED=true
JOOCLIENT_CORRELATION_ID_HEADER=X-Correlation-ID
```

### Via Code

```php
use JOOservices\Client\Middlewares\CorrelationIdMiddleware;

$middleware = new CorrelationIdMiddleware(
    'X-Correlation-ID', // Header name
    null // Custom generator (optional)
);

$factory = (new Factory())
    ->addMiddleware($middleware, 'correlation_id');
```

---

## Custom Correlation ID

You can provide your own correlation ID per request:

```php
$response = $client->get('https://api.example.com/data', [
    'correlation_id' => 'custom-id-123',
]);

// Response will have your custom ID
$correlationId = $response->getHeaderLine('X-Correlation-ID');
// Returns: 'custom-id-123'
```

---

## Custom ID Generator

You can use a custom function to generate correlation IDs:

```php
use JOOservices\Client\Middlewares\CorrelationIdMiddleware;

$generator = function() {
    return 'req-' . uniqid() . '-' . time();
};

$middleware = new CorrelationIdMiddleware('X-Correlation-ID', $generator);

$factory = (new Factory())
    ->addMiddleware($middleware, 'correlation_id');
```

---

## Custom Header Name

You can use a different header name:

```php
$middleware = new CorrelationIdMiddleware('X-Request-ID');

$factory = (new Factory())
    ->addMiddleware($middleware, 'correlation_id');

$response = $client->get('https://api.example.com/data');
$requestId = $response->getHeaderLine('X-Request-ID');
```

---

## Integration with Logging

Correlation IDs are automatically included in logs when logging is enabled:

```php
$factory = (new Factory())
    ->enableLogging([
        'logging' => [
            'enabled' => true,
            'driver' => 'mysql',
        ],
    ])
    ->enableCorrelationId();

$client = $factory->make();
$response = $client->get('https://api.example.com/data');

// Logs will include correlation ID in context
```

---

## Request Chaining

Correlation IDs work with request chains:

```php
$chain = $client->chain()
    ->get('https://api1.com/data')
    ->post('https://api2.com/process')
    ->get('https://api3.com/final');

// All requests in the chain share the same correlation ID
$finalResponse = $chain->getResponse();
$correlationId = $finalResponse->getHeaderLine('X-Correlation-ID');
```

---

## Best Practices

### 1. Use Consistent Header Name

```php
// Use same header name across all services
$middleware = new CorrelationIdMiddleware('X-Correlation-ID');
```

### 2. Generate Unique IDs

```php
// Use UUID for distributed systems
$generator = function() {
    return \Ramsey\Uuid\Uuid::uuid4()->toString();
};

$middleware = new CorrelationIdMiddleware('X-Correlation-ID', $generator);
```

### 3. Propagate in Service Calls

```php
// Extract correlation ID from incoming request
$correlationId = $request->header('X-Correlation-ID');

// Use in outgoing requests
$response = $client->get('https://api.example.com/data', [
    'correlation_id' => $correlationId,
]);
```

---

## API Reference

### Factory Methods

```php
$factory->enableCorrelationId(): self
```

### Middleware Constructor

```php
new CorrelationIdMiddleware(
    string $headerName = 'X-Correlation-ID',
    ?callable $generator = null
)
```

### Request Options

```php
$client->get($uri, [
    'correlation_id' => 'custom-id',
]);
```

---

## Troubleshooting

### Correlation ID Not Added

1. **Check middleware:** Ensure correlation ID middleware is added
2. **Check header name:** Verify header name matches
3. **Check existing header:** If request already has correlation ID, middleware skips

### Correlation ID Not Propagated

1. **Check response:** Verify correlation ID is in response headers
2. **Check service:** Ensure downstream service propagates the header
3. **Check logging:** Verify correlation ID is in logs

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

