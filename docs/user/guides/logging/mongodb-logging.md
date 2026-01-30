# MongoDB Configuration Guide

Complete guide for configuring MongoDB logging with file rotation in JOOClient.

## New Features Added

✅ **Custom Log File Path** - Specify where MongoDB error logs are saved  
✅ **Automatic File Rotation** - Rotate logs when they exceed a size limit  
✅ **Configurable Rotation** - Control how many rotated files to keep

---

## Configuration Options

### Environment Variables

Add to your `.env` file:

```env
# MongoDB Logging
JOOCLIENT_MONGODB_LOGGING=true
JOOCLIENT_MONGODB_DSN=mongodb://127.0.0.1:27017
JOOCLIENT_MONGODB_DATABASE=jooclient
JOOCLIENT_MONGODB_COLLECTION=client_request_logs
JOOCLIENT_MONGODB_BATCH=false

# File Rotation Settings (NEW)
JOOCLIENT_MONGODB_LOG_PATH=/path/to/logs/mongodb_errors.log
JOOCLIENT_MONGODB_ROTATE_SIZE=10485760
JOOCLIENT_MONGODB_ROTATE_FILES=5
```

### Config File

In `config/jooclient.php`:

```php
'logging' => [
    'driver' => 'mongodb',
    'connection' => [
        'mongodb' => [
            'enabled' => true,
            'dsn' => 'mongodb://127.0.0.1:27017',
            'database' => 'jooclient',
            'collection' => 'client_request_logs',
            'batch' => false,
            'fallback' => 'error_log',
            
            // File rotation settings (for error/failure logs)
            'file_path' => storage_path('logs/mongodb_errors.log'),
            'rotate_size' => 10485760, // 10MB (in bytes)
            'rotate_files' => 5, // Keep 5 rotated files
        ],
    ],
],
```

---

## Configuration Parameters

### file_path
**Type**: `string|null`  
**Default**: `/tmp/jooclient_mongodb_logger_failures.log`  
**Description**: Path where MongoDB failure logs are saved

**Examples**:
```php
// Laravel storage
'file_path' => storage_path('logs/mongodb_errors.log')

// Custom path
'file_path' => '/var/log/jooclient/mongodb_errors.log'

// Null uses default /tmp/ location
'file_path' => null
```

### rotate_size
**Type**: `int`  
**Default**: `10485760` (10MB)  
**Min**: `1024` (1KB)  
**Description**: Maximum file size in bytes before rotation

**Examples**:
```php
'rotate_size' => 5242880,    // 5MB
'rotate_size' => 10485760,   // 10MB (default)
'rotate_size' => 52428800,   // 50MB
'rotate_size' => 104857600,  // 100MB
```

**Human-readable sizes**:
- 1KB = 1024
- 1MB = 1048576  
- 5MB = 5242880
- 10MB = 10485760
- 50MB = 52428800
- 100MB = 104857600

### rotate_files
**Type**: `int`  
**Default**: `5`  
**Min**: `1`  
**Max**: `100`  
**Description**: Number of rotated files to keep

**Examples**:
```php
'rotate_files' => 3,   // Keep 3 rotated files
'rotate_files' => 5,   // Keep 5 rotated files (default)
'rotate_files' => 10,  // Keep 10 rotated files
```

---

## How File Rotation Works

### Example Scenario

Config:
```php
'file_path' => '/var/log/mongodb_errors.log',
'rotate_size' => 10485760, // 10MB
'rotate_files' => 3,
```

### File Lifecycle

1. **Initial state**: `mongodb_errors.log` (empty)

2. **After logging**: `mongodb_errors.log` (5MB)

3. **When file reaches 10MB**:
   - `mongodb_errors.log` → `mongodb_errors.log.1`
   - Create new `mongodb_errors.log`

4. **When file reaches 10MB again**:
   - `mongodb_errors.log.1` → `mongodb_errors.log.2`
   - `mongodb_errors.log` → `mongodb_errors.log.1`
   - Create new `mongodb_errors.log`

5. **When file reaches 10MB a third time**:
   - `mongodb_errors.log.2` → `mongodb_errors.log.3`
   - `mongodb_errors.log.1` → `mongodb_errors.log.2`
   - `mongodb_errors.log` → `mongodb_errors.log.1`
   - Create new `mongodb_errors.log`

