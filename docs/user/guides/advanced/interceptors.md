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
use JOOservices\Client\Factory\Factory;
use JOOservices\Client\Middlewares\InterceptorMiddleware;

$interceptor = new InterceptorMiddleware();
$interceptor->onRequest(function ($request, $options) {
    // Modify request
    $request = $request->withHeader('X-Custom', 'value');
    return [$request, $options];
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');

$client = $factory->make();
$response = $client->get('https://api.example.com/data');
```

---

## Request Interceptors

Request interceptors allow you to modify requests before they're sent.

### Add Headers

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onRequest(function ($request, $options) {
    $request = $request->withHeader('Authorization', 'Bearer ' . $token);
    return [$request, $options];
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

### Modify Request Body

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onRequest(function ($request, $options) {
    // Modify JSON body
    if (isset($options['json'])) {
        $options['json']['timestamp'] = time();
    }
    return [$request, $options];
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

### Modify Query Parameters

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onRequest(function ($request, $options) {
    $uri = $request->getUri();
    $query = $uri->getQuery();
    parse_str($query, $params);
    $params['api_key'] = env('API_KEY');
    
    $newUri = $uri->withQuery(http_build_query($params));
    $request = $request->withUri($newUri);
    
    return [$request, $options];
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

---

## Response Interceptors

Response interceptors allow you to modify responses after they're received.

### Add Response Headers

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onResponse(function ($response, $request) {
    return $response->withHeader('X-Processed-By', 'jooclient');
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

### Transform Response Body

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onResponse(function ($response, $request) {
    $body = (string) $response->getBody();
    $data = json_decode($body, true);
    
    // Transform data
    if (isset($data['items'])) {
        $data['items'] = array_map(function ($item) {
            $item['processed'] = true;
            return $item;
        }, $data['items']);
    }
    
    $newBody = json_encode($data);
    return $response->withBody(\GuzzleHttp\Psr7\Utils::streamFor($newBody));
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

### Log Response Metadata

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onResponse(function ($response, $request) {
    logger()->info('Response received', [
        'method' => $request->getMethod(),
        'uri' => (string) $request->getUri(),
        'status' => $response->getStatusCode(),
        'size' => $response->getBody()->getSize(),
    ]);
    
    return $response;
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

---

## Error Interceptors

Error interceptors allow you to handle errors during request execution.

### Log Errors

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onError(function ($error, $request) {
    logger()->error('Request failed', [
        'method' => $request->getMethod(),
        'uri' => (string) $request->getUri(),
        'error' => $error->getMessage(),
    ]);
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

### Retry on Specific Errors

```php
$interceptor = new InterceptorMiddleware();
$retryCount = 0;

$interceptor->onError(function ($error, $request) use (&$retryCount) {
    if ($error instanceof \GuzzleHttp\Exception\ConnectException && $retryCount < 3) {
        $retryCount++;
        sleep(1);
        // Retry logic would be handled by retry middleware
    }
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

### Transform Errors

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onError(function ($error, $request) {
    // Convert connection errors to custom exceptions
    if ($error instanceof \GuzzleHttp\Exception\ConnectException) {
        throw new \App\Exceptions\ServiceUnavailableException(
            'Service temporarily unavailable',
            $error
        );
    }
    
    throw $error;
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

---

## Multiple Interceptors

You can chain multiple interceptors of the same type:

```php
$interceptor = new InterceptorMiddleware();

// First interceptor: Add auth header
$interceptor->onRequest(function ($request, $options) {
    $request = $request->withHeader('Authorization', 'Bearer ' . $token);
    return [$request, $options];
});

// Second interceptor: Add correlation ID
$interceptor->onRequest(function ($request, $options) {
    $request = $request->withHeader('X-Correlation-ID', uniqid());
    return [$request, $options];
});

// Response interceptor: Log response
$interceptor->onResponse(function ($response, $request) {
    logger()->info('Request completed', [
        'uri' => (string) $request->getUri(),
        'status' => $response->getStatusCode(),
    ]);
    return $response;
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

---

## Advanced Usage

### Conditional Interception

```php
$interceptor = new InterceptorMiddleware();
$interceptor->onRequest(function ($request, $options) {
    // Only modify requests to specific domain
    if (str_contains((string) $request->getUri(), 'api.example.com')) {
        $request = $request->withHeader('X-API-Version', 'v2');
    }
    return [$request, $options];
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

### Request Timing

```php
$interceptor = new InterceptorMiddleware();
$startTime = null;

$interceptor->onRequest(function ($request, $options) use (&$startTime) {
    $startTime = microtime(true);
    return [$request, $options];
});

$interceptor->onResponse(function ($response, $request) use (&$startTime) {
    $duration = microtime(true) - $startTime;
    logger()->info('Request duration', [
        'uri' => (string) $request->getUri(),
        'duration_ms' => round($duration * 1000, 2),
    ]);
    return $response;
});

$factory = (new Factory())
    ->addMiddleware($interceptor, 'interceptor');
```

---

## Best Practices

### 1. Keep Interceptors Simple

```php
// Good: Single responsibility
$interceptor->onRequest(function ($request, $options) {
    return [$request->withHeader('X-API-Key', $apiKey), $options];
});

// Bad: Multiple responsibilities
$interceptor->onRequest(function ($request, $options) {
    // Too many things happening
    $request = $request->withHeader('X-API-Key', $apiKey);
    $request = $request->withHeader('X-Correlation-ID', uniqid());
    $request = $request->withHeader('X-User-ID', $userId);
    // ... more modifications
    return [$request, $options];
});
```

### 2. Use Immutable Pattern

```php
// Good: Returns new instance
$interceptor->onRequest(function ($request, $options) {
    return [$request->withHeader('X-Custom', 'value'), $options];
});

// Bad: Modifies in place (won't work)
$interceptor->onRequest(function ($request, $options) {
    $request->getHeaders()['X-Custom'] = ['value']; // Won't work
    return [$request, $options];
});
```

### 3. Handle Errors Gracefully

```php
$interceptor->onError(function ($error, $request) {
    // Log but don't throw
    logger()->error('Request failed', [
        'error' => $error->getMessage(),
        'uri' => (string) $request->getUri(),
    ]);
    
    // Re-throw to let other error handlers process
    throw $error;
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

### Callback Signatures

```php
// Request interceptor
function(RequestInterface $request, array $options): [RequestInterface, array]

// Response interceptor
function(ResponseInterface $response, RequestInterface $request): ResponseInterface

// Error interceptor
function(\Throwable $error, RequestInterface $request): void
```

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

