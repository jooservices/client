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

JOOClient (via Guzzle) automatically handles compression by default. It sends `Accept-Encoding: gzip, deflate` and decompresses responses automatically.

```php
use JOOservices\Client\Client\ClientBuilder;

// Default behavior: Compression enabled
$client = ClientBuilder::create()->build();

$response = $client->get('https://api.example.com/data');
$content = (string) $response->getBody(); // Automatically decompressed
```

---

## Configuration

### Disable Compression

To disable automatic decompression (and prevent sending the `Accept-Encoding` header):

```php
$client = ClientBuilder::create()
    ->withOption('decode_content', false)
    ->build();
```

### Custom Decoding

You can pass a specific encoding to `decode_content` to only allow that encoding.

```php
// Only allow gzip
$client = ClientBuilder::create()
    ->withOption('decode_content', 'gzip')
    ->build();
```

---

## Best Practices

### 1. Trust the Defaults

Modern web servers and Guzzle handle compression negotiation efficiently. You rarely need to configure this manually.

### 2. Check Response Headers

If debugging, you can check if the response was actually compressed:

```php
$response = $client->get('https://api.example.com/data');

// Note: If decode_content is true, Content-Encoding header might be removed after decoding
// depending on the underlying handler.
```

---

## API Reference

Compression is handled via Guzzle's `decode_content` option.

- `true` (default): Automatic `Accept-Encoding` and decompression.
- `false`: No `Accept-Encoding`, no decompression.
- `string`: Specify encoding (e.g., `'gzip'`).

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

