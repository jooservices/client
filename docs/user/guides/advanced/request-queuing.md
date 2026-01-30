# Request Queuing Guide

Complete guide to queuing and batch processing HTTP requests.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Request queuing allows you to queue multiple HTTP requests and process them in batches with configurable delays.

**Key Features:**
- ✅ Queue multiple requests
- ✅ Batch processing with configurable size
- ✅ Configurable delay between batches
- ✅ Automatic error handling
- ✅ Returns all responses

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

$factory = new Factory();
$client = $factory->make();

// Create queue
$queue = $client->createQueue([
    'batch_size' => 10,
    'delay' => 1.0, // 1 second delay between batches
]);

// Add requests to queue
$queue->add('GET', 'https://api.example.com/users/1');
$queue->add('GET', 'https://api.example.com/users/2');
$queue->add('GET', 'https://api.example.com/users/3');

// Process all requests
$results = $queue->process();

// Results is an array of ResponseWrapper instances
foreach ($results as $index => $response) {
    if ($response->isSuccess()) {
        $data = $response->getContent();
        echo "Request {$index}: Success\n";
    }
}
```

---

## Configuration

### Batch Size

Control how many requests are processed in parallel:

```php
$queue = $client->createQueue([
    'batch_size' => 5, // Process 5 requests at a time
]);
```

### Delay Between Batches

Add delay between batches to avoid overwhelming the server:

```php
$queue = $client->createQueue([
    'batch_size' => 10,
    'delay' => 2.0, // 2 seconds delay between batches
]);
```

---

## Advanced Usage

### With Request Options

```php
$queue = $client->createQueue();

$queue->add('POST', 'https://api.example.com/users', [
    'json' => ['name' => 'John'],
    'headers' => ['Authorization' => 'Bearer token'],
]);

$queue->add('PUT', 'https://api.example.com/users/1', [
    'json' => ['name' => 'Jane'],
]);

$results = $queue->process();
```

### Queue Management

```php
$queue = $client->createQueue();

// Add requests
$queue->add('GET', 'https://api.example.com/users/1');
$queue->add('GET', 'https://api.example.com/users/2');

// Check queue size
echo "Queue size: " . $queue->size() . "\n"; // 2

// Clear queue
$queue->clear();
echo "Queue size: " . $queue->size() . "\n"; // 0
```

### Error Handling

```php
$queue = $client->createQueue();
$queue->add('GET', 'https://api.example.com/users/1');

$results = $queue->process();

foreach ($results as $index => $response) {
    if ($response->isSuccess()) {
        $data = $response->getContent();
    } else {
        // Handle error
        echo "Request {$index} failed: " . $response->getStatusCode() . "\n";
    }
}
```

---

## Best Practices

### 1. Appropriate Batch Size

```php
// For rate-limited APIs: Smaller batches
$queue = $client->createQueue([
    'batch_size' => 5,
    'delay' => 1.0,
]);

// For high-throughput APIs: Larger batches
$queue = $client->createQueue([
    'batch_size' => 50,
    'delay' => 0.5,
]);
```

### 2. Use Delays for Rate-Limited APIs

```php
$queue = $client->createQueue([
    'batch_size' => 10,
    'delay' => 2.0, // 2 second delay to respect rate limits
]);
```

### 3. Process Results Immediately

```php
$results = $queue->process();

// Process results as soon as available
foreach ($results as $response) {
    if ($response->isSuccess()) {
        // Process immediately
        $this->processResponse($response);
    }
}
```

---

## API Reference

### Client Methods

```php
$client->createQueue(array $config = []): RequestQueue
```

### RequestQueue Methods

```php
$queue->add(string $method, string|UriInterface $uri, array $options = []): self
$queue->process(): array<ResponseWrapper>
$queue->size(): int
$queue->clear(): void
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `batch_size` | int | `10` | Number of requests per batch |
| `delay` | float | `0.0` | Delay in seconds between batches |

---

## Use Cases

### 1. Bulk Data Fetching

```php
$queue = $client->createQueue(['batch_size' => 20]);

// Queue all user requests
for ($i = 1; $i <= 100; $i++) {
    $queue->add('GET', "https://api.example.com/users/{$i}");
}

// Process all at once
$results = $queue->process();
```

### 2. Rate-Limited API

```php
$queue = $client->createQueue([
    'batch_size' => 5, // Small batches
    'delay' => 2.0, // 2 second delay
]);

// Queue requests
foreach ($userIds as $userId) {
    $queue->add('GET', "https://api.example.com/users/{$userId}");
}

$results = $queue->process();
```

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

