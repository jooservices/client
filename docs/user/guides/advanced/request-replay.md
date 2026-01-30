# Request Replay Guide

Complete guide to replaying recorded requests for debugging.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Debugging](TESTING_GUIDE.md) - Debugging utilities

---

## Overview

Request replay allows you to record and replay HTTP requests for debugging and testing.

**Key Features:**
- ✅ Record request/response pairs
- ✅ Replay requests from history
- ✅ Debugging support
- ✅ Request inspection

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

$factory = new Factory();
$client = $factory->make();

// Create replay instance
$replay = $client->createReplay();

// Make requests (they're automatically recorded)
$response1 = $client->get('https://api.example.com/users/1');
$replay->record($response1->getRequest(), $response1->getOriginal());

$response2 = $client->get('https://api.example.com/users/2');
$replay->record($response2->getRequest(), $response2->getOriginal());

// Replay first request
$replayed = $replay->replay(0);
```

---

## Recording Requests

### Manual Recording

```php
$replay = $client->createReplay();

$response = $client->get('https://api.example.com/data');
$replay->record($response->getRequest(), $response->getOriginal());
```

### Automatic Recording

For automatic recording, you'd need to integrate with request history:

```php
$factory = (new Factory())
    ->enableRequestHistory();

$client = $factory->make();
$replay = $client->createReplay();

// Make requests
$response = $client->get('https://api.example.com/data');

// Get from history
$history = $factory->getRequestHistory();
if (!empty($history)) {
    $lastRequest = $history[count($history) - 1];
    $replay->record($lastRequest['request'], $lastRequest['response']);
}
```

---

## Replaying Requests

### Replay by Index

```php
$replay = $client->createReplay();

// Record some requests
$replay->record($request1, $response1);
$replay->record($request2, $response2);

// Replay first request
$replayed = $replay->replay(0);

// Replay second request
$replayed = $replay->replay(1);
```

### Get History

```php
$replay = $client->createReplay();

// Record requests
$replay->record($request1, $response1);
$replay->record($request2, $response2);

// Get all recorded requests
$history = $replay->getHistory();

foreach ($history as $index => $record) {
    echo "Request {$index}: {$record['request']->getMethod()} {$record['request']->getUri()}\n";
    echo "Timestamp: {$record['timestamp']}\n";
}
```

---

## Use Cases

### 1. Debugging Failed Requests

```php
$replay = $client->createReplay();

try {
    $response = $client->get('https://api.example.com/data');
    $replay->record($response->getRequest(), $response->getOriginal());
} catch (\Throwable $e) {
    // Replay to debug
    if ($replay->size() > 0) {
        $replayed = $replay->replay(0);
        // Inspect replayed request
    }
}
```

### 2. Testing

```php
$replay = $client->createReplay();

// Record production request
$response = $client->get('https://api.example.com/data');
$replay->record($response->getRequest(), $response->getOriginal());

// Replay in test environment
$replayed = $replay->replay(0);
$this->assertEquals(200, $replayed->getStatusCode());
```

---

## API Reference

### Client Methods

```php
$client->createReplay(): RequestReplay
```

### RequestReplay Methods

```php
$replay->record(RequestInterface $request, ResponseInterface $response): void
$replay->replay(int $index): ?ResponseWrapper
$replay->getHistory(): array
$replay->clear(): void
```

---

## Best Practices

### 1. Clear History Regularly

```php
$replay = $client->createReplay();

// Record requests
$replay->record($request1, $response1);

// After debugging, clear history
$replay->clear();
```

### 2. Use for Debugging Only

Request replay is primarily for debugging. Don't use it in production code paths.

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

