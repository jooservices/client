# Progress Tracking Guide

Complete guide to tracking upload/download progress for HTTP requests.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Progress tracking allows you to monitor upload and download progress for HTTP requests.

**Key Features:**
- ✅ Upload progress tracking
- ✅ Download progress tracking
- ✅ Callback-based progress updates
- ✅ Total and current bytes tracking

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;
use JOOservices\Client\Middlewares\ProgressTrackingMiddleware;

$factory = (new Factory())
    ->addMiddleware(new ProgressTrackingMiddleware(), 'progress');

$client = $factory->make();

// Track download progress
$response = $client->get('https://api.example.com/large-file', [
    'progress' => function ($total, $downloaded, $uploaded = 0) {
        if ($total > 0) {
            $percent = ($downloaded / $total) * 100;
            echo "Download progress: {$percent}% ({$downloaded}/{$total} bytes)\n";
        }
    },
]);
```

---

## Upload Progress

### Track File Upload

```php
$response = $client->post('https://api.example.com/upload', [
    'multipart' => [
        [
            'name' => 'file',
            'contents' => fopen('large-file.zip', 'r'),
            'filename' => 'large-file.zip',
        ],
    ],
    'progress' => function ($total, $downloaded, $uploaded) {
        if ($total > 0) {
            $percent = ($uploaded / $total) * 100;
            echo "Upload progress: {$percent}% ({$uploaded}/{$total} bytes)\n";
        }
    },
]);
```

---

## Download Progress

### Track File Download

```php
$response = $client->get('https://api.example.com/download', [
    'progress' => function ($total, $downloaded) {
        if ($total > 0) {
            $percent = ($downloaded / $total) * 100;
            echo "Download: {$percent}% ({$downloaded}/{$total} bytes)\n";
        } else {
            echo "Downloaded: {$downloaded} bytes (size unknown)\n";
        }
    },
]);
```

---

## Advanced Usage

### Progress Bar

```php
function formatProgressBar($current, $total, $width = 50): string
{
    if ($total <= 0) {
        return str_repeat('=', $width);
    }
    
    $percent = $current / $total;
    $filled = (int) ($percent * $width);
    $empty = $width - $filled;
    
    return '[' . str_repeat('=', $filled) . str_repeat(' ', $empty) . '] ' . 
           round($percent * 100, 1) . '%';
}

$response = $client->get('https://api.example.com/large-file', [
    'progress' => function ($total, $downloaded) use ($width = 50) {
        echo "\r" . formatProgressBar($downloaded, $total, $width);
        flush();
    },
]);

echo "\n"; // New line after completion
```

### Save Progress to File

```php
$progressFile = fopen('progress.log', 'w');

$response = $client->get('https://api.example.com/large-file', [
    'progress' => function ($total, $downloaded) use ($progressFile) {
        $data = [
            'timestamp' => time(),
            'total' => $total,
            'downloaded' => $downloaded,
            'percent' => $total > 0 ? ($downloaded / $total) * 100 : 0,
        ];
        fwrite($progressFile, json_encode($data) . "\n");
    },
]);

fclose($progressFile);
```

---

## Best Practices

### 1. Handle Unknown Size

```php
$response = $client->get('https://api.example.com/data', [
    'progress' => function ($total, $downloaded) {
        if ($total > 0) {
            // Known size: Show percentage
            $percent = ($downloaded / $total) * 100;
            echo "Progress: {$percent}%\n";
        } else {
            // Unknown size: Show bytes only
            echo "Downloaded: {$downloaded} bytes\n";
        }
    },
]);
```

### 2. Throttle Progress Updates

```php
$lastUpdate = 0;

$response = $client->get('https://api.example.com/large-file', [
    'progress' => function ($total, $downloaded) use (&$lastUpdate) {
        $now = time();
        if ($now - $lastUpdate >= 1) { // Update every second
            $percent = $total > 0 ? ($downloaded / $total) * 100 : 0;
            echo "Progress: {$percent}%\n";
            $lastUpdate = $now;
        }
    },
]);
```

---

## API Reference

### Progress Callback Signature

```php
function(int $total, int $downloaded, int $uploaded = 0): void
```

**Parameters:**
- `$total`: Total bytes (0 if unknown)
- `$downloaded`: Bytes downloaded so far
- `$uploaded`: Bytes uploaded so far (for uploads)

### Request Options

```php
$client->get($uri, [
    'progress' => callable, // Progress callback
]);
```

---

## Limitations

- Progress tracking requires middleware to be added
- Some servers may not provide `Content-Length` header (total will be 0)
- Progress callbacks are called frequently (consider throttling)

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

