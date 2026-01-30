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

```php
use JOOservices\Client\Factory\Factory;
use JOOservices\Client\Cookies\CookieJarManager;

$manager = new CookieJarManager();
$jar = $manager->create();

$factory = (new Factory())
    ->withCookieJar($jar);

$client = $factory->make();

// Cookies are automatically managed
$response = $client->get('https://api.example.com/login');
$response = $client->get('https://api.example.com/profile'); // Uses cookies from login
```

---

## Cookie Jar Types

### In-Memory Cookie Jar

Cookies stored in memory (lost when script ends):

```php
$manager = new CookieJarManager();
$jar = $manager->create();

$factory = (new Factory())
    ->withCookieJar($jar);
```

### Persistent Cookie Jar

Cookies stored in file (persists across requests):

```php
$manager = new CookieJarManager();
$jar = $manager->createPersistent('/path/to/cookies.txt');

$factory = (new Factory())
    ->withCookieJar($jar);
```

### From Array

Create cookie jar from array:

```php
$manager = new CookieJarManager();
$jar = $manager->fromArray([
    'session_id' => 'abc123',
    'user_token' => 'xyz789',
], 'example.com');

$factory = (new Factory())
    ->withCookieJar($jar);
```

---

## Use Cases

### 1. Session Management

```php
$manager = new CookieJarManager();
$jar = $manager->createPersistent(storage_path('cookies/session.txt'));

$factory = (new Factory())
    ->withCookieJar($jar);

$client = $factory->make();

// Login (sets session cookie)
$client->post('https://api.example.com/login', [
    'json' => ['username' => 'user', 'password' => 'pass'],
]);

// Subsequent requests use session cookie
$response = $client->get('https://api.example.com/profile');
```

### 2. Multiple Domains

```php
$manager = new CookieJarManager();

// Create separate jars for different domains
$apiJar = $manager->create();
$webJar = $manager->create();

$apiFactory = (new Factory())->withCookieJar($apiJar);
$webFactory = (new Factory())->withCookieJar($webJar);
```

---

## Best Practices

### 1. Use Persistent Jars for Sessions

```php
// Good: Persistent jar for session management
$jar = $manager->createPersistent(storage_path('cookies/session.txt'));

// Bad: In-memory jar (cookies lost on script end)
$jar = $manager->create();
```

### 2. Separate Jars for Different Services

```php
// Create separate jars for isolation
$apiJar = $manager->createPersistent(storage_path('cookies/api.txt'));
$webJar = $manager->createPersistent(storage_path('cookies/web.txt'));
```

### 3. Secure Cookie Storage

```php
// Store cookies in secure location
$jar = $manager->createPersistent(
    storage_path('app/secure/cookies.txt')
);

// Set appropriate file permissions
chmod(storage_path('app/secure/cookies.txt'), 0600);
```

---

## API Reference

### CookieJarManager Methods

```php
$manager->create(array $cookies = []): CookieJarInterface
$manager->createPersistent(string $filePath): CookieJarInterface
$manager->fromArray(array $cookies, string $domain): CookieJarInterface
```

### Factory Methods

```php
$factory->withCookieJar(CookieJarInterface $cookieJar): self
```

---

## Troubleshooting

### Cookies Not Persisting

1. **Check file permissions:** Ensure cookie file is writable
2. **Check path:** Verify file path is correct
3. **Check domain:** Ensure cookies are set for correct domain

### Cookies Not Sent

1. **Check jar:** Verify cookie jar is set on factory
2. **Check domain:** Ensure request domain matches cookie domain
3. **Check expiration:** Verify cookies haven't expired

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

