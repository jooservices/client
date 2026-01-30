# Request Templates Guide

Complete guide to request templates for reducing boilerplate and reusing configurations.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Request templates allow you to define reusable request configurations (headers, base URI, timeout, etc.) and apply them to requests.

**Key Features:**
- ✅ Reusable request configurations
- ✅ Template inheritance
- ✅ Apply templates via `withTemplate()` or request options
- ✅ Chain-level template support

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

$factory = new Factory();

// Register a template
$factory->registerTemplate('api', [
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'Authorization' => 'Bearer ' . env('API_KEY'),
        'Content-Type' => 'application/json',
    ],
    'timeout' => 30,
]);

$client = $factory->make();

// Use template
$response = $client->get('/users', [
    'template' => 'api',
]);
```

---

## Registering Templates

### Via Factory

```php
$factory = new Factory();

$factory->registerTemplate('api', [
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'Authorization' => 'Bearer token',
    ],
    'timeout' => 30,
]);

$factory->registerTemplate('webhook', [
    'headers' => [
        'X-Webhook-Signature' => 'signature',
    ],
    'timeout' => 10,
]);
```

### Via Configuration

```php
$factory = Jooclient::fromConfig([
    'templates' => [
        'api' => [
            'base_uri' => 'https://api.example.com',
            'headers' => [
                'Authorization' => 'Bearer token',
            ],
        ],
        'webhook' => [
            'headers' => [
                'X-Webhook-Signature' => 'signature',
            ],
        ],
    ],
]);
```

---

## Using Templates

### Via Request Options

```php
$response = $client->get('/users', [
    'template' => 'api',
]);
```

### Via Chain Configuration

```php
$chain = $client->chain()
    ->withTemplate('api')
    ->get('/users')
    ->post('/posts', ['json' => ['title' => 'Hello']]);
```

### Via Factory Method

```php
$factory = new Factory();
$factory->registerTemplate('api', [
    'base_uri' => 'https://api.example.com',
]);

// Template is applied to all requests from this factory
$client = $factory->make();
$response = $client->get('/users'); // Uses base_uri from template
```

---

## Template Options

Templates support all standard Guzzle request options:

```php
$factory->registerTemplate('api', [
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'Authorization' => 'Bearer token',
        'Accept' => 'application/json',
    ],
    'timeout' => 30,
    'connect_timeout' => 10,
    'verify' => true,
    'allow_redirects' => true,
    'query' => [
        'api_version' => 'v2',
    ],
]);
```

---

## Template Inheritance

Templates can extend other templates:

```php
// Base template
$factory->registerTemplate('base', [
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'Accept' => 'application/json',
    ],
]);

// Extended template
$factory->registerTemplate('authenticated', [
    'extends' => 'base',
    'headers' => [
        'Authorization' => 'Bearer token',
    ],
]);
```

---

## Dynamic Templates

Templates can use closures for dynamic values:

```php
$factory->registerTemplate('api', [
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'Authorization' => function() {
            return 'Bearer ' . cache()->get('api_token');
        },
    ],
]);
```

---

## Best Practices

### 1. Organize by Service

```php
$factory->registerTemplate('github_api', [
    'base_uri' => 'https://api.github.com',
    'headers' => [
        'Authorization' => 'token ' . env('GITHUB_TOKEN'),
        'Accept' => 'application/vnd.github.v3+json',
    ],
]);

$factory->registerTemplate('slack_webhook', [
    'base_uri' => 'https://hooks.slack.com',
    'headers' => [
        'Content-Type' => 'application/json',
    ],
]);
```

### 2. Use Environment-Specific Templates

```php
$factory->registerTemplate('api', [
    'base_uri' => env('API_BASE_URI', 'https://api.example.com'),
    'headers' => [
        'Authorization' => 'Bearer ' . env('API_KEY'),
    ],
]);
```

### 3. Combine with Chain Configuration

```php
$chain = $client->chain()
    ->withTemplate('api')
    ->withHeaders(['X-Request-ID' => uniqid()])
    ->get('/users')
    ->post('/posts');
```

---

## API Reference

### Factory Methods

```php
$factory->registerTemplate(string $name, array $options): self
$factory->getTemplate(string $name): ?RequestTemplate
$factory->hasTemplate(string $name): bool
```

### Request Options

```php
$client->get($uri, [
    'template' => 'api',
]);
```

### Chain Methods

```php
$chain->withTemplate(string $templateName): self
```

---

## Troubleshooting

### Template Not Applied

1. **Check template name:** Ensure template is registered
2. **Check request options:** Verify `template` option is set
3. **Check template structure:** Ensure template options are valid

### Template Options Overridden

Template options are merged with request options. Request options take precedence:

```php
// Template has timeout: 30
// Request has timeout: 60
// Result: timeout is 60 (request option wins)
```

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

