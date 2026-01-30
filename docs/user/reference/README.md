# API Reference

Complete API reference documentation for JOOClient.

## Contents

- **[Classes](classes.md)** - Complete class reference with all methods and properties

## Quick Reference

### Factory

```php
use JOOservices\Client\Factory\Factory;

$factory = new Factory();
$factory = $factory->enableLogging();
$factory = $factory->enableCache();
$factory = $factory->enableRetries(3, 1, 500);
$result = $factory->make();
```

### Client

```php
$response = $result->get('https://api.example.com');
$response = $result->post('https://api.example.com', ['json' => $data]);
$response = $result->request('GET', 'https://api.example.com');
```

### ResponseWrapper

```php
if ($response->isSuccess()) {
    $content = $response->getContent();
    $json = $response->getJson();
    $status = $response->getStatusCode();
}
```

## See Also

- **[Guides](../guides/)** - Feature-specific tutorials
- **[Examples](../examples/)** - Code examples

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
