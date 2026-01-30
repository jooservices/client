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
JOOCLIENT_LOGGING_DRIVER=mysql

# MySQL
JOOCLIENT_DB_LOGGING=true
JOOCLIENT_DB_HOST=127.0.0.1
JOOCLIENT_DB_DATABASE=jooclient
JOOCLIENT_DB_USERNAME=root
JOOCLIENT_DB_PASSWORD=secret

# Caching
JOOCLIENT_CACHE_ENABLED=true
JOOCLIENT_CACHE_DRIVER=redis
JOOCLIENT_CACHE_TTL=3600

# Retries
JOOCLIENT_RETRIES=true
JOOCLIENT_RETRIES_MAX=3
JOOCLIENT_RETRIES_DELAY=1
```

## Config File

For more detailed configuration, edit `config/jooclient.php`:

```php
return [
    'logging' => [
        'enabled' => true,
        'driver' => 'mysql', // 'mysql', 'mongodb', 'monolog', or 'multi'
        
        'connection' => [
            'mysql' => [
                'enabled' => true,
                'host' => env('JOOCLIENT_DB_HOST', '127.0.0.1'),
                'port' => env('JOOCLIENT_DB_PORT', 3306),
                'database' => env('JOOCLIENT_DB_DATABASE', 'jooclient'),
                'username' => env('JOOCLIENT_DB_USERNAME', 'root'),
                'password' => env('JOOCLIENT_DB_PASSWORD', ''),
                'table' => env('JOOCLIENT_DB_TABLE', 'client_request_logs'),
                'batch' => env('JOOCLIENT_DB_BATCH', false),
            ],
            'mongodb' => [
                'enabled' => env('JOOCLIENT_MONGODB_LOGGING', false),
                'dsn' => env('JOOCLIENT_MONGODB_DSN', 'mongodb://127.0.0.1:27017'),
                'database' => env('JOOCLIENT_MONGODB_DATABASE', 'jooclient'),
                'collection' => env('JOOCLIENT_MONGODB_COLLECTION', 'client_request_logs'),
                'batch' => env('JOOCLIENT_MONGODB_BATCH', false),
            ],
        ],
    ],
    
    'retries' => [
        'enabled' => env('JOOCLIENT_RETRIES', true),
        'max_attempts' => env('JOOCLIENT_RETRIES_MAX', 3),
        'delay_seconds' => env('JOOCLIENT_RETRIES_DELAY', 1),
        'min_error_code' => env('JOOCLIENT_RETRIES_MIN_ERROR_CODE', 500),
    ],
    
    'defaults' => [
        'timeout' => env('JOOCLIENT_TIMEOUT', 30),
        'connect_timeout' => env('JOOCLIENT_CONNECT_TIMEOUT', 10),
        'headers' => [
            'Accept' => 'application/json',
        ],
    ],
];
```

## Programmatic Configuration

Configure in code for dynamic settings:

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->addOptions([
        'timeout' => 30,
        'base_uri' => 'https://api.example.com',
    ])
    ->enableRetries(3, 1, 500)
    ->enableLogging()
    ->enableCache();
```

## Configuration Presets

Use presets for common scenarios:

```php
use JOOservices\Client\Factory\Factory;

// Development (longer timeout, logging enabled)
$factory = Factory::forDevelopment();

// Production (secure defaults, rate limiting, circuit breaker)
$factory = Factory::forProduction();

// Testing (fast failures, no retries)
$factory = Factory::forTesting();

// API Client (pre-configured with base URI and auth)
$factory = Factory::forApiClient('https://api.example.com', env('API_KEY'));

// Auto-detect environment (uses APP_ENV)
$factory = Factory::create();
```

## Common Configuration Scenarios

### Basic Setup (No Logging)

```php
$factory = new Factory();
$result = $factory->make();
```

### With Logging Only

```php
$factory = (new Factory())
    ->enableLogging();
$result = $factory->make();
```

### With Logging and Caching

```php
$factory = (new Factory())
    ->enableLogging()
    ->enableCache();
$result = $factory->make();
```

### Production Setup

```php
$factory = (new Factory())
    ->enableLogging()
    ->enableCache()
    ->enableRetries(3, 2, 500)
    ->addOptions([
        'timeout' => 30,
        'verify' => true, // SSL verification
    ]);
$result = $factory->make();
```

## Next Steps

- **[First Request](first-request.md)** - Make your first API call
- **[Feature Guides](../guides/)** - Learn about specific features

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
