# Configuration

Configure JOOClient to match your application's needs.

## Configuration Methods

JOOClient can be configured in three ways:

1. **Environment Variables** (`.env`) - Recommended for most settings
2. **Config File** (`config/jooclient.php`) - Detailed configuration
3. **Code** - Programmatic configuration

## Environment Variables

The simplest way to configure JOOClient is via `.env`:

```env
# Logging
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=monolog

# Retries
JOOCLIENT_RETRIES=true
JOOCLIENT_RETRIES_MAX=3
JOOCLIENT_RETRIES_DELAY=1
```

## Programmatic Configuration

Configure in code for dynamic settings using `ClientBuilder`:

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Resilience\RetryConfig;

$builder = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->withTimeout(30)
    // Retry configuration
    ->withRetry(new RetryConfig(
        maxAttempts: 3,
        delaySeconds: 1
    ))
    // Logging with specific domain
    ->withDefaultLogging('my-app-domain');
    
$client = $builder->build();
```

## Common Configuration Scenarios

### Basic Setup

```php
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()->build();
```

### With Logging

```php
$client = ClientBuilder::create()
    ->withDefaultLogging('my-app')
    ->build();
```

### With Retries and Timeout

```php
use JOOservices\Client\Resilience\RetryConfig;

$client = ClientBuilder::create()
    ->withTimeout(10)
    ->withRetry(new RetryConfig(maxAttempts: 3))
    ->build();
```

### Custom Headers and HTTPS

```php
$client = ClientBuilder::create()
    ->withBaseUri('https://secure-api.com')
    ->withHeader('Authorization', 'Bearer token')
    ->withVerifySsl(true) // Enabled by default
    ->build();
```

## Next Steps

- **[First Request](first-request.md)** - Make your first API call
- **[Feature Guides](../guides/)** - Learn about specific features

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
