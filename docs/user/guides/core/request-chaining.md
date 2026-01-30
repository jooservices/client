# Request Chaining Guide

Complete guide to fluent request chaining with conditional execution, middleware, and user-agent control.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Error Handling](ERROR_HANDLING.md) - Error handling patterns
> - [Debugging](DEBUGGING.md) - Request debugging

---

## Overview

Request chaining allows you to make sequential HTTP requests with a fluent API, conditional execution, chain-level middleware, and user-agent control.

**Key Features:**
- ✅ Sequential request chaining
- ✅ Conditional execution (`ifSuccess()`, `ifError()`, `ifStatus()`)
- ✅ Chain-level configuration (base URI, headers, timeout, template, query)
- ✅ Chain-level middleware
- ✅ User-agent control (consistent, random, per-request)
- ✅ Request/response interceptors

---

## Basic Usage

### Simple Sequential Requests

```php
use JOOservices\Client\Factory\Factory;

$factory = new Factory();
$client = $factory->make();

$chain = $client->chain()
    ->get('https://api1.com/data')
    ->post('https://api2.com/process', ['json' => ['source' => 'api1']])
    ->get('https://api3.com/final');

// Get final response
$finalResponse = $chain->getResponse();

// Get all responses
$allResponses = $chain->getResponses();
```

---

## Chain-Level Configuration

### withBaseUri() - Set Base URI

Set a base URI that applies to all requests in the chain.

```php
$chain = $client->chain()
    ->withBaseUri('https://api.example.com')
    ->get('/users')      // Requests https://api.example.com/users
    ->post('/posts');    // Requests https://api.example.com/posts
```

**Behavior:** Base URI is prepended to relative paths. Absolute URIs are not modified.

---

### withHeaders() - Set Headers

Set headers that apply to all requests in the chain.

```php
$chain = $client->chain()
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->get('/users')      // Has Authorization header
    ->post('/posts');    // Has Authorization header

// Merge with per-request headers
$chain = $client->chain()
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->get('/users', ['headers' => ['X-Custom' => 'value']]);
    // Both Authorization and X-Custom headers
```

**Behavior:** Chain headers are merged with per-request headers. Per-request headers take precedence.

---

### withTimeout() - Set Timeout

Set timeout for all requests in the chain.

```php
$chain = $client->chain()
    ->withTimeout(30)
    ->get('/users')      // 30s timeout
    ->post('/posts');    // 30s timeout
```

**Behavior:** Applies to all requests unless overridden by per-request timeout.

---

### withTemplate() - Apply Template

Apply a request template to all requests in the chain.

```php
$factory = (new Factory())
    ->registerTemplate('github_api', [
        'base_uri' => 'https://api.github.com',
        'headers' => [
            'Accept' => 'application/vnd.github.v3+json',
            'Authorization' => 'token ' . $token,
        ],
    ]);

$client = $factory->make();

$chain = $client->chain()
    ->withTemplate('github_api')
    ->get('/repos/owner/repo')      // Uses github_api template
    ->get('/user');                 // Uses github_api template
```

**Behavior:** Template is applied to all requests unless overridden by per-request template.

---

### withQuery() - Set Query Parameters

Set default query parameters for all requests.

```php
$chain = $client->chain()
    ->withQuery(['api_key' => $key, 'version' => 'v1'])
    ->get('/users')      // ?api_key=...&version=v1
    ->get('/posts', ['query' => ['page' => 1]]);
    // ?api_key=...&version=v1&page=1
```

**Behavior:** Chain query parameters are merged with per-request query. Per-request query takes precedence.

---

### Combining Configuration

```php
$chain = $client->chain()
    ->withBaseUri('https://api.example.com')
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->withTimeout(30)
    ->withQuery(['version' => 'v1'])
    ->get('/users')
    ->post('/posts', ['json' => ['name' => 'John']]);
```

---

## Conditional Execution

### ifSuccess() - Execute on Success

```php
$chain = $client->chain()
    ->get('https://api.example.com/users/123')
    ->ifSuccess()
        ->post('https://api.example.com/log', ['json' => ['status' => 'ok']])
    ->get('https://api.example.com/final');
```

