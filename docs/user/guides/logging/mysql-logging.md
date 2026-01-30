# MySQL Logging

Log HTTP requests and responses to MySQL database.

## Overview

MySQL logging stores all HTTP requests and responses in a MySQL database table, allowing you to query, analyze, and monitor API calls.

## Configuration (two paths)

### A) With Laravel (follows Laravel config)
1. Install & publish:
```bash
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=config
php artisan vendor:publish --provider="JOOservices\Client\Providers\JooclientServiceProvider" --tag=migrations
php artisan migrate
```
2. Set `.env` (defaults fall back to `config('database.connections.mysql.*')`):
```env
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=mysql
JOOCLIENT_DB_HOST=127.0.0.1
JOOCLIENT_DB_PORT=3306
JOOCLIENT_DB_DATABASE=jooclient
JOOCLIENT_DB_USERNAME=root
JOOCLIENT_DB_PASSWORD=secret
JOOCLIENT_DB_TABLE=client_request_logs
JOOCLIENT_DB_BATCH=true
JOOCLIENT_DB_FALLBACK=error_log # error_log|throw|silent
```
3. Use (container-resolved):
```php
public function __construct(private Factory $jooclient) {}

public function handle()
{
    $client = $this->jooclient->enableLogging()->make();
    $res = $client->get('https://api.example.com');
    $client->flushLogger();
}
```

### B) Without Laravel (Capsule + .env)
1. Bootstrap Capsule and load `.env` (see developer/setup/db-setup-without-laravel.md for full snippet).
2. Minimal `.env` keys:
```env
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=mysql
JOOCLIENT_DB_HOST=127.0.0.1
JOOCLIENT_DB_PORT=3306
JOOCLIENT_DB_DATABASE=jooclient
JOOCLIENT_DB_USERNAME=root
JOOCLIENT_DB_PASSWORD=secret
JOOCLIENT_DB_TABLE=client_request_logs
JOOCLIENT_DB_BATCH=false
JOOCLIENT_DB_FALLBACK=error_log
```
3. Enable via config array if you prefer code-based config:
```php
$config = [
    'logging' => [
        'enabled' => true,
        'driver' => 'mysql',
        'connection' => [
            'mysql' => [
                'enabled' => true,
                'host' => getenv('JOOCLIENT_DB_HOST'),
                'port' => getenv('JOOCLIENT_DB_PORT'),
                'database' => getenv('JOOCLIENT_DB_DATABASE'),
                'username' => getenv('JOOCLIENT_DB_USERNAME'),
                'password' => getenv('JOOCLIENT_DB_PASSWORD'),
                'table' => getenv('JOOCLIENT_DB_TABLE'),
                'batch' => getenv('JOOCLIENT_DB_BATCH') === 'true',
                'fallback' => getenv('JOOCLIENT_DB_FALLBACK') ?: 'error_log',
            ],
        ],
    ],
];
$factory = (new Factory())->enableLogging($config);
```

### Table requirement
- Ensure `client_request_logs` exists. In Laravel: `php artisan migrate`. Without Laravel: run the provided SQL (see developer/setup/db-setup-without-laravel.md).
- Batch mode buffers writes; call `flushLogger()` in long-lived processes.

## Usage

```php
$factory = (new Factory())->enableLogging();
$result = $factory->make();
$response = $result->get('https://api.example.com');

// Important: Flush logs if batch mode is enabled
$result->flushLogger();
```

## Querying Logs

```php
use JOOservices\Client\Models\ClientRequestLog;

// Get all failed requests
$failed = ClientRequestLog::where('response_status', '>=', 400)->get();

// Get requests to specific endpoint
$apiCalls = ClientRequestLog::where('path', 'like', '%/api/users%')
    ->whereDate('created_at', today())
    ->get();

// Get average response time
$avgTime = ClientRequestLog::whereDate('created_at', today())
    ->avg('response_time');
```

## Batch Mode

For high-traffic applications, enable batch mode:

```php
'batch' => true,  // Buffer logs in memory
```

**Important:** Always call `flushLogger()` at the end of requests.

## See Also

- **[Multi-Logger](multi-logger.md)** - Log to multiple destinations
- **[MongoDB Logging](mongodb-logging.md)** - Alternative logging driver

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
