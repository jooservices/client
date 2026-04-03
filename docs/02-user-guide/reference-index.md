# API Reference

Complete API reference documentation for JOOClient.

## Contents

- **[Classes](classes-reference.md)** - Complete class reference with all methods and properties

## Quick Reference

### ClientBuilder

The main entry point for creating clients.

```php
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->withTimeout(30)
    ->withDefaultLogging('my-app')
    ->withRetry(new RetryConfig(maxAttempts: 3))
    ->build();
```

### HttpClient

The interface you interact with.

```php
$response = $client->get('/users');
$response = $client->post('/users', ['json' => $data]);
$response = $client->request('PUT', '/users/1', ['headers' => ['X-Foo' => 'Bar']]);
```

### ResponseWrapper

The response object returned by client methods.

```php
if ($response->successful()) {
    $content = $response->body();
    $json = $response->json();
    $status = $response->status();
}
```

## See Also

- **[User Guide Home](README.md)** - Main user-guide index
- **[Examples](../03-examples/README.md)** - Runnable code examples

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