**Behavior:** Next request executes only if previous response was 2xx.

---

### ifError() - Execute on Error

```php
$chain = $client->chain()
    ->get('https://api.example.com/users/123')
    ->ifError()
        ->post('https://api.example.com/errors', ['json' => ['status' => 'failed']])
    ->get('https://api.example.com/final');
```

**Behavior:** Next request executes only if previous response was not 2xx.

---

### ifStatus() - Execute on Specific Status

```php
// Single status
$chain = $client->chain()
    ->get('https://api.example.com/resource')
    ->ifStatus(200)
        ->get('https://api.example.com/success-path')
    ->ifStatus(404)
        ->get('https://api.example.com/not-found-handler');

// Multiple statuses
$chain = $client->chain()
    ->get('https://api.example.com/resource')
    ->ifStatus([404, 500])
        ->post('https://api.example.com/error-handler', ['json' => ['type' => 'error']]);
```

**Behavior:** Next request executes only if previous status matches.

---

### if() - Custom Condition

```php
$chain = $client->chain()
    ->get('https://api.example.com/users/123')
    ->if(function($response) {
        $data = $response->getContent();
        return is_array($data) && isset($data['active']) && $data['active'] === true;
    })
        ->post('https://api.example.com/activate', ['json' => ['user_id' => 123]])
    ->get('https://api.example.com/final');
```

**Behavior:** Next request executes only if callback returns `true`.

---

## Chain-Level Middleware

### withMiddleware() - Add Guzzle Middleware

```php
$chain = $client->chain()
    ->withMiddleware(function(callable $handler) {
        return function($request, $options) use ($handler) {
            // Add custom header to all requests in chain
            $request = $request->withHeader('X-Chain-ID', 'chain-123');
            return $handler($request, $options);
        };
    }, 'chain-header')
    ->get('https://api1.com/data')
    ->post('https://api2.com/process');
```

**Behavior:** Middleware applies to all subsequent requests in the chain.

---

### onRequest() - Request Interceptor

```php
$chain = $client->chain()
    ->onRequest(function($request, $options) {
        // Log request
        Log::info('Chain request', ['uri' => (string)$request->getUri()]);
        
        // Modify request
        $request = $request->withHeader('X-Request-Time', (string)time());
        
        return [$request, $options];
    })
    ->get('https://api1.com/data')
    ->post('https://api2.com/process');
```

**Behavior:** Interceptor runs before each request in the chain.

---

### onResponse() - Response Interceptor

```php
$chain = $client->chain()
    ->onResponse(function($response, $request) {
        // Log response
        Log::info('Chain response', [
            'uri' => (string)$request->getUri(),
            'status' => $response->getStatusCode(),
        ]);
        
        // Modify response if needed
        return $response;
    })
    ->get('https://api1.com/data')
    ->post('https://api2.com/process');
```

**Behavior:** Interceptor runs after each successful response.

---

### onError() - Error Interceptor

```php
$chain = $client->chain()
    ->onError(function($exception, $request) {
        // Log error
        Log::error('Chain error', [
            'uri' => (string)$request->getUri(),
            'error' => $exception->getMessage(),
        ]);
    })
    ->get('https://api1.com/data')
    ->post('https://api2.com/process');
```

**Behavior:** Interceptor runs when a request fails.

---

## User-Agent Control

### withConsistentUserAgent() - Same UA for All

```php
$chain = $client->chain()
    ->withConsistentUserAgent()  // Generate once, reuse for all
    ->get('https://api1.com/data')
    ->post('https://api2.com/process')  // Same UA
    ->get('https://api3.com/final');     // Same UA
```

**Behavior:** Generates one user-agent and reuses it for all requests in the chain.

---

### withUserAgent() - Specific UA for All

```php
$chain = $client->chain()
    ->withUserAgent('MyApp/1.0')
    ->get('https://api1.com/data')
    ->post('https://api2.com/process');  // All use 'MyApp/1.0'
```

**Behavior:** Sets a specific user-agent for all requests in the chain.

---

### withRandomUserAgent() - Random UA per Request

