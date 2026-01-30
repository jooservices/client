# Response Validation Guide

Complete guide to validating API responses against schemas.

> **Related Documentation:**
> - [Architecture](../architecture/ARCHITECTURE.md) - System design
> - [Usage Guide](USAGE_GUIDE.md) - General usage patterns

---

## Overview

Response validation ensures API responses match expected structure, catching API contract violations early.

**Key Features:**
- ✅ Laravel-style validation rules
- ✅ Automatic validation via `validate()` method
- ✅ Throws `ValidationException` on failure
- ✅ Returns validation errors array
- ✅ Integrates with `ResponseWrapper`

---

## Quick Start

### Basic Usage

```php
use JOOservices\Client\Factory\Factory;

$factory = new Factory();
$client = $factory->make();

$response = $client->get('https://api.example.com/users/1');

// Validate response
$errors = $response->validate([
    'id' => 'integer|required',
    'name' => 'string|required|min:1',
    'email' => 'email|required',
]);

if (empty($errors)) {
    echo "Response is valid!\n";
} else {
    foreach ($errors as $field => $message) {
        echo "{$field}: {$message}\n";
    }
}
```

---

## Validation Rules

### Available Rules

| Rule | Description | Example |
|------|-------------|---------|
| `required` | Field must be present | `'name' => 'required'` |
| `integer` | Field must be an integer | `'id' => 'integer|required'` |
| `string` | Field must be a string | `'name' => 'string|required'` |
| `email` | Field must be a valid email | `'email' => 'email|required'` |
| `min:N` | String length or number minimum | `'name' => 'string|min:1'` |
| `max:N` | String length or number maximum | `'age' => 'integer|max:120'` |
| `array` | Field must be an array | `'tags' => 'array|required'` |

---

## Throwing on Validation Failure

### Automatic Exception

```php
$response = $client->get('https://api.example.com/users/1');

try {
    $response->validate([
        'id' => 'integer|required',
        'name' => 'string|required',
    ], true); // true = throw on invalid
} catch (\JOOservices\Client\Exceptions\Validation\ValidationException $e) {
    echo "Validation failed: {$e->getMessage()}\n";
    foreach ($e->getErrors() as $field => $message) {
        echo "  {$field}: {$message}\n";
    }
}
```

---

## Complex Validation

### Nested Arrays

```php
$response = $client->get('https://api.example.com/users');

$errors = $response->validate([
    'users' => 'array|required',
    // Note: Nested validation not yet supported
    // For nested validation, validate each item separately
]);

if (empty($errors)) {
    $users = $response->getContent()['users'] ?? [];
    foreach ($users as $index => $user) {
        // Validate each user
        $userResponse = new \JOOservices\Client\Http\ResponseWrapper(
            new \GuzzleHttp\Psr7\Response(200, [], json_encode($user))
        );
        $userErrors = $userResponse->validate([
            'id' => 'integer|required',
            'name' => 'string|required',
        ]);
        // Handle errors...
    }
}
```

---

## Best Practices

### 1. Validate Early

```php
$response = $client->get('https://api.example.com/users/1');

// Validate before using
$errors = $response->validate([
    'id' => 'integer|required',
    'name' => 'string|required',
]);

if (!empty($errors)) {
    // Handle validation errors
    return;
}

// Safe to use response
$user = $response->getContent();
```

### 2. Use Descriptive Rules

```php
// Good: Clear and specific
$errors = $response->validate([
    'id' => 'integer|required',
    'email' => 'email|required',
    'age' => 'integer|min:18|max:120',
]);

// Bad: Too generic
$errors = $response->validate([
    'id' => 'required',
    'email' => 'required',
]);
```

### 3. Handle Validation Errors Gracefully

```php
$errors = $response->validate([...]);

if (!empty($errors)) {
    logger()->warning('API response validation failed', [
        'errors' => $errors,
        'response' => $response->getContent(),
    ]);
    
    // Fallback or retry logic
}
```

---

## API Reference

### ResponseWrapper Methods

```php
$response->validate(array $rules, bool $throwOnInvalid = false): array
```

### ValidationException

```php
$exception->getErrors(): array<string, string>
$exception->getFirstError(): ?string
```

---

## Limitations

- Nested array validation not yet supported (validate items separately)
- JSON Schema support planned for future release
- Custom validation rules not yet supported

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.

