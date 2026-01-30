# JOOClient Usage Guide

Complete guide for using JOOClient in Laravel 12 applications.

## Table of Contents

1. [Installation](#installation)
2. [Basic Usage](#basic-usage)
3. [Logging Drivers](#logging-drivers)
4. [Advanced Features](#advanced-features)
5. [Testing](#testing)
6. [Performance Tuning](#performance-tuning)
7. [Troubleshooting](#troubleshooting)

---

## Installation

### Step 1: Install Package

```bash
composer require jooservices/jooclient
```

### Step 2: Publish Configuration

```bash
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=config
```

This creates `config/jooclient.php` in your Laravel application.

### Step 3: Configure Environment

Add to your `.env`:

```env
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=mysql  # or mongodb, monolog
JOOCLIENT_DB_LOGGING=true
JOOCLIENT_DB_DATABASE=your_database
```

### Step 4: Run Migrations (MySQL only)

```bash
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=migrations
php artisan migrate
```

---

## Basic Usage

### Method 1: Via Service Container (Recommended)

```php
namespace App\Http\Controllers;

use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Contracts\HttpClientInterface;

class ApiController extends Controller
{
    public function __construct(
        private ClientBuilder $builder
    ) {}

    public function fetchUsers()
    {
        $client = $this->builder->build();

        try {
            $response = $result->get('https://api.example.com/users');
            $users = $response->json();

            return response()->json($users);
        } catch (\GuzzleHttp\Exception\GuzzleException $e) {
            // Exception is automatically logged
            return response()->json(['error' => 'Failed to fetch users'], 500);
        }
    }
}
```

### Method 2: Via Helper Function

```php
// Assuming a provider registers ClientBuilder or returns a built client
$client = app('jooclient');
$response = $client->get('/api/data');
```

### Method 3: Direct Instantiation

```php
use JOOservices\Client\Client\ClientBuilder;

$builder = ClientBuilder::create()
    ->withTimeout(30)
    // ->withRetry(...) // Retry is configured via middleware, not direct method in basic example unless using withRetry
    ;

$client = $builder->build();
$response = $client->post('/api/create', [
    'json' => ['name' => 'John Doe']
]);
```

---

## Logging Drivers

### MySQL Logging

Best for: Relational queries. (Not yet implemented)





### MongoDB Logging

Best for: High write volume. (Not yet implemented)





### Monolog Logging

Best for: Development, file-based logs, simple setups

```php
// Via config
'logging' => [
    'enabled' => true,
    'driver' => 'monolog',
],
'logging_options' => [
    'monolog' => [
        'channel' => 'api_client',
        'file' => storage_path('logs/api-client.log'),
        'level' => 'debug',
        'formatter' => 'json',
    ],
],
```

Or via code:
```php
$builder = ClientBuilder::create()
    ->withDefaultLogging('api_client', '/path/to/log.log');
```

---

## Advanced Features

### Retry Logic

Automatically retry failed requests with exponential backoff:

```php
$factory = $factory->enableRetries(
    maxRetries: 3,        // Max retry attempts
    delayInSec: 2,        // Base delay (multiplied by attempt number)
    minErrorCode: 500     // Only retry 5xx errors
);

$result = $factory->make();
$response = $result->get('/unstable-api');
// Automatically retries on 500, 502, 503, etc.
```

**Retry behavior:**
- Attempt 1 fails (500) → wait 2 seconds
- Attempt 2 fails (502) → wait 4 seconds
- Attempt 3 fails (503) → wait 6 seconds
- Give up after max attempts

### Batch Mode

For high-traffic applications, batch database writes:

```php
// config/jooclient.php
'logging' => [
    'connection' => [
        'mysql' => [
            'batch' => true,
        ],
    ],
],
```

**Important**: Always flush at request end:

```php
public function handle($request, Closure $next)
{
    $result = app('jooclient')->make();

    // ... use client ...

    $response = $next($request);

    // Flush before response sent
    $result->flushLogger();

    return $response;
}
```

Or use Laravel middleware:

```php
class FlushJooclientLogs
{
    public function terminate($request, $response)
    {
        if (app()->bound('jooclient.result')) {
            app('jooclient.result')->flushLogger();
        }
    }
}
```

### Custom Middleware

```php
$rateLimitMiddleware = function (callable $handler) {
    return function ($request, $options) use ($handler) {
        // Check rate limit
        if (!RateLimiter::attempt('api', 60, 1)) {
            throw new \Exception('Rate limit exceeded');
        }

        return $handler($request, $options);
    };
};

$factory = $factory->addMiddleware($rateLimitMiddleware, 'rate_limit');
```

### Cache Middleware

```php
use Illuminate\Support\Facades\Cache;

$cacheMiddleware = function (callable $handler) {
    return function ($request, $options) use ($handler) {
        // Only cache GET requests
        if ($request->getMethod() !== 'GET') {
            return $handler($request, $options);
        }

        $cacheKey = 'http_cache:' . md5((string)$request->getUri());

        // Check cache
        if ($cached = Cache::get($cacheKey)) {
            return \GuzzleHttp\Promise\Create::promiseFor(
                new \GuzzleHttp\Psr7\Response(200, [], $cached)
            );
        }

        // Make request and cache
        return $handler($request, $options)->then(
            function ($response) use ($cacheKey) {
                Cache::put($cacheKey, (string)$response->getBody(), 3600);
                return $response;
            }
        );
    };
};

$factory = $factory->enableCache($cacheMiddleware);
```

---

## Testing

### Unit Testing with Mocks

```php
namespace Tests\Feature;

use JOOservices\Client\Factory\Factory;
use GuzzleHttp\Psr7\Response;
use Tests\TestCase;

class ApiServiceTest extends TestCase
{
    public function test_fetches_users_successfully()
    {
        $factory = (new Factory())
            ->fakeResponses([
                new Response(200, [], json_encode([
                    ['id' => 1, 'name' => 'John'],
                    ['id' => 2, 'name' => 'Jane'],
                ]))
            ]);

        $result = $factory->make();
        $response = $result->get('/users');

        $users = json_decode($response->getBody(), true);

        $this->assertCount(2, $users);
        $this->assertEquals('John', $users[0]['name']);
    }

    public function test_handles_api_errors()
    {
        $factory = (new Factory())
            ->fakeResponses([
                new \GuzzleHttp\Exception\ServerException(
                    'Server error',
                    new \GuzzleHttp\Psr7\Request('GET', '/error'),
                    new Response(500, [], 'Internal Server Error')
                )
            ]);

        $result = $factory->make();

        $this->expectException(\GuzzleHttp\Exception\ServerException::class);
        $result->get('/error');
    }

    public function test_request_history_is_captured()
    {
        $factory = (new Factory())
            ->fakeResponses([
                new Response(200, [], 'OK'),
                new Response(201, [], 'Created'),
            ]);

        $result = $factory->make();

        $result->get('/test1');
        $result->post('/test2');

        $history = $result->getHistory();

        $this->assertCount(2, $history);
        $this->assertEquals('GET', $history[0]['request']->getMethod());
        $this->assertEquals('POST', $history[1]['request']->getMethod());
    }
}
```

---

## Performance Tuning

### 1. Enable Batch Mode

For applications making >100 requests/minute:

```env
JOOCLIENT_DB_BATCH=true
JOOCLIENT_MONGODB_BATCH=true
```

**Throughput improvement**: ~10x faster (500 inserts/transaction vs 1)

### 2. Schema Caching

Schema is automatically cached. Clear if schema changes:

```php
\JOOservices\Client\Repositories\ClientRequestLogRepository::clearCache();
```

### 3. Connection Pooling

Package uses Laravel's connection pooling automatically.

### 4. Adjust Buffer Sizes

Default: 1000 entries max

To adjust, extend `LogBuffer`:

```php
class LargeLogBuffer extends LogBuffer
{
    public function __construct()
    {
        parent::__construct(maxSize: 5000);
    }
}
```

### 5. Optimize Body Logging

Large response bodies can impact performance:

```php
// Implement custom extractor to skip large bodies
class OptimizedExtractor extends RequestResponseExtractor
{
    private function extractBody($message): ?string
    {
        $body = (string)$message->getBody();

        // Skip bodies > 10KB
        if (strlen($body) > 10240) {
            return '[body too large]';
        }

        return parent::extractBody($message);
    }
}
```

---

## Troubleshooting

### Logs Not Being Saved

**Check 1**: Database connection

```php
// In tinker
\Illuminate\Support\Facades\DB::connection()->getPdo();
```

**Check 2**: Table exists

```bash
php artisan migrate:status
```

**Check 3**: Logging enabled in config

```php
// config/jooclient.php
'logging' => [
    'enabled' => true,  // Must be true
    'connection' => [
        'mysql' => [
            'enabled' => true,  // Must be true
        ],
    ],
],
```

**Check 4**: Flush is being called (batch mode)

```php
$result->flushLogger(); // Add this!
```

**Check 5**: Check failure logs

```bash
cat /tmp/jooclient_db_logger_failures.log
cat /tmp/jooclient_mongodb_logger_failures.log
```

### Memory Issues

**Symptom**: PHP memory limit exceeded

**Solutions**:

1. Disable body logging for large responses
2. Enable batch mode and flush frequently
3. Increase buffer size limit
4. Use MongoDB (better for large documents)

### Connection Errors

**MySQL**: "Operation not permitted"
- Check MySQL is running
- Verify credentials in .env
- Check firewall settings

**MongoDB**: "Connection refused"
- Verify MongoDB is running: `mongosh`
- Check DSN format
- Verify extension loaded: `php -m | grep mongodb`

### Circular Reference Errors

Package automatically detects and handles circular references.
If you see `[circular_reference]` in logs, it's working correctly.

---

## Real-World Examples

### Example 1: API Client Service

```php
namespace App\Services;

use JOOservices\Client\Factory\Factory;
use GuzzleHttp\Exception\GuzzleException;

class ExternalApiService
{
    private Factory $factory;

    public function __construct(Factory $jooclient)
    {
        $this->factory = $jooclient;
    }

    public function fetchUserData(int $userId): ?array
    {
        $result = $this->factory->make();

        try {
            $response = $result->get("/api/users/{$userId}");

            return json_decode($response->getBody(), true);
        } catch (GuzzleException $e) {
            \Log::error('Failed to fetch user data', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);

            return null;
        } finally {
            $result->flushLogger();
        }
    }

    public function createUser(array $data): ?int
    {
        $result = $this->factory->make();

        try {
            $response = $result->post('/api/users', [
                'json' => $data,
                'headers' => [
                    'Authorization' => 'Bearer ' . config('services.api.token')
                ]
            ]);

            $created = json_decode($response->getBody(), true);

            return $created['id'] ?? null;
        } catch (GuzzleException $e) {
            \Log::error('Failed to create user', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);

            return null;
        } finally {
            $result->flushLogger();
        }
    }
}
```

### Example 2: Webhook Service

```php
namespace App\Services;

use JOOservices\Client\Factory\Factory;

class WebhookService
{
    public function __construct(private Factory $jooclient) {}

    public function sendWebhook(string $url, array $payload): bool
    {
        // Configure with retries for reliability
        $factory = $this->jooclient
            ->addOptions([
                'timeout' => 10,
                'connect_timeout' => 3,
            ])
            ->enableRetries(3, 2, 500);

        $result = $factory->make();

        try {
            $response = $result->post($url, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $this->generateSignature($payload),
                ]
            ]);

            return $response->getStatusCode() < 300;
        } catch (\Exception $e) {
            // Logged automatically
            return false;
        } finally {
            $result->flushLogger();
        }
    }

    private function generateSignature(array $payload): string
    {
        return hash_hmac('sha256', json_encode($payload), config('app.key'));
    }
}
```

### Example 3: Background Job

```php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use JOOservices\Client\Factory\Factory;

class FetchExternalDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $endpoint
    ) {}

    public function handle(Factory $jooclient): void
    {
        // Enable batch mode for better performance in background jobs
        $factory = $jooclient->addOptions([
            'timeout' => 60, // Longer timeout for background
        ]);

        $result = $factory->make();

        try {
            $response = $result->get($this->endpoint);
            $data = json_decode($response->getBody(), true);

            // Process data
            $this->processData($data);
        } catch (\Exception $e) {
            // Automatically logged
            $this->fail($e);
        } finally {
            // Important: Flush logs before job completes
            $result->flushLogger();
        }
    }

    private function processData(array $data): void
    {
        // Processing logic
    }
}
```

---

## Monitoring & Analytics

### Dashboard Queries (MySQL)

```php
// Success rate
$total = ClientRequestLog::count();
$successful = ClientRequestLog::where('response_status', '<', 400)->count();
$successRate = ($successful / $total) * 100;

// Average response time (if tracked)
$avgTime = ClientRequestLog::whereDate('created_at', today())
    ->avg('response_time');

// Top failing endpoints
$failing = ClientRequestLog::where('response_status', '>=', 500)
    ->select('path', DB::raw('count(*) as count'))
    ->groupBy('path')
    ->orderBy('count', 'desc')
    ->limit(10)
    ->get();

// Requests per hour
$hourly = ClientRequestLog::whereDate('created_at', today())
    ->select(DB::raw('HOUR(created_at) as hour'), DB::raw('count(*) as count'))
    ->groupBy('hour')
    ->get();
```

### Alert on Error Threshold

```php
// In a scheduled command
namespace App\Console\Commands;

use Illuminate\Console\Command;
use JOOservices\Client\Models\ClientRequestLog;
use Illuminate\Support\Facades\Mail;

class CheckApiHealth extends Command
{
    protected $signature = 'api:check-health';

    public function handle()
    {
        $errorCount = ClientRequestLog::where('level', 'error')
            ->whereBetween('created_at', [now()->subHour(), now()])
            ->count();

        if ($errorCount > 100) {
            // Send alert
            Mail::to('dev@example.com')->send(
                new \App\Mail\ApiHealthAlert($errorCount)
            );

            $this->error("High error rate: {$errorCount} errors in last hour");
        } else {
            $this->info("API health OK: {$errorCount} errors in last hour");
        }
    }
}
```

---

## Security Best Practices

### 1. Redact Sensitive Data

```php
class SecureExtractor extends \JOOservices\Client\Logging\Extractors\RequestResponseExtractor
{
    private const SENSITIVE_HEADERS = ['Authorization', 'X-API-Key', 'Cookie'];

    public function extractRequestData($request, array $row): array
    {
        $row = parent::extractRequestData($request, $row);

        if (isset($row['request_headers'])) {
            $headers = json_decode($row['request_headers'], true);

            foreach (self::SENSITIVE_HEADERS as $header) {
                if (isset($headers[$header])) {
                    $headers[$header] = ['[REDACTED]'];
                }
            }

            $row['request_headers'] = json_encode($headers);
        }

        return $row;
    }
}

// Register in service provider
$this->app->bind(
    \JOOservices\Client\Logging\Contracts\RequestResponseExtractorInterface::class,
    SecureExtractor::class
);
```

### 2. Disable Body Logging for Sensitive Endpoints

```php
// Implement custom logger that checks paths
```

### 3. Rotate Logs

```php
// Schedule in app/Console/Kernel.php
$schedule->command('db:prune', ['--model' => 'JOOservices\Client\Models\ClientRequestLog'])
    ->daily();
```

Or manually:

```php
// Delete logs older than 30 days
ClientRequestLog::where('created_at', '<', now()->subDays(30))->delete();
```

---

## Configuration Reference

See `config/jooclient.php` for all available options with detailed comments.

Key sections:
- `logging`: Enable/disable and driver selection
- `logging.connection.mysql`: MySQL-specific settings
- `logging.connection.mongodb`: MongoDB-specific settings
- `logging_options.monolog`: Monolog-specific settings
- `retries`: Retry behavior configuration
- `defaults`: Default Guzzle options

---

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