```php
$chain = $client->chain()
    ->withRandomUserAgent()  // Explicit (or default behavior)
    ->get('https://api1.com/data')
    ->post('https://api2.com/process');  // Different UA each
```

**Behavior:** Uses random user-agent for each request (default behavior).

---

### withNextUserAgent() - Override for Next Request

```php
$chain = $client->chain()
    ->withConsistentUserAgent()
    ->get('https://api1.com/data')
    ->withNextUserAgent('CustomUA/2.0')  // Override next only
    ->post('https://api2.com/process')   // Uses 'CustomUA/2.0'
    ->get('https://api3.com/final');      // Back to consistent UA
```

**Behavior:** Overrides user-agent for the next request only, then returns to chain setting.

**Priority Order:**
1. `withNextUserAgent()` - Highest priority
2. `withUserAgent()` - Chain-specific UA
3. `withConsistentUserAgent()` - Auto-generated consistent UA
4. `withRandomUserAgent()` - Random per request (default)

---

## Advanced Patterns

### Combining Features

```php
$chain = $client->chain()
    // Chain configuration
    ->withBaseUri('https://api.example.com')
    ->withHeaders(['Authorization' => 'Bearer ' . $token])
    ->withTimeout(30)
    ->withConsistentUserAgent()
    ->withMiddleware(function(callable $handler) {
        return function($request, $options) use ($handler) {
            $request = $request->withHeader('X-Chain-Feature', 'enabled');
            return $handler($request, $options);
        };
    }, 'feature-header')
    ->onRequest(function($request, $options) {
        // Additional request modification
        return [$request, $options];
    })
    
    // Sequential requests
    ->get('/users/123')
    ->ifSuccess()
        ->then(function($response) use ($client) {
            // Use response data in next request
            $user = $response->getContent();
            return $client->post('/analytics', [
                'json' => ['user_id' => $user['id']]
            ]);
        })
        ->get('/final');
```

---

### Error Handling in Chains

```php
try {
    $chain = $client->chain()
        ->get('https://api.example.com/users/123')
        ->ifSuccess()
            ->post('https://api.example.com/log', ['json' => ['status' => 'ok']])
        ->ifError()
            ->post('https://api.example.com/errors', ['json' => ['status' => 'failed']]);
    
    $finalResponse = $chain->getResponse();
    $finalResponse->throwOnError(); // Throw exception if not successful
} catch (\JOOservices\Client\Exceptions\Http\ClientException $e) {
    // Handle 4xx error
} catch (\JOOservices\Client\Exceptions\Http\ServerException $e) {
    // Handle 5xx error
}
```

---

### Using Response Data

```php
$chain = $client->chain()
    ->getJson('https://api.example.com/users/123')
    ->if(function($response) {
        $user = $response->getContent();
        return $user !== null && isset($user['active']) && $user['active'] === true;
    })
        ->postJson('https://api.example.com/activate', ['user_id' => 123])
    ->getResponse();

$finalResponse = $chain->getResponse();
$data = $finalResponse->getContent();
```

---

## Best Practices

### ✅ DO

- **Use chain-level configuration** (`withBaseUri()`, `withHeaders()`, etc.) for common settings
- **Use consistent user-agent** when making related requests to the same service
- **Add chain middleware** for authentication, logging, or common headers
- **Use conditionals** to handle different response scenarios
- **Store responses** if you need to access previous responses later
- **Handle errors** with `throwOnError()` or try-catch

### ❌ DON'T

- **Don't create very long chains** - break into smaller chains for readability
- **Don't mix async and sync** - use `settle()` or `pool()` for parallel requests
- **Don't forget error handling** - always check responses or use exceptions
- **Don't use chain middleware for global concerns** - use Factory-level middleware instead

---

## Performance Considerations

**Chain Client Creation:**
- Chain client is created lazily (only when middleware is added)
- Client is cached and reused for all requests in the chain
- Minimal overhead when no middleware is used

**User-Agent Generation:**
- Consistent user-agent is generated once and reused
- Random user-agent uses existing session mechanism
- No performance impact for user-agent control

---

## Examples

See [examples/11-request-chaining.php](../../examples/11-request-chaining.php) for complete working examples.

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