6. **When file reaches 10MB again** (limit reached):
   - `mongodb_errors.log.3` → **deleted** (exceeds rotate_files limit)
   - `mongodb_errors.log.2` → `mongodb_errors.log.3`
   - `mongodb_errors.log.1` → `mongodb_errors.log.2`
   - `mongodb_errors.log` → `mongodb_errors.log.1`
   - Create new `mongodb_errors.log`

### Result
Always maintains:
- 1 active log file
- Up to 3 rotated files (`.1`, `.2`, `.3`)
- Total of 4 files maximum

---

## Usage Examples

### Example 1: Default Configuration

```php
$factory = (new Factory())
    ->enableMongoDbLogging(
        'mongodb://127.0.0.1:27017',
        'jooclient',
        'client_request_logs'
    );
```

**Result**:
- Logs to MongoDB database `jooclient`, collection `client_request_logs`
- Errors logged to `/tmp/jooclient_mongodb_logger_failures.log`
- Rotates at 10MB
- Keeps 5 rotated files

### Example 2: Custom Log Path

```php
$factory = (new Factory())
    ->enableMongoDbLogging(
        'mongodb://127.0.0.1:27017',
        'jooclient',
        'client_request_logs',
        [
            'file_path' => storage_path('logs/mongodb_errors.log')
        ]
    );
```

**Result**:
- Errors logged to Laravel's storage directory
- Other settings use defaults

### Example 3: High-Traffic Configuration

```php
$factory = (new Factory())
    ->enableMongoDbLogging(
        'mongodb://127.0.0.1:27017',
        'production_logs',
        'http_requests',
        [
            'batch' => true, // Enable batching for performance
            'file_path' => '/var/log/jooclient/mongodb_errors.log',
            'rotate_size' => 52428800, // 50MB
            'rotate_files' => 10, // Keep more history
        ]
    );
```

**Result**:
- Batch mode for better performance
- Larger rotation size for high volume
- More rotated files for longer history

### Example 4: Via Configuration

```php
// config/jooclient.php
return [
    'logging' => [
        'driver' => 'mongodb',
        'connection' => [
            'mongodb' => [
                'enabled' => true,
                'dsn' => env('MONGODB_DSN'),
                'database' => env('MONGODB_DATABASE'),
                'collection' => 'client_request_logs',
                'file_path' => storage_path('logs/mongodb/' . env('APP_ENV') . '.log'),
                'rotate_size' => 20971520, // 20MB
                'rotate_files' => 7, // One week of daily rotations
            ],
        ],
    ],
];

// In your code
$factory = Jooclient::fromConfig(config('jooclient'));
```

---

## Error Log Format

Logs are written in JSON format:

```json
{
  "time": "2025-11-06T01:23:15+00:00",
  "exception": "Failed to connect to MongoDB",
  "exception_class": "MongoDB\\Driver\\Exception\\ConnectionTimeoutException",
  "file": "/path/to/MongoDbLogger.php",
  "line": 123,
  "trace": "#0 ...\n#1 ...",
  "code": 0,
  "document": {
    "level": "error",
    "message": "API request failed",
    "context": {...},
    "created_at": {"$date": {"$numberLong": "1699123456000"}}
  }
}
```

---

## Validation

The config validates all parameters:

### DSN Validation
```php
// ✅ Valid
'dsn' => 'mongodb://127.0.0.1:27017'
'dsn' => 'mongodb://user:pass@localhost:27017'
'dsn' => 'mongodb+srv://cluster.mongodb.net'

// ❌ Invalid - throws exception
'dsn' => ''
// Missing dsn key
```

### Database Validation
```php
// ✅ Valid
'database' => 'jooclient'
'database' => 'production_logs'

// ❌ Invalid - throws exception
'database' => ''
// Missing database key
```

### Rotate Size Validation
```php
// ✅ Valid
'rotate_size' => 1024      // 1KB minimum
'rotate_size' => 10485760  // 10MB
'rotate_size' => 104857600 // 100MB

// ❌ Invalid - throws exception
'rotate_size' => 512  // Too small (< 1KB)
'rotate_size' => 0    // Invalid
```

