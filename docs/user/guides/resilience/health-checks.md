# Health Check Guide

## Overview

JOOClient provides comprehensive health check utilities to verify the availability and connectivity of all configured services.

## Features

- ✅ MySQL database connectivity checks
- ✅ MongoDB connection verification
- ✅ Redis cache availability
- ✅ Filesystem writability checks
- ✅ Automatic timeout handling
- ✅ Detailed error reporting
- ✅ Performance timing
- ✅ JSON export for APIs

---

## Usage

### Check Individual Services

```php
use JOOservices\Client\Support\HealthCheck;
use JOOservices\Client\Logging\Config\DatabaseConnectionConfig;

$health = new HealthCheck();

// Check MySQL
$mysqlConfig = DatabaseConnectionConfig::fromArray([
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'jooclient',
    'username' => 'root',
    'password' => 'secret',
]);

$result = $health->checkMySql($mysqlConfig);

if ($result['status'] === 'healthy') {
    echo "MySQL is operational ✅\n";
} else {
    echo "MySQL is down: {$result['error']}\n";
}
```

### Check All Services

```php
use JOOservices\Client\Support\HealthCheck;

$health = new HealthCheck();

// Use same config as Factory
$config = config('jooclient');

$status = $health->checkAll($config);

if ($status['healthy']) {
    echo "All systems operational ✅\n";
} else {
    echo "Some services are down\n";
    foreach ($status['services'] as $service => $result) {
        if ($result['status'] !== 'healthy') {
            echo "- {$service}: {$result['error']}\n";
        }
    }
}
```

### Laravel Health Check Endpoint

```php
// routes/api.php
use JOOservices\Client\Support\HealthCheck;

Route::get('/health/jooclient', function () {
    $health = new HealthCheck();
    $status = $health->checkAll(config('jooclient'));
    
    return response()->json($status, $status['healthy'] ? 200 : 503);
});
```

---

## Response Format

### Healthy Service

```json
{
  "status": "healthy",
  "connection": "ok",
  "duration": 0.0023,
  "details": {
    "host": "127.0.0.1",
    "port": 3306,
    "database": "jooclient"
  }
}
```

### Unhealthy Service

```json
{
  "status": "unhealthy",
  "connection": "failed",
  "error": "SQLSTATE[HY000] [2002] Connection refused",
  "duration": 3.0012
}
```

### All Services

```json
{
  "healthy": true,
  "timestamp": "2025-11-06T03:00:00+00:00",
  "services": {
    "mysql": {
      "status": "healthy",
      "connection": "ok",
      "duration": 0.0023
    },
    "mongodb": {
      "status": "healthy",
      "connection": "ok",
      "duration": 0.0041
    },
    "redis": {
      "status": "healthy",
      "connection": "ok",
      "duration": 0.0012
    },
    "monolog": {
      "status": "healthy",
      "writable": true,
      "duration": 0.0001
    }
  }
}
```

---

## Kubernetes Liveness/Readiness Probes

```yaml
# deployment.yaml
apiVersion: v1
kind: Pod
metadata:
  name: my-app
spec:
  containers:
  - name: app
    image: my-app:latest
    livenessProbe:
      httpGet:
        path: /health/jooclient
        port: 8000
      initialDelaySeconds: 30
      periodSeconds: 10
    readinessProbe:
      httpGet:
        path: /health/jooclient
        port: 8000
      initialDelaySeconds: 5
      periodSeconds: 5
```

---

## Monitoring Integration

### Prometheus Metrics

```php
// Export health checks as Prometheus metrics
Route::get('/metrics', function () {
    $health = new HealthCheck();
    $status = $health->checkAll(config('jooclient'));
    
    $metrics = [];
    foreach ($status['services'] as $service => $result) {
        $healthy = $result['status'] === 'healthy' ? 1 : 0;
        $metrics[] = "jooclient_{$service}_up {$healthy}";
        
        if (isset($result['duration'])) {
            $metrics[] = "jooclient_{$service}_response_time {$result['duration']}";
        }
    }
    
    return response(implode("\n", $metrics))->header('Content-Type', 'text/plain');
});
```

