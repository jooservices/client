# JOOClient Usage Guide

Complete guide for using JOOClient in PHP applications.

## Table of Contents

1. [Installation](#installation)
2. [Basic Usage](#basic-usage)
3. [Logging](#logging)
4. [Advanced Features](#advanced-features)
5. [Testing](#testing)
6. [Troubleshooting](#troubleshooting)

---

## Installation

### Step 1: Install Package

```bash
composer require jooservices/jooclient
```

### Step 2: Configure

Configuration is handled programmatically via `ClientBuilder` or through your application's environment/config injection.

---

## Basic Usage

### Method 1: Direct Instantiation (Simplest)

```php
use JOOservices\Client\Client\ClientBuilder;

// minimalist
$client = ClientBuilder::create()->build();
$response = $client->get('https://api.github.com');
```

### Method 2: With Configuration

```php
$builder = ClientBuilder::create()
    ->withBaseUri('https://api.example.com/v1')
    ->withTimeout(30)
    ->withHeader('User-Agent', 'MyApp/1.0');

$client = $builder->build();
```

### Method 3: Via Dependency Injection (e.g. Laravel/Symfony)

Define the service in your container:

```php
// AppServiceProvider.php (Laravel example)
$this->app->bind(ClientBuilder::class, function () {
    return ClientBuilder::create()
        ->withTimeout(30)
        ->withDefaultLogging('my-app');
});
```

Inject and use:

```php
class ApiController
{
    public function __construct(
        private ClientBuilder $builder
    ) {}

    public function index()
    {
        $client = $this->builder->build();
        return $client->get('/users')->json();
    }
}
```

---

## Logging

JOOClient supports Monolog for logging request details.

### Enable Logging

```php
$client = ClientBuilder::create()
    ->withDefaultLogging('api-client', '/path/to/logs/client.log')
    ->build();
```

This will log:
- Request method, URI, headers
- Response status, duration
- Request/Response bodies (if enabled)

### Custom Logger

You can pass any PSR-3 logger:

```php
use Monolog\Logger;

$logger = new Logger('custom');
// ... configure handlers ...

$client = ClientBuilder::create()
    ->withLogger($logger, logBodies: true) // Set true to log payloads
    ->build();
```

---

## Advanced Features

### Retry Logic

Automatically retry failed requests using `RetryMiddleware`.

```php
use JOOservices\Client\Resilience\RetryConfig;

$client = ClientBuilder::create()
    ->withRetry(new RetryConfig(
        maxAttempts: 3,
        delaySeconds: 1,
        minErrorCode: 500
    ))
    ->build();
```

### Caching

Cache GET responses using any PSR-16 SimpleCache implementation.

```php
// $cache is a PSR-16 instance (e.g. from Laravel Cache::store())
$client = ClientBuilder::create()
    ->withCache($cache, defaultTtl: 3600)
    ->build();
```

### Circuit Breaker

Prevent cascading failures by stopping requests to failing services.

```php
use JOOservices\Client\Resilience\CircuitBreakerConfig;

$client = ClientBuilder::create()
    ->withCircuitBreaker(new CircuitBreakerConfig(
        failureThreshold: 5,
        recoveryTimeout: 60
    ))
    ->build();
```

---

## Testing

For testing, you might want to mock the underlying Guzzle handler or inject a pre-configured client.

### Mocking Responses (Guzzle Style)

Since `JOOClient` wraps Guzzle, you can use Guzzle's `MockHandler`.

```php
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\HandlerStack;
use JOOservices\Client\Client\ClientBuilder;

$mock = new MockHandler([
    new Response(200, [], json_encode(['id' => 1])),
    new Response(403, [], 'Forbidden'),
]);

$handlerStack = HandlerStack::create($mock);

$client = ClientBuilder::create()
    ->withOption('handler', $handlerStack) // Inject handler
    ->build();

// First request matches first mock
$status = $client->get('/test')->status(); // 200
```

---

## Troubleshooting

### Logs Not Appearing
- Ensure `withDefaultLogging` or `withLogger` is called.
- Check file permissions if using file logging.
- Verify `logBodies` is true if you expect payload logs.

### Middleware Order
- `ClientBuilder` adds middleware in a specific order:
    1. Interceptors (User callbacks)
    2. Logging
    3. Retry (Wraps network)
    4. Circuit Breaker
    5. Cache
    (Note: Actual execution order depends on internal pipeline structure)

---

## Real-World Example

```php
class StripeService
{
    private $client;

    public function __construct()
    {
        $this->client = ClientBuilder::create()
            ->withBaseUri('https://api.stripe.com/v1/')
            ->withHeader('Authorization', 'Bearer ' . getenv('STRIPE_KEY'))
            ->withRetry(new RetryConfig(maxAttempts: 3))
            ->withDefaultLogging('stripe')
            ->build();
    }

    public function getCustomer(string $id)
    {
        $response = $this->client->get("customers/{$id}");
        
        if ($response->status() === 404) {
            return null;
        }

        return $response->json();
    }
}
```

## FAQ

**Q: Can I use multiple logging drivers?**
A: Not simultaneously. Choose one driver per application. You can query both MySQL and MongoDB if you switch between deployments.

**Q: How do I disable logging temporarily?**
A: Set `JOOCLIENT_LOGGING_ENABLED=false` in `.env` or:
```php
$factory = app('jooclient'); // Logging disabled if config says so
```

**Q: Can I log to custom fields?**
A: Yes, implement `RequestResponseExtractorInterface` and bind it in your service provider.

**Q: What's the performance impact?**
A: Minimal with batch mode (~1-2ms per request). Without batching, ~5-10ms per request.

**Q: Can I use this outside Laravel?**
A: No, this package is specifically designed for Laravel 12. For standalone, use Guzzle directly with Monolog.

---

## Additional Resources

- [Architecture Documentation](ARCHITECTURE.md)
- [Laravel Documentation](https://laravel.com/docs/12.x)
- [Guzzle Documentation](https://docs.guzzlephp.org)
- [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/)
- [MongoDB PHP Library](https://www.mongodb.com/docs/php-library/current/)

---

**Need Help?** Contact the development team or consult the architecture documentation for implementation details.


