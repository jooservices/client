# Cookie Jar Guide

Complete guide to managing cookies with HTTP clients.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Cookie jar management allows you to persist and manage cookies across multiple requests.

**Key Features:**
- ✅ Session cookie management
- ✅ Persistent cookie storage
- ✅ Cookie sharing across requests
- ✅ File-based cookie persistence

---

## Quick Start

### Basic Usage

JOOClient uses Guzzle's underlying cookie management. You can enable a shared cookie jar for the client, allowing cookies to persist across requests made by that client instance.

```php
use JOOservices\Client\Client\ClientBuilder;

// Enable cookies (creates a shared in-memory cookie jar)
$client = ClientBuilder::create()
    ->withOption('cookies', true)
    ->build();

// Cookies are automatically handled
$response = $client->post('https://api.example.com/login', [...]);
$response = $client->get('https://api.example.com/profile'); // Session cookie sent automatically
```

---

## Persistent Cookies

To persist cookies to disk (e.g., for CLI tools or long-running processes), pass a `GuzzleHttp\Cookie\FileCookieJar`.

```php
use JOOservices\Client\Client\ClientBuilder;
use GuzzleHttp\Cookie\FileCookieJar;

$jar = new FileCookieJar('/path/to/cookies.json', true);

$client = ClientBuilder::create()
    ->withOption('cookies', $jar)
    ->build();
```

---

## Use Cases

### 1. Session Management

```php
$client = ClientBuilder::create()
    ->withOption('cookies', true)
    ->build();

// Login (sets session cookie)
$client->post('https://api.example.com/login', [
    'json' => ['username' => 'user', 'password' => 'pass'],
]);

// Subsequent requests use session cookie
$response = $client->get('https://api.example.com/profile');
```

### 2. Manual Cookie Management

You can also pass a `CookieJar` instance to manipulate cookies manually.

```php
use GuzzleHttp\Cookie\CookieJar;

$jar = new CookieJar();
$jar->setCookie(new SetCookie([
    'Name' => 'foo',
    'Value' => 'bar',
    'Domain' => 'api.example.com'
]));

$client = ClientBuilder::create()
    ->withOption('cookies', $jar)
    ->build();
```

---

## Best Practices

### 1. Secure Cookie Storage

If using file-based persistence, ensure the file is stored in a secure location with restricted permissions (e.g., `chmod 600`).

### 2. Session Isolation

Create separate client instances (with separate cookie jars) for different users or sessions to ensure isolation.

```php
$userAClient = ClientBuilder::create()->withOption('cookies', true)->build();
$userBClient = ClientBuilder::create()->withOption('cookies', true)->build();
```

---

## API Reference

Cookie management is handled via Guzzle's `cookies` option.

```php
// Request Option
$client->get('/url', [
    'cookies' => $jar // Use specific jar for this request
]);
```

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