### Rotate Files Validation
```php
// ✅ Valid
'rotate_files' => 1    // Keep 1 file
'rotate_files' => 5    // Keep 5 files
'rotate_files' => 100  // Keep 100 files (max)

// ❌ Invalid - throws exception
'rotate_files' => 0    // Too few
'rotate_files' => 101  // Too many (> 100)
```

---

## Best Practices

### 1. Production Environments
```php
[
    'file_path' => '/var/log/jooclient/mongodb_errors.log',
    'rotate_size' => 52428800, // 50MB
    'rotate_files' => 14, // 2 weeks of daily rotations
]
```

### 2. Development Environments
```php
[
    'file_path' => storage_path('logs/mongodb_errors.log'),
    'rotate_size' => 5242880, // 5MB  
    'rotate_files' => 3, // Recent logs only
]
```

### 3. High-Traffic Applications
```php
[
    'batch' => true, // Enable batching
    'file_path' => '/var/log/jooclient/mongodb_errors.log',
    'rotate_size' => 104857600, // 100MB
    'rotate_files' => 30, // 1 month of daily rotations
]
```

### 4. Monitoring Setup
```php
[
    'file_path' => '/var/log/jooclient/mongodb_errors.log',
    'rotate_size' => 10485760, // 10MB
    'rotate_files' => 7,
]

// Monitor with logrotate, fluentd, or similar
```

---

## Checking Logs

### Via Command Line
```bash
# View current log
cat /path/to/mongodb_errors.log

# View with JSON formatting
cat /path/to/mongodb_errors.log | jq '.'

# View latest entries
tail -f /path/to/mongodb_errors.log | jq '.'

# Count errors
wc -l /path/to/mongodb_errors.log

# View rotated logs
ls -lh /path/to/mongodb_errors.log*
```

### Via PHP
```php
$logFile = storage_path('logs/mongodb_errors.log');

if (file_exists($logFile)) {
    $lines = file($logFile, FILE_IGNORE_NEW_LINES);
    foreach ($lines as $line) {
        $entry = json_decode($line, true);
        echo "Error: {$entry['exception']}\n";
        echo "Time: {$entry['time']}\n";
        echo "---\n";
    }
}
```

---

## Troubleshooting

### Logs Not Being Created

**Check 1**: Directory permissions
```bash
ls -ld /path/to/logs
# Should be writable by web server user
```

**Check 2**: Path in config
```php
// Make sure directory exists
$dir = dirname(storage_path('logs/mongodb_errors.log'));
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
```

### Rotation Not Working

**Check 1**: File size
```bash
ls -lh /path/to/mongodb_errors.log
# Should show actual size
```

**Check 2**: Rotate size config
```php
// Make sure it's in bytes, not MB
'rotate_size' => 10 * 1024 * 1024, // 10MB
```

### Too Many Rotated Files

**Check 1**: rotate_files setting
```php
'rotate_files' => 5, // Reduces number of kept files
```

**Check 2**: Manual cleanup
```bash
# Remove old rotated files
rm /path/to/mongodb_errors.log.[6-9]
rm /path/to/mongodb_errors.log.1[0-9]
```

---

## Migration Guide

### Upgrading from Previous Version

**Before** (no rotation):
```php
$factory = (new Factory())
    ->enableMongoDbLogging(
        'mongodb://127.0.0.1:27017',
        'jooclient',
        'logs'
    );
```

**After** (with rotation):
```php
$factory = (new Factory())
    ->enableMongoDbLogging(
        'mongodb://127.0.0.1:27017',
        'jooclient',
        'logs',
        [
            'file_path' => storage_path('logs/mongodb_errors.log'),
            'rotate_size' => 10485760,
            'rotate_files' => 5,
        ]
    );
```

**Notes**:
- Existing code works without changes (backward compatible)
- New parameters are optional
- Default behavior unchanged if not specified

---

## Summary

✅ **file_path** - Where to save error logs (optional, defaults to `/tmp/`)  
✅ **rotate_size** - File size limit before rotation (default: 10MB)  
✅ **rotate_files** - Number of rotated files to keep (default: 5)  

All settings are optional and have sensible defaults!



