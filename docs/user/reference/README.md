# API Reference

Complete API reference documentation for JOOClient.

## Contents

- **[Classes](classes.md)** - Complete class reference with all methods and properties

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

- **[Guides](../guides/)** - Feature-specific tutorials
- **[Examples](../examples/)** - Code examples

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