### DataDog/New Relic

```php
use JOOservices\Client\Support\HealthCheck;

$health = new HealthCheck();
$status = $health->checkAll(config('jooclient'));

// Send to monitoring service
foreach ($status['services'] as $service => $result) {
    $metric = "jooclient.{$service}." . ($result['status'] === 'healthy' ? 'up' : 'down');
    
    // DataDog
    \DDTrace\send_metric($metric, 1);
    
    // Or New Relic
    if (extension_loaded('newrelic')) {
        newrelic_custom_metric($metric, 1);
    }
}
```

---

## Best Practices

### 1. Regular Health Checks

```php
// Schedule health checks
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $health = new HealthCheck();
        $status = $health->checkAll(config('jooclient'));
        
        if (!$status['healthy']) {
            // Alert team
            \Log::critical('JOOClient health check failed', $status);
        }
    })->everyFiveMinutes();
}
```

### 2. Startup Validation

```php
// Check before application starts
// bootstrap/app.php or AppServiceProvider
$health = new HealthCheck();
$status = $health->checkAll(config('jooclient'));

if (!$status['healthy']) {
    throw new \RuntimeException('Required services are unavailable');
}
```

### 3. Deployment Validation

```bash
# deploy.sh
php artisan tinker --execute="
use JOOservices\Client\Support\HealthCheck;
\$h = new HealthCheck();
\$s = \$h->checkAll(config('jooclient'));
exit(\$s['healthy'] ? 0 : 1);
"

if [ $? -eq 0 ]; then
  echo "✅ Health check passed"
else
  echo "❌ Health check failed - rolling back"
  exit 1
fi
```

---

## Troubleshooting

### MySQL Connection Issues

```
Error: SQLSTATE[HY000] [2002] Connection refused
```

**Solutions:**
- Verify MySQL is running: `systemctl status mysql`
- Check connection details in config
- Verify firewall allows connection
- Check credentials

### MongoDB Connection Issues

```
Error: Failed to connect to MongoDB
```

**Solutions:**
- Verify MongoDB is running: `systemctl status mongod`
- Check DSN format: `mongodb://host:port`
- Verify authentication if required
- Check network connectivity

### Redis Connection Issues

```
Error: Failed to connect to Redis server
```

**Solutions:**
- Verify Redis is running: `systemctl status redis`
- Check host and port
- Verify password if required
- Check ext-redis is installed: `php -m | grep redis`

### Filesystem Issues

```
Error: Log directory is not writable
```

**Solutions:**
- Check directory permissions: `ls -la /path/to/logs`
- Fix permissions: `chmod 755 /path/to/logs`
- Check ownership: `chown www-data:www-data /path/to/logs`

---

## Example Output

Run the health check example:

```bash
php examples/06-health-check.php
```

Output:
```
============================================================
JOOCLIENT HEALTH CHECK
============================================================

Overall Status: ✅ HEALTHY
Timestamp: 2025-11-06T03:00:00+00:00

Service Status:
------------------------------------------------------------
✅ MYSQL: healthy
   Duration: 0.0023s
   Details:
     - host: 127.0.0.1
     - port: 3306
     - database: jooclient

✅ REDIS: healthy
   Duration: 0.0012s
   Details:
     - host: 127.0.0.1
     - port: 6379

✅ MONOLOG: healthy
   Duration: 0.0001s
   Details:
     - path: /var/log/jooclient
     - permissions: 0755

JSON Export:
------------------------------------------------------------
{
  "healthy": true,
  "timestamp": "2025-11-06T03:00:00+00:00",
  "services": { ... }
}
```

---

## Conclusion

Health checks ensure your JOOClient dependencies are operational:
- ✅ Quick service verification
- ✅ Deployment validation
- ✅ Continuous monitoring
- ✅ API-ready JSON output
- ✅ Production-ready

Perfect for microservices, Kubernetes, and cloud deployments!

