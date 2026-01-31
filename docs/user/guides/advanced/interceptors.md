# Interceptors Guide

Complete guide to request/response interceptors for modifying requests, responses, and handling errors.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Interceptors allow you to modify requests before they're sent, modify responses after they're received, and handle errors during request execution.

**Key Features:**
- ✅ Request interceptors (modify requests)
- ✅ Response interceptors (modify responses)
- ✅ Error interceptors (handle errors)
- ✅ Multiple interceptors per type
- ✅ Immutable pattern (returns new instances)

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Middleware\InterceptorMiddleware;

$interceptor = new InterceptorMiddleware();
$interceptor->onRequest(function ($request, $options) {
    // Modify request
    $request = $request->withHeader('X-Custom', 'value');
    return [$request, $options];
});

$client = ClientBuilder::create()
    ->addMiddleware($interceptor, 'interceptor')
    ->build();

$response = $client->get('https://api.example.com/data');
```

---

## Request Interceptors

Request interceptors allow you to modify requests before they're sent.

### Add Headers

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onRequest(function ($request, $options) {
    $request = $request->withHeader('Authorization', 'Bearer token123');
    return [$request, $options];
});
```

### Modify Query Parameters

```php
$interceptor->onRequest(function ($request, $options) {
    $uri = $request->getUri();
    $newUri = $uri->withQuery($uri->getQuery() . '&api_key=123');
    return [$request->withUri($newUri), $options];
});
```

---

## Response Interceptors

Response interceptors allow you to modify responses after they're received.

### Log Response Metadata

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onResponse(function ($response, $request) {
    // Log response details
    return $response;
});
```

---

## Error Interceptors

Error interceptors allow you to handle exceptions during request execution.

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onError(function ($error, $request) {
    // Log error
    throw $error; // Re-throw or handle
});
```

---

## Best Practices

### 1. Keep Interceptors Simple

Interceptors should focus on a single responsibility (e.g., auth injection, logging) rather than doing everything in one closure.

### 2. Use Immutable Pattern

PSR-7 Requests and Responses are immutable. Always return the modified instance.

```php
$interceptor->onRequest(function ($request, $options) {
    return [$request->withHeader('X-Custom', 'value'), $options];
});
```

---

## API Reference

### InterceptorMiddleware Methods

```php
$interceptor->onRequest(callable $interceptor): self
$interceptor->onResponse(callable $interceptor): self
$interceptor->onError(callable $interceptor): self
```

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

