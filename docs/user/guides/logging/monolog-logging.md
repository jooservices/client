# Monolog Logging Guide

## Overview

JOOClient supports file-based logging using Monolog with configurable rotation and formatting options.

## Features

- ✅ File-based logging with Monolog
- ✅ Optional daily log rotation (RotatingFileHandler)
- ✅ Configurable log levels (debug, info, warning, error, etc.)
- ✅ JSON formatter support
- ✅ Custom log paths and filenames
- ✅ Automatic directory creation

---

## Configuration

### Via .env (Recommended)

```env
# Enable Monolog logging
JOOCLIENT_LOGGING_ENABLED=true
JOOCLIENT_LOGGING_DRIVER=monolog

# Storage configuration
JOOCLIENT_MONOLOG_PATH=/path/to/logs
JOOCLIENT_MONOLOG_FILENAME=jooclient.log

# File rotation (optional)
JOOCLIENT_MONOLOG_ROTATE_ENABLED=true  # Enable daily rotation
JOOCLIENT_MONOLOG_ROTATE_MAX_FILES=7    # Keep 7 days of logs

# Logging configuration
JOOCLIENT_MONOLOG_CHANNEL=jooclient
JOOCLIENT_MONOLOG_LEVEL=info  # debug, info, warning, error
JOOCLIENT_MONOLOG_FORMATTER=json  # Optional: use JSON format
```

### Via config/jooclient.php

```php
'logging' => [
    'enabled' => true,
    'driver' => 'monolog',
    
    'connection' => [
        'monolog' => [
            'enabled' => true,
            
            // Storage configuration
            'path' => storage_path('logs'),
            'filename' => 'jooclient.log',
            
            // File rotation settings
            'rotate_enabled' => true,
            'rotate_max_files' => 7,
            
            // Logging configuration
            'channel' => 'jooclient',
            'level' => 'info',
            'formatter' => 'json',  // or null for default
        ],
    ],
],
```

### Via Code

```php
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withDefaultLogging(
        domain: 'jooclient', 
        path: '/path/to/logs/jooclient.log'
    )
    ->build();
```

---

## Usage

### Basic Usage

```php
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withDefaultLogging('my-app')
    ->build();

// All requests are logged to file
$response = $client->get('https://api.example.com/users');
```

### With Custom Logger

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use JOOservices\Client\Client\ClientBuilder;

$log = new Logger('custom-channel');
$log->pushHandler(new StreamHandler('/path/to/my.log', Logger::WARNING));

$client = ClientBuilder::create()
    ->withLogger($log, logBodies: true)
    ->build();
```

---

## Log Levels

Monolog supports 8 log levels (RFC 5424):

| Level | Value | Description |
|-------|-------|-------------|
| **DEBUG** | 100 | Detailed debug information |
| **INFO** | 200 | Informational messages (default) |
| **NOTICE** | 250 | Normal but significant events |
| **WARNING** | 300 | Warning messages |
| **ERROR** | 400 | Error conditions |
| **CRITICAL** | 500 | Critical conditions |
| **ALERT** | 550 | Action must be taken immediately |
| **EMERGENCY** | 600 | System is unusable |

---

## Best Practices

### 1. Use JSON Format for Log Aggregation

When using `withDefaultLogging`, the logger is configured to use a standard format. For custom formatting, inject your own Logger instance.

### 2. Organize Logs by Purpose

```php
// API requests
$apiClient = ClientBuilder::create()
    ->withDefaultLogging('api-requests', '/logs/api.log')
    ->build();

// Webhook callbacks
$webhookClient = ClientBuilder::create()
    ->withDefaultLogging('webhooks', '/logs/webhooks.log')
    ->build();
```

### 5. Monitor Log File Sizes

```bash
# Check log file sizes
du -h storage/logs/*.log

# Clean up old logs manually if needed
find storage/logs -name "*.log" -mtime +30 -delete
```

---

## Troubleshooting

### Log File Not Created

**Problem:** Log file doesn't exist after requests

**Solutions:**

1. **Check directory permissions**
   ```bash
   chmod 755 /path/to/logs
   chown www-data:www-data /path/to/logs
   ```

2. **Verify logging is enabled**
   ```env
   JOOCLIENT_LOGGING_ENABLED=true
   JOOCLIENT_LOGGING_DRIVER=monolog
   ```

3. **Check for rotation** (file might have date suffix)
   ```bash
   ls -la /path/to/logs/jooclient-*.log
   ```

4. **Disable rotation for testing**
   ```env
   JOOCLIENT_MONOLOG_ROTATE_ENABLED=false
   ```

### Permission Denied Errors

**Problem:** `fopen(): failed to open stream: Permission denied`

**Solutions:**

```bash
# Make directory writable
chmod 755 /path/to/logs

# Ensure correct ownership
chown www-data:www-data /path/to/logs

# Or use a different path
JOOCLIENT_MONOLOG_PATH=/tmp/jooclient-logs
```

### No Logs Appearing

**Problem:** Log file exists but is empty

**Solutions:**

1. **Check log level**
   ```env
   # Set to debug to log everything
   JOOCLIENT_MONOLOG_LEVEL=debug
   ```

2. **Verify flush is called**
   ```php
   $result->flushLogger();
   ```

3. **Check for errors**
   ```bash
   tail -f /var/log/php-errors.log
   ```

### Logs Filling Up Disk

**Problem:** Log files consuming too much space

**Solutions:**

1. **Enable rotation**
   ```env
   JOOCLIENT_MONOLOG_ROTATE_ENABLED=true
   JOOCLIENT_MONOLOG_ROTATE_MAX_FILES=7
   ```

2. **Increase log level** (log less)
   ```env
   JOOCLIENT_MONOLOG_LEVEL=warning
   ```

3. **Set up log rotation with logrotate**
   ```bash
   # /etc/logrotate.d/jooclient
   /path/to/logs/*.log {
       daily
       rotate 7
       compress
       delaycompress
       missingok
       notifempty
   }
   ```

---

## Performance Tips

1. **Use Buffering**  
   Monolog writes are synchronous - use batch mode if available

2. **Appropriate Log Level**  
   Don't use `debug` in production - it's very verbose

3. **JSON Format**  
   Slightly slower than default, but worth it for structured logging

4. **Rotation**  
   Daily rotation has minimal performance impact

5. **Storage Location**  
   Use fast storage (SSD) for log files if possible

---

## Comparison with Other Drivers

| Feature | Monolog | MySQL | MongoDB |
|---------|---------|-------|---------|
| **Setup** | Very Easy | Medium | Medium |
| **Dependencies** | Monolog library | MySQL DB | MongoDB extension |
| **Performance** | Fast | Medium | Fast |
| **Querying** | Grep/Tools | SQL queries | MongoDB queries |
| **Rotation** | Built-in | Manual | Manual |
| **Best For** | Simple logging | Structured queries | High volume |

---

## Conclusion

Monolog logging in JOOClient provides:
- ✅ Easy file-based logging
- ✅ Optional daily rotation
- ✅ JSON formatting
- ✅ Configurable via .env
- ✅ Production-ready

Perfect for:
- Simple applications
- Development/debugging
- Applications without database logging needs
- Quick setup and deployment

For structured querying and advanced features, consider MySQL or MongoDB drivers.

