# Tracing & Distributed Logging Guide

This guide explains how to use the comprehensive tracing features in `jooclient` for distributed systems debugging and monitoring.

## Overview

The logging system captures detailed tracing information for every HTTP request, enabling you to:

- **Track requests across services** using correlation IDs
- **Analyze performance** with detailed timing and size metrics
- **Debug issues** with error classification and retry tracking
- **Monitor cache efficiency** with cache hit/miss tracking
- **Security analysis** with IP address and user-agent tracking

## Captured Fields

### Request Tracing Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `correlation_id` | string | Correlation ID for distributed tracing | `abc-123-xyz` |
| `request_ip` | string | Client IP address (supports IPv6) | `192.168.1.1` |
| `user_agent` | string | User-Agent header value | `MyApp/1.0.0` |
| `request_size_bytes` | int | Total request size including headers | `1024` |

### Response Tracing Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `response_headers` | JSON | Response headers as JSON object | `{"Content-Type": ["application/json"]}` |
| `response_size_bytes` | int | Total response size including headers | `2048` |

### Performance Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `duration_ms` | float | Request duration in milliseconds | `150.5` |
| `retry_count` | int | Number of retry attempts | `2` |
| `cache_hit` | bool | Whether response was served from cache | `true` |

### Error Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `error_type` | string | Type of error | `timeout`, `connection_error`, `http_server_error` |
| `error_message` | string | Error message | `Connection timeout` |

## Basic Usage

### Enable Logging with Tracing

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableLogging([
        'logging' => [
            'enabled' => true,
            'driver' => 'mysql',
            'mysql' => [
                'connection' => [
                    'host' => 'localhost',
                    'database' => 'app_db',
                    'table' => 'client_request_logs',
                ],
            ],
        ],
    ]);

$client = $factory->make()->client;
```

### Automatic Correlation ID Extraction

The logger automatically extracts correlation IDs from common headers:

- `X-Correlation-ID`
- `X-Request-ID`
- `X-Trace-ID`
- `Correlation-ID`
- `Request-ID`

```php
// Set correlation ID in request
$response = $client->get('https://api.example.com/users', [
    'headers' => [
        'X-Correlation-ID' => 'my-trace-123',
    ],
]);

// Correlation ID is automatically captured in logs
```

### Automatic IP Address Extraction

The logger extracts client IP from headers in order of preference:

1. `X-Forwarded-For` (takes first IP if multiple)
2. `X-Real-IP`
3. `X-Client-IP`
4. `CF-Connecting-IP` (Cloudflare)
5. `True-Client-IP` (Cloudflare Enterprise)

Or you can set it explicitly:

```php
$response = $client->get('https://api.example.com/users', [
    'client_ip' => '203.0.113.42', // Explicitly set IP
]);
```

## Querying Logs

### Find Requests by Correlation ID

```php
use JOOservices\Client\Models\ClientRequestLog;

// Find all requests in a trace
$trace = ClientRequestLog::where('correlation_id', 'my-trace-123')
    ->orderBy('created_at')
    ->get();

foreach ($trace as $log) {
    echo "{$log->method} {$log->path} - {$log->duration_ms}ms\n";
}
```

### Find Requests by IP Address

```php
// Find all requests from specific IP in last 24 hours
$requests = ClientRequestLog::where('request_ip', '192.168.1.1')
    ->where('created_at', '>=', now()->subDay())
    ->get();
```

### Performance Analysis

```php
// Find slow requests (> 1 second)
$slowRequests = ClientRequestLog::where('duration_ms', '>', 1000)
    ->orderBy('duration_ms', 'desc')
    ->limit(100)
    ->get();

// Calculate average response time
$avgDuration = ClientRequestLog::where('created_at', '>=', now()->subDay())
    ->avg('duration_ms');
```

### Error Analysis

```php
// Find all timeout errors
$timeouts = ClientRequestLog::where('error_type', 'timeout')
    ->where('created_at', '>=', now()->subDay())
    ->get();

// Find all server errors (5xx)
$serverErrors = ClientRequestLog::where('error_type', 'http_server_error')
    ->get();

// Group errors by type
$errorStats = ClientRequestLog::whereNotNull('error_type')
    ->selectRaw('error_type, COUNT(*) as count')
    ->groupBy('error_type')
    ->get();
```

### Cache Efficiency Analysis

```php
// Calculate cache hit rate
$totalRequests = ClientRequestLog::where('created_at', '>=', now()->subDay())
    ->count();

$cacheHits = ClientRequestLog::where('created_at', '>=', now()->subDay())
    ->where('cache_hit', true)
    ->count();

$hitRate = ($cacheHits / $totalRequests) * 100;
echo "Cache hit rate: {$hitRate}%\n";
```

### Retry Analysis

```php
// Find requests that required retries
$retriedRequests = ClientRequestLog::where('retry_count', '>', 0)
    ->orderBy('retry_count', 'desc')
    ->get();

// Calculate average retry count
$avgRetries = ClientRequestLog::where('created_at', '>=', now()->subDay())
    ->avg('retry_count');
```

## Distributed Tracing

### Setting Up Correlation IDs

In a microservices architecture, propagate correlation IDs across services:

```php
// Service A: Generate correlation ID
$correlationId = bin2hex(random_bytes(16));

$response = $client->post('https://service-b.example.com/api', [
    'headers' => [
        'X-Correlation-ID' => $correlationId,
    ],
    'json' => ['action' => 'process'],
]);

// Service B: Extract and use correlation ID
$correlationId = $request->header('X-Correlation-ID');

