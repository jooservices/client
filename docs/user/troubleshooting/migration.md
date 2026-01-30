# Migration Guide

This guide covers all breaking changes and deprecated APIs across JOOClient versions.

## Current Deprecations (v1.x)

These APIs are deprecated but still work in v1.x. They will be removed in v2.0.

### Deprecated: MonologLoggingAdapter::createFromConfig()

**Status:** Deprecated since v1.2.0, will be removed in v2.0.0

**Old API:**
```php
use JOOservices\Client\Logging\Drivers\MonologLoggingAdapter;

$adapter = MonologLoggingAdapter::createFromConfig([
    'channel' => 'api_client',
    'file' => storage_path('logs/api-client.log'),
    'level' => 'debug',
    'formatter' => 'json',
]);
```

**New API:**
```php
use JOOservices\Client\Logging\Drivers\MonologLoggingAdapter;
use JOOservices\Client\Logging\Config\MonologConfig;

$config = MonologConfig::fromArray([
    'channel' => 'api_client',
    'file' => storage_path('logs/api-client.log'),
    'level' => 'debug',
    'formatter' => 'json',
]);
$adapter = MonologLoggingAdapter::fromConfig($config);
```

**Why changed:** To follow SOLID principles by using value objects instead of raw arrays.

---

### Deprecated: RequestResponseLogger namespace

**Status:** Deprecated since v1.3.0b, may be removed in v2.0.0

**Old Import:**
```php
use JOOservices\Client\Logging\RequestResponseLogger;
```

**New Import:**
```php
use JOOservices\Client\Middlewares\RequestResponseLogger;
```

**Why changed:** Class was moved to better reflect its purpose as middleware, not just logging.

**Note:** Both imports work thanks to `class_alias`. Consider updating for clarity, but not urgent.

---

## v1.3.0b Breaking Changes

### Breaking: Client Properties Made Private

**Status:** Breaking change in v1.3.0b

The `Client` class properties `client` and `factory` are now private to maintain proper encapsulation.

**Old API:**
```php
$result = $factory->make();
$response = $result->client->get('/api/users');  // ❌ No longer works
$history = $result->factory->getHistory($result->client);  // ❌ No longer works
```

**New API:**
```php
$result = $factory->make();
$response = $result->get('/api/users');  // ✅ Works
$history = $result->getHistory();  // ✅ Works

// For testing/internal use only:
$guzzle = $result->getGuzzleClient();  // @internal
$factory = $result->getFactory();  // @internal
```

**Migration steps:**
1. Remove `->client` from all client method calls
2. Replace `$factory->getHistory($client)` with `$result->getHistory()`
3. Use internal getters only for testing

---

## v1.2.0: Simplified enableLogging() API

### Overview

The Factory API has been simplified. Instead of calling separate methods like `enableDbLogging()`, `enableMongoDbLogging()`, or `enableMonologLogging()` with parameters, you now use a single `enableLogging()` method that automatically loads configuration from your Laravel `.env` and `config/jooclient.php` files.

## What Changed?

### ❌ Old API (Removed)
```php
// MySQL
$factory = $factory->enableDbLogging('127.0.0.1', 3306, 'jooclient', [
    'table' => 'client_request_logs',
    'batch' => false
]);

// MongoDB
$factory = $factory->enableMongoDbLogging(
    'mongodb://127.0.0.1:27017',
    'jooclient',
    'client_request_logs'
);

// Monolog
$factory = $factory->enableMonologLogging($monologLogger);
```

### ✅ New API (Recommended)
```php
// Everything loads from .env and config/jooclient.php
$factory = $factory->enableLogging();
```

## How to Migrate

### Step 1: Configure via .env

Instead of passing parameters to methods, configure everything in your `.env` file:

