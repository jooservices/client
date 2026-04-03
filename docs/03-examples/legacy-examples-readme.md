# Runnable Examples

This directory contains runnable PHP scripts demonstrating various features of JOOClient.

## Prerequisites

Run `composer install` in the project root to load dependencies.

## How to Run

Execute any script using PHP from the project root:

```bash
php docs/03-examples/01-basic-get.php
```

## Available Examples

### Basics
- **[01-basic-get.php](01-basic-get.php)**: Simple GET request to a public API.
- **[02-post-with-json.php](02-post-with-json.php)**: POST request with JSON payload.

### Async & Performance
- **[03-async-requests.php](03-async-requests.php)**: Sending multiple requests concurrently.

### Resilience
- **[04-error-handling.php](04-error-handling.php)**: Handling timeouts and 404/500 errors.

### Logging
- **[05-middleware-logging.php](05-middleware-logging.php)**: Setting up Monolog logging.