// Service B makes request to Service C with same correlation ID
$response = $client->post('https://service-c.example.com/api', [
    'headers' => [
        'X-Correlation-ID' => $correlationId, // Propagate
    ],
]);
```

### Tracing a Request Across Services

```php
// Find all requests in a distributed trace
$correlationId = 'trace-abc-123';

$allRequests = ClientRequestLog::where('correlation_id', $correlationId)
    ->orderBy('created_at')
    ->get();

// Build trace timeline
foreach ($allRequests as $log) {
    echo sprintf(
        "[%s] %s %s - %s - %sms\n",
        $log->created_at->format('H:i:s.u'),
        $log->method,
        $log->path,
        $log->response_status,
        $log->duration_ms
    );
}
```

## Performance Monitoring

### Track Request Sizes

```php
// Find large requests
$largeRequests = ClientRequestLog::where('request_size_bytes', '>', 100000)
    ->orderBy('request_size_bytes', 'desc')
    ->get();

// Find large responses
$largeResponses = ClientRequestLog::where('response_size_bytes', '>', 1000000)
    ->get();
```

### Monitor Response Times

```php
// P95 response time
$p95 = ClientRequestLog::where('created_at', '>=', now()->subDay())
    ->orderBy('duration_ms')
    ->skip((int) (ClientRequestLog::count() * 0.95))
    ->first()
    ->duration_ms;

// Response time percentiles
$percentiles = [50, 75, 90, 95, 99];
foreach ($percentiles as $p) {
    $value = ClientRequestLog::where('created_at', '>=', now()->subDay())
        ->orderBy('duration_ms')
        ->skip((int) (ClientRequestLog::count() * ($p / 100)))
        ->first()
        ->duration_ms;
    
    echo "P{$p}: {$value}ms\n";
}
```

## Error Classification

The logger automatically classifies errors:

| Error Type | Description |
|------------|-------------|
| `http_server_error` | HTTP 5xx responses |
| `http_client_error` | HTTP 4xx responses |
| `connection_error` | Connection failures |
| `timeout` | Request timeouts |
| `request_error` | Request-level errors |
| `transfer_error` | Transfer-level errors |
| `exception` | General exceptions |

### Query by Error Type

```php
// Find all connection errors
$connectionErrors = ClientRequestLog::where('error_type', 'connection_error')
    ->get();

// Find errors by status code range
$serverErrors = ClientRequestLog::where('response_status', '>=', 500)
    ->where('response_status', '<', 600)
    ->get();
```

## Advanced Queries

### Complex Filtering

```php
// Find slow requests from specific IP with errors
$results = ClientRequestLog::where('request_ip', '192.168.1.1')
    ->where('duration_ms', '>', 1000)
    ->whereNotNull('error_type')
    ->where('created_at', '>=', now()->subHour())
    ->get();
```

### Aggregations

```php
// Group by endpoint and calculate stats
$stats = ClientRequestLog::where('created_at', '>=', now()->subDay())
    ->selectRaw('
        path,
        COUNT(*) as request_count,
        AVG(duration_ms) as avg_duration,
        MAX(duration_ms) as max_duration,
        SUM(CASE WHEN error_type IS NOT NULL THEN 1 ELSE 0 END) as error_count
    ')
    ->groupBy('path')
    ->get();
```

### Time-based Analysis

```php
// Requests per hour
$hourly = ClientRequestLog::where('created_at', '>=', now()->subDay())
    ->selectRaw('
        DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour,
        COUNT(*) as count,
        AVG(duration_ms) as avg_duration
    ')
    ->groupBy('hour')
    ->orderBy('hour')
    ->get();
```

## Best Practices

### 1. Always Set Correlation IDs

```php
// Generate correlation ID at request entry point
$correlationId = bin2hex(random_bytes(16));

// Pass to all downstream services
$response = $client->get('https://api.example.com/data', [
    'headers' => ['X-Correlation-ID' => $correlationId],
]);
```

### 2. Monitor Key Metrics

```php
// Set up alerts for:
// - High error rates
// - Slow response times (P95 > threshold)
// - High retry counts
// - Low cache hit rates
```

### 3. Regular Cleanup

```php
// Archive old logs (e.g., older than 90 days)
ClientRequestLog::where('created_at', '<', now()->subDays(90))
    ->delete();
```

### 4. Index Optimization

Ensure indexes are created for common queries:

```sql
CREATE INDEX idx_correlation_id ON client_request_logs(correlation_id);
CREATE INDEX idx_request_ip ON client_request_logs(request_ip);
CREATE INDEX idx_duration_ms ON client_request_logs(duration_ms);
CREATE INDEX idx_created_at ON client_request_logs(created_at);
CREATE INDEX idx_error_type ON client_request_logs(error_type);
```

## MongoDB Logging

All tracing fields are also available when using MongoDB logging:

```php
$factory = (new Factory())
    ->enableLogging([
        'logging' => [
            'enabled' => true,
            'driver' => 'mongodb',
            'mongodb' => [
                'connection' => [
                    'dsn' => 'mongodb://localhost:27017',
                    'database' => 'app_logs',
                    'collection' => 'request_logs',
                ],
            ],
        ],
    ]);
```

Query MongoDB logs:

```php
use MongoDB\Client;

$client = new Client('mongodb://localhost:27017');
$collection = $client->app_logs->request_logs;

// Find by correlation ID
$logs = $collection->find(['correlation_id' => 'trace-123']);

// Find slow requests
$slow = $collection->find([
    'duration_ms' => ['$gt' => 1000],
])->sort(['duration_ms' => -1]);
```

## See Also

- [Logging Guide](./LOGGING_GUIDE.md) - General logging configuration
- [Error Handling Guide](./ERROR_HANDLING_GUIDE.md) - Error handling patterns
- [Performance Guide](./PERFORMANCE_GUIDE.md) - Performance optimization

