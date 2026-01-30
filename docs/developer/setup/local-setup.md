# Local Development (Docker-free, mock-first)

## Overview
Run JOOClient locally with PHP 8.5+ and Composer. Tests are Guzzle-mock-first; external services are optional and only needed for integration-style checks (e.g., observing real DB/Redis writes).

## Quick Start
1) Install PHP 8.5+ and Composer.
2) Install deps:
```bash
composer install
```
3) Run tests (mock-first, no services required):
```bash
composer test
```
4) Try a lightweight example:
```bash
php examples/01-basic-usage.php
```

## Optional integrations (only when you really need them)
- **MySQL/Mongo/Redis**: Start the service locally and enable the corresponding driver in your config before running examples that persist logs or cache. Not required for the default test suite.
- **Migrations**: Publish/run only when testing database logging manually; skip for mock-only development.

## Minimal env template
```env
JOOCLIENT_LOGGING_ENABLED=false
JOOCLIENT_CACHE_ENABLED=false
JOOCLIENT_RETRIES_MAX=3
JOOCLIENT_RETRIES_DELAY=1
```
Turn on drivers only when the backing service is available.

## Troubleshooting
- Tests try to hit external services: unset related env vars to stay mock-only.
- MySQL/Mongo/Redis connection errors: disable the driver or ensure the service is running locally before re-enabling.
- No logs appear when testing DB logging: ensure the driver is enabled and call `flushLogger()` if batching is on.
