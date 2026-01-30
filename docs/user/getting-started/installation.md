# Installation

Install and configure JOOClient in your Laravel 12 application.

## Requirements

- PHP 8.5+
- Laravel 12.x
- MySQL 8.0+ OR MongoDB 6.0+ (optional, for logging)
- Redis 6.0+ OR Filesystem storage (optional, for caching)

## Step 1: Install Package

```bash
composer require jooservices/jooclient
```

## Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=config
```

This creates `config/jooclient.php` in your Laravel application.

## Step 3: Publish Migrations (MySQL Logging Only)

If you plan to use MySQL logging:

```bash
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=migrations
php artisan migrate
```

## Step 4: Configure Environment

Add to your `.env` file:

```env
# Logging (Main Settings)
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=mysql  # mysql, mongodb, monolog, or multi

# MySQL Logging
JOOCLIENT_DB_LOGGING=true
JOOCLIENT_DB_HOST=127.0.0.1
JOOCLIENT_DB_PORT=3306
JOOCLIENT_DB_DATABASE=jooclient
JOOCLIENT_DB_USERNAME=root
JOOCLIENT_DB_PASSWORD=secret
JOOCLIENT_DB_TABLE=client_request_logs

# MongoDB Logging (Optional)
JOOCLIENT_MONGODB_LOGGING=true
JOOCLIENT_MONGODB_DSN=mongodb://127.0.0.1:27017
JOOCLIENT_MONGODB_DATABASE=jooclient
JOOCLIENT_MONGODB_COLLECTION=client_request_logs

# Caching (Optional)
JOOCLIENT_CACHE_ENABLED=true
JOOCLIENT_CACHE_DRIVER=redis  # redis or filesystem
JOOCLIENT_CACHE_TTL=3600

# Redis Cache (when driver=redis)
JOOCLIENT_REDIS_HOST=127.0.0.1
JOOCLIENT_REDIS_PORT=6379
JOOCLIENT_REDIS_PASSWORD=
JOOCLIENT_REDIS_DATABASE=0

# Retries (Optional)
JOOCLIENT_RETRIES=true
JOOCLIENT_RETRIES_MAX=3
JOOCLIENT_RETRIES_DELAY=1
JOOCLIENT_RETRIES_MIN_ERROR_CODE=500
```

## Step 5: Verify Installation

Create a simple test:

```php
use JOOservices\Client\Factory\Factory;

$factory = new Factory();
$result = $factory->make();
$response = $result->get('https://api.github.com');

echo "Status: " . $response->getStatusCode() . "\n";
```

If you see a status code (200, 404, etc.), installation is successful!

## Next Steps

- **[Configuration](configuration.md)** - Learn about configuration options
- **[First Request](first-request.md)** - Make your first API call
- **[Feature Guides](../guides/)** - Explore all features

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
