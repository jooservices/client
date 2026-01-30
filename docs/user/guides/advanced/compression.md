# Compression Guide

Complete guide to request/response compression.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Compression middleware automatically adds `Accept-Encoding` headers to requests, allowing servers to compress responses.

**Key Features:**
- ✅ Automatic `Accept-Encoding` header
- ✅ Multiple encoding support (gzip, deflate, br)
- ✅ Server-side response compression
- ✅ Reduced bandwidth usage

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableCompression(['gzip', 'deflate']);

$client = $factory->make();
$response = $client->get('https://api.example.com/data');
```

---

## Supported Encodings

### Gzip

Most common compression format:

```php
$factory = (new Factory())
    ->enableCompression(['gzip']);
```

### Deflate

Alternative compression format:

```php
$factory = (new Factory())
    ->enableCompression(['deflate']);
```

### Brotli

Modern compression format (requires server support):

```php
$factory = (new Factory())
    ->enableCompression(['br', 'gzip', 'deflate']);
```

### Multiple Encodings

Request multiple encodings (server chooses best):

```php
$factory = (new Factory())
    ->enableCompression(['br', 'gzip', 'deflate']);
```

---

## How It Works

### Request Flow

1. Client adds `Accept-Encoding: gzip, deflate` header
2. Server compresses response if supported
3. Server adds `Content-Encoding: gzip` header
4. Guzzle automatically decompresses response

### Automatic Decompression

Guzzle automatically decompresses responses, so you don't need to handle decompression manually:

```php
$response = $client->get('https://api.example.com/data');
$content = $response->getContent(); // Already decompressed
```

---

## Best Practices

### 1. Use Multiple Encodings

```php
// Good: Request multiple encodings
$factory = (new Factory())
    ->enableCompression(['br', 'gzip', 'deflate']);

// Server will choose the best supported encoding
```

### 2. Check Response Encoding

```php
$response = $client->get('https://api.example.com/data');

if ($response->hasHeader('Content-Encoding')) {
    $encoding = $response->getHeaderLine('Content-Encoding');
    echo "Response compressed with: {$encoding}\n";
}
```

### 3. Combine with Caching

```php
// Compression + caching for maximum efficiency
$factory = (new Factory())
    ->enableCache([...])
    ->enableCompression(['gzip', 'deflate']);
```

---

## API Reference

### Factory Methods

```php
$factory->enableCompression(array $encodings = ['gzip', 'deflate']): self
```

### Supported Encodings

- `gzip` - Gzip compression
- `deflate` - Deflate compression
- `br` - Brotli compression (requires server support)

---

## Troubleshooting

### Compression Not Working

1. **Check header:** Verify `Accept-Encoding` header is sent
2. **Check server:** Ensure server supports compression
3. **Check response:** Verify `Content-Encoding` header in response

### Performance Issues

Compression adds minimal overhead. If you experience issues:
1. Disable compression for specific requests
2. Use fewer encoding options
3. Check server compression settings

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

