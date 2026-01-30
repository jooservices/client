# Request Signing Guide

Complete guide to signing HTTP requests for authenticated APIs.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Request signing adds authentication signatures to HTTP requests for APIs that require signed requests (OAuth, HMAC, AWS, etc.).

**Key Features:**
- ✅ HMAC signing
- ✅ OAuth 1.0 signing
- ✅ Per-request signing control
- ✅ Custom header names
- ✅ Multiple signing algorithms

---

## Quick Start

### HMAC Signing

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableRequestSigning([
        'type' => 'hmac',
        'secret' => env('API_SECRET'),
        'algorithm' => 'sha256',
        'header_name' => 'X-Signature',
    ]);

$client = $factory->make();
$response = $client->get('https://api.example.com/data');
```

---

## Signing Types

### HMAC Signing

HMAC (Hash-based Message Authentication Code) signing.

```php
$factory = (new Factory())
    ->enableRequestSigning([
        'type' => 'hmac',
        'secret' => 'your-secret-key',
        'algorithm' => 'sha256', // sha256, sha1, sha512
        'header_name' => 'X-Signature', // Optional, default: X-Signature
    ]);
```

**How it works:**
- Signs: `METHOD + URI + BODY`
- Uses HMAC with specified algorithm
- Adds signature to request header

---

### OAuth 1.0 Signing

OAuth 1.0 request signing.

```php
$factory = (new Factory())
    ->enableRequestSigning([
        'type' => 'oauth1',
        'consumer_key' => env('OAUTH_CONSUMER_KEY'),
        'consumer_secret' => env('OAUTH_CONSUMER_SECRET'),
        'token' => env('OAUTH_TOKEN'), // Optional
        'token_secret' => env('OAUTH_TOKEN_SECRET'), // Optional
    ]);
```

**How it works:**
- Implements OAuth 1.0 signature algorithm
- Adds `Authorization` header with OAuth parameters
- Supports token-based authentication

**Note:** This is a simplified OAuth1 implementation. For full OAuth1 support, consider using `league/oauth1-client`.

---

## Disable Signing Per Request

You can disable signing for specific requests:

```php
$response = $client->get('https://api.example.com/data', [
    'no_signing' => true,
]);
```

---

## Custom Signers

### Implementing Custom Signer

```php
use JOOservices\Client\Signing\RequestSignerInterface;
use Psr\Http\Message\RequestInterface;

final class CustomSigner implements RequestSignerInterface
{
    public function sign(RequestInterface $request, array $options = []): RequestInterface
    {
        // Your signing logic
        $signature = $this->computeSignature($request);
        
        return $request->withHeader('X-Custom-Signature', $signature);
    }
    
    private function computeSignature(RequestInterface $request): string
    {
        // Signature computation
        return 'signature';
    }
}
```

### Using Custom Signer

```php
use JOOservices\Client\Signing\Middleware\RequestSigningMiddleware;

$signer = new CustomSigner();
$middleware = new RequestSigningMiddleware($signer);

$factory = (new Factory())
    ->addMiddleware($middleware, 'custom_signing');
```

---

## Best Practices

### 1. Store Secrets Securely

```php
// Good: Use environment variables
$factory->enableRequestSigning([
    'type' => 'hmac',
    'secret' => env('API_SECRET'),
]);

// Bad: Hardcode secrets
$factory->enableRequestSigning([
    'type' => 'hmac',
    'secret' => 'hardcoded-secret', // Never do this!
]);
```

### 2. Use Appropriate Algorithm

```php
// For security: Use SHA-256 or higher
$factory->enableRequestSigning([
    'type' => 'hmac',
    'algorithm' => 'sha256', // Recommended
]);

// Avoid: SHA-1 (deprecated)
$factory->enableRequestSigning([
    'type' => 'hmac',
    'algorithm' => 'sha1', // Not recommended
]);
```

### 3. Test Signing

```php
// Verify signature is added
$response = $client->get('https://api.example.com/data');
$request = $response->getRequest();

if ($request->hasHeader('X-Signature')) {
    echo "Signature added: " . $request->getHeaderLine('X-Signature') . "\n";
}
```

---

## API Reference

### Factory Methods

```php
$factory->enableRequestSigning(array $config): self
```

### Configuration Options

| Option | Type | Required | Description |
|--------|------|----------|-------------|
| `type` | string | Yes | Signing type: `hmac`, `oauth1` |
| `secret` | string | Yes (HMAC) | Secret key for HMAC |
| `algorithm` | string | No | HMAC algorithm (default: `sha256`) |
| `header_name` | string | No | Header name (default: `X-Signature`) |
| `consumer_key` | string | Yes (OAuth1) | OAuth consumer key |
| `consumer_secret` | string | Yes (OAuth1) | OAuth consumer secret |
| `token` | string | No (OAuth1) | OAuth token |
| `token_secret` | string | No (OAuth1) | OAuth token secret |

---

## Troubleshooting

### Signature Not Added

1. **Check configuration:** Verify signing is enabled
2. **Check request:** Use `getRequest()` to inspect headers
3. **Check options:** Ensure `no_signing` is not set

### Invalid Signature

1. **Check algorithm:** Ensure server expects same algorithm
2. **Check secret:** Verify secret matches server
3. **Check format:** Verify header name matches server expectation

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

