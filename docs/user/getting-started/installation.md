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

## Step 1: Install Package

```bash
composer require jooservices/jooclient
```

## Step 2: Configure Environment

Add to your `.env` file (if using dotenv):

```env
# Logging (Main Settings)
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=monolog  # monolog is the default

# Retries (Optional)
JOOCLIENT_RETRIES=true
JOOCLIENT_RETRIES_MAX=3
JOOCLIENT_RETRIES_DELAY=1
JOOCLIENT_RETRIES_MIN_ERROR_CODE=500
```

## Step 3: Verify Installation

Create a simple test:

```php
use JOOservices\Client\Client\ClientBuilder;

$builder = ClientBuilder::create();
$client = $builder->build();
$response = $client->get('https://api.github.com');

echo "Status: " . $response->status() . "\n";
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
