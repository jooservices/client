# Retries

Automatic retry logic with exponential backoff.

## Overview

Retry middleware automatically retries failed requests with exponential backoff, improving reliability for transient failures.

## Configuration

### Environment Variables

```env
JOOCLIENT_RETRIES=true
JOOCLIENT_RETRIES_MAX=3
JOOCLIENT_RETRIES_DELAY=1
JOOCLIENT_RETRIES_MIN_ERROR_CODE=500
```

### Code Configuration

```php
use JOOservices\Client\Factory\Factory;

$factory = (new Factory())
    ->enableRetries(
        maxRetries: 3,        // Maximum retry attempts
        delayInSec: 1,        // Base delay in seconds
        minErrorCode: 500     // Only retry 5xx errors
    );
```

## Usage

```php
$factory = (new Factory())
    ->enableRetries(3, 2, 500);

$result = $factory->make();

// Automatically retries on 500, 502, 503, etc.
$response = $result->get('https://api.example.com/unstable');
```

## Retry Behavior

**Example with 3 retries and 2 second delay:**

1. Attempt 1 fails (500) → wait 2 seconds
2. Attempt 2 fails (502) → wait 4 seconds
3. Attempt 3 fails (503) → wait 6 seconds
4. Give up after max attempts

## Retryable Errors

By default, only retries:
- 5xx server errors (500, 502, 503, etc.)
- Connection errors
- Timeout errors

**Does NOT retry:**
- 4xx client errors (400, 401, 404, etc.)
- Status codes < `min_error_code`

## See Also

- **[Circuit Breaker](circuit-breaker.md)** - Prevent cascading failures
- **[Rate Limiting](rate-limiting.md)** - Prevent 429 errors

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
