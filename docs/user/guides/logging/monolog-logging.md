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
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())->enableLogging([
    'logging' => [
        'enabled' => true,
        'driver' => 'monolog',
        'connection' => [
            'monolog' => [
                'path' => '/path/to/logs',
                'filename' => 'jooclient.log',
                'rotate_enabled' => true,
                'rotate_max_files' => 7,
                'level' => 'info',
            ],
        ],
    ],
]);
```

---

## Usage

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

// Uses config from .env
$factory = (new Factory())->enableLogging();
$result = $factory->make();

// All requests are logged to file
$response = $result->get('https://api.example.com/users');

// Flush any buffered logs (if applicable)
$result->flushLogger();
```

### With JSON Formatting

```env
JOOCLIENT_MONOLOG_FORMATTER=json
```

This will log in JSON format:

```json
{
  "message": "Mac.local GuzzleHttp/7 - [06/Nov/2025:03:00:00 +0000] \"GET /users HTTP/1.1\" 200",
  "context": {
    "request": "[object:GuzzleHttp\\Psr7\\Request]",
    "response": "[object:GuzzleHttp\\Psr7\\Response]"
  },
  "level": 200,
  "level_name": "INFO",
  "channel": "jooclient",
  "datetime": "2025-11-06T03:00:00.000000+00:00",
  "extra": []
}
```

### With File Rotation

When `rotate_enabled` is `true`, Monolog uses `RotatingFileHandler` which:

1. **Creates daily log files** with date suffixes
   - Example: `jooclient-2025-11-06.log`
   
2. **Automatically rotates logs** at midnight

3. **Keeps only N files** (specified by `rotate_max_files`)

4. **Deletes oldest logs** when limit is reached

**Example:**
```
logs/
├── jooclient-2025-11-01.log  (deleted - older than 7 days)
├── jooclient-2025-11-05.log
├── jooclient-2025-11-06.log  (today)
└── jooclient-2025-11-07.log  (tomorrow)
```

### Without File Rotation

When `rotate_enabled` is `false` (default), Monolog uses `StreamHandler` which:

1. **Writes to a single file** (no date suffix)
   - Example: `jooclient.log`

2. **Appends continuously** to the same file

3. **No automatic deletion** (file grows indefinitely)

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

**Configuration:**

```env
JOOCLIENT_MONOLOG_LEVEL=info  # Only logs INFO and above
```

---

## Examples

### 1. Basic File Logging

```php
$factory = (new Factory())->enableLogging([
    'logging' => [
        'enabled' => true,
        'driver' => 'monolog',
        'connection' => [
            'monolog' => [
                'path' => storage_path('logs'),
                'filename' => 'api-requests.log',
                'level' => 'info',
            ],
        ],
    ],
]);

$result = $factory->make();
$response = $result->get('https://api.example.com/data');
```

**Output (`logs/api-requests.log`):**
```
[2025-11-06 03:00:00] jooclient.INFO: Mac.local GuzzleHttp/7 - [06/Nov/2025:03:00:00 +0000] "GET /data HTTP/1.1" 200
```

### 2. JSON Logging with Rotation

```php
$factory = (new Factory())->enableLogging([
    'logging' => [
        'enabled' => true,
        'driver' => 'monolog',
        'connection' => [
            'monolog' => [
                'path' => '/var/log/app',
                'filename' => 'http-requests.log',
                'rotate_enabled' => true,
                'rotate_max_files' => 30,  // Keep 30 days
                'level' => 'debug',
                'formatter' => 'json',
            ],
        ],
    ],
]);
```

**Output (`/var/log/app/http-requests-2025-11-06.log`):**
```json
{"message":"...","context":{...},"level":200,"level_name":"INFO",...}
```

### 3. Laravel Integration

```php
// In a controller
use JOOservices\Client\Factory\Factory;

class ApiController extends Controller
{
    public function fetchData()
    {
        $factory = (new Factory())->enableLogging();
        $result = $factory->make();
        
        $response = $result->get('https://api.example.com/data');
        $result->flushLogger();
        
        return response()->json(json_decode($response->getBody()));
    }
}
```

### 4. Different Log Levels for Different Environments

```php
// config/jooclient.php
'logging' => [
    'connection' => [
        'monolog' => [
            'level' => app()->environment('production') ? 'warning' : 'debug',
        ],
    ],
],
```

---

## Best Practices

### 1. Enable Rotation in Production

```env
# Production
JOOCLIENT_MONOLOG_ROTATE_ENABLED=true
JOOCLIENT_MONOLOG_ROTATE_MAX_FILES=30
```

**Why?**
- Prevents log files from growing indefinitely
- Automatic cleanup of old logs
- Saves disk space

### 2. Use Appropriate Log Levels

```env
# Development
JOOCLIENT_MONOLOG_LEVEL=debug

# Staging
JOOCLIENT_MONOLOG_LEVEL=info

# Production
JOOCLIENT_MONOLOG_LEVEL=warning
```

**Why?**
- Debug logs can be very verbose
- Production should only log important events
- Reduces log file size and improves performance

### 3. Use JSON Format for Log Aggregation

```env
JOOCLIENT_MONOLOG_FORMATTER=json
```

**Why?**
- Easier to parse with log aggregation tools (ELK, Splunk, etc.)
- Structured data for better analysis
- Consistent format for automated processing

### 4. Organize Logs by Purpose

```php
// API requests
$apiFactory = (new Factory())->enableLogging([
    'logging' => [
        'connection' => [
            'monolog' => [
                'path' => storage_path('logs/api'),
                'filename' => 'external-requests.log',
            ],
        ],
    ],
]);

// Webhook callbacks
$webhookFactory = (new Factory())->enableLogging([
    'logging' => [
        'connection' => [
            'monolog' => [
                'path' => storage_path('logs/webhooks'),
                'filename' => 'webhook-calls.log',
            ],
        ],
    ],
]);
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