```env
# Main logging settings
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=mysql  # mysql, mongodb, monolog, or multi

# MySQL settings
JOOCLIENT_DB_LOGGING=true
JOOCLIENT_DB_HOST=127.0.0.1
JOOCLIENT_DB_PORT=3306
JOOCLIENT_DB_DATABASE=jooclient
JOOCLIENT_DB_USERNAME=root
JOOCLIENT_DB_PASSWORD=secret
JOOCLIENT_DB_TABLE=client_request_logs
JOOCLIENT_DB_BATCH=false
JOOCLIENT_DB_FALLBACK=error_log

# MongoDB settings
JOOCLIENT_MONGODB_LOGGING=true
JOOCLIENT_MONGODB_DSN=mongodb://127.0.0.1:27017
JOOCLIENT_MONGODB_DATABASE=jooclient
JOOCLIENT_MONGODB_COLLECTION=client_request_logs
JOOCLIENT_MONGODB_BATCH=false
JOOCLIENT_MONGODB_FALLBACK=error_log
JOOCLIENT_MONGODB_LOG_PATH=/path/to/logs/mongodb_errors.log
JOOCLIENT_MONGODB_ROTATE_SIZE=10485760
JOOCLIENT_MONGODB_ROTATE_FILES=5

# Monolog settings
JOOCLIENT_MONOLOG_CHANNEL=jooclient
JOOCLIENT_MONOLOG_FILE=/path/to/logs/jooclient.log
JOOCLIENT_MONOLOG_LEVEL=info
JOOCLIENT_MONOLOG_FORMATTER=json

# Multi-logger settings (log to multiple destinations)
JOOCLIENT_LOGGING_DRIVER=multi
JOOCLIENT_MULTI_DRIVERS=mysql,mongodb,monolog
```

### Step 2: Update Your Code

Replace old method calls with the new simplified API:

**Before:**
```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableDbLogging('127.0.0.1', 3306, 'jooclient')
    ->enableRetries(3);
```

**After:**
```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableLogging()  // ← Automatically loads from config
    ->enableRetries(3);
```

### Step 3: Verify Configuration

The configuration is automatically loaded from `config/jooclient.php`, which reads from your `.env` file. Make sure you've published the config:

```bash
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=config
```

## Advanced Usage

### Custom Config Override

If you need to override config programmatically (e.g., for testing):

```php
$factory = (new Factory())->enableLogging([
    'logging' => [
        'enabled' => true,
        'driver' => 'mysql',
        'connection' => [
            'mysql' => [
                'enabled' => true,
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'test_db',
                'username' => 'root',
                'password' => 'root',
                'table' => 'client_request_logs',
                'batch' => false,
                'fallback' => 'error_log',
            ]
        ]
    ]
]);
```

### Multi-Logger

Log to multiple destinations simultaneously:

```env
JOOCLIENT_LOGGING_DRIVER=multi
JOOCLIENT_MULTI_DRIVERS=mysql,mongodb,monolog
```

```php
$factory = (new Factory())->enableLogging();
// Will log to MySQL, MongoDB, AND Monolog simultaneously
```

### Direct Logger Injection (Advanced)

If you need to inject a custom PSR-3 logger directly (not common):

```php
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;

$customLogger = new YourPsrLogger();
$formatter = new MessageFormatter();
$middleware = Middleware::log($customLogger, $formatter);

$factory = (new Factory())->addMiddleware($middleware, 'custom_logging');
```

## Benefits of the New API

1. **Simpler Code**: One method instead of three
2. **Centralized Configuration**: All settings in `.env`
3. **Environment-Based**: Different configs for dev/staging/production
4. **Laravel Best Practices**: Follows Laravel's configuration patterns
5. **Multi-Logger Support**: Easy to enable logging to multiple destinations
6. **Less Coupling**: No need to pass connection details in code

## Test Updates

Tests have been updated to use the new API. Example:

**Before:**
```php
$factory = (new Factory())->enableDbLogging('127.0.0.1', 3306, 'jooclient');
```

**After:**
```php
$config = [
    'logging' => [
        'enabled' => true,
        'driver' => 'mysql',
        'connection' => [
            'mysql' => [
                'enabled' => true,
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'jooclient',
                'username' => 'root',
                'password' => 'root',
                'table' => 'client_request_logs',
                'batch' => false,
                'fallback' => 'error_log',
            ]
        ]
    ]
];
$factory = (new Factory())->enableLogging($config);
```

## Questions?

- See `README.md` for updated examples
- See `USAGE_GUIDE.md` for detailed usage patterns
- See `MULTI_LOGGER_GUIDE.md` for multi-logger setup
- See `MONGODB_CONFIG_GUIDE.md` for MongoDB configuration

## Backward Compatibility

⚠️ **Breaking Change**: The old `enableDbLogging()`, `enableMongoDbLogging()`, and direct logger injection via `enableLogging(LoggerInterface $logger)` methods have been removed. You must migrate to the new config-based API.

If you have custom code that needs direct logger injection, use `addMiddleware()` as shown in the "Advanced Usage" section above.


