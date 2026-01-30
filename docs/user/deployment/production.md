# Production Deployment Guide

This guide describes best practices for deploying JOOClient in a production environment.

## 1. Environment Configuration

Ensure your `.env` file is properly configured. Do NOT hardcode credentials in your code.

### Recommended Settings

```env
# Logging: Use a robust driver like MySQL or MongoDB
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=mysql

# Performance: Enable batch mode to reduce I/O blocking
JOOCLIENT_DB_BATCH=true
JOOCLIENT_MONGODB_BATCH=true

# Caching: Use Redis for speed; Filesystem is slower
JOOCLIENT_CACHE_ENABLED=true
JOOCLIENT_CACHE_DRIVER=redis

# Retries: Enable for resilience, but keep max attempts low (2-3)
JOOCLIENT_RETRIES=true
JOOCLIENT_RETRIES_MAX=3
JOOCLIENT_RETRIES_DELAY=1
```

## 2. Performance Optimization

### Batch Logging
In production, enabling batch logging is **critical** for performance. It delays writing logs to the database until the end of the request lifecycle or until the buffer is full (default 500 items).

```php
// config/jooclient.php
'logging' => [
    'connection' => [
        'mysql' => ['batch' => true],
        'mongodb' => ['batch' => true],
    ],
],
```

> **Note:** When using batch mode, ensure you call `$result->flushLogger()` at the end of long-running processes or queue workers.

### Caching
Use Redis (`predis` or `phpredis`) instead of the file driver.

1. Install Redis extension or Predis package.
2. Set `JOOCLIENT_CACHE_DRIVER=redis`.
3. Configure connection details in `.env`.

## 3. Security

### Sensitive Data Redaction
Verify that your sensitive data patterns cover all secrets used in your application.

```php
// config/jooclient.php
'logging' => [
    'sanitization' => [
        'enabled' => true,
        'keywords' => ['password', 'secret', 'api_key', 'token', 'card_number', 'cvv'],
    ],
],
```

### SSL Verification
**Never** disable SSL verification (`JOOCLIENT_VERIFY_SSL=false`) in production.

## 4. Maintenance

### Log Rotation (Pruning)
Logs accumulate quickly. Set up a scheduled task to prune old logs automatically.

Add this into `routes/console.php` or `app/Console/Kernel.php`:

```php
use Illuminate\Support\Facades\Schedule;

// Keep 30 days of logs
Schedule::command('jooclient:prune --days=30 --force')->dailyAt('02:00');
```

## 5. Monitoring

### Fallback Logging
If the primary log driver fails (e.g., Database is down), JOOClient writes to the system error log by default.
Monitor your server's error logs (e.g., `/var/log/nginx/error.log` or `/var/log/php-fpm/error.log`) for these fallback messages.

### Health Checks
Use the built-in health checks in your deployment pipeline or monitoring system:

```php
// Example: Create a /health route
Route::get('/health/jooclient', function (JOOservices\Client\Factory\Factory $factory) {
    try {
        // Attempt a simple DB write or select
        // ... implementation of check ...
        return response()->json(['status' => 'ok']);
    } catch (\Exception $e) {
        return response()->json(['status' => 'error'], 500);
    }
});
```

## 6. Deployment Checklist

- [ ] `.env` variables set (distinct from local/staging)
- [ ] `JOOCLIENT_VERIFY_SSL` is `true`
- [ ] Batch logging is `true` for high-volume endpoints
- [ ] Redis cache is configured (if caching is used)
- [ ] Prune command is scheduled via cron/scheduler
- [ ] Config is cached (`php artisan config:cache`)
