# JOOservices HTTP Client

A robust, layered HTTP Client wrapper designed for extensibility, strict typing, and high performance. Built with a "Clean Architecture" approach, decoupling the business logic from the underlying Guzzle transport.

[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.5-blue)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%209-success)](phpstan.neon)
[![Coverage](https://img.shields.io/badge/Coverage-%3E%3D98%25-success)](docs/04-development/testing.md)
[![Docker](https://img.shields.io/badge/Docker-enabled-2496ED?logo=docker&logoColor=white)](Dockerfile)
[![Packagist](https://img.shields.io/packagist/v/jooservices/client)](https://packagist.org/packages/jooservices/client)
[![Latest Release](https://img.shields.io/github/v/release/jooservices/client)](https://github.com/jooservices/client/releases)
[![AI Workflow](https://img.shields.io/badge/AI-Workflow-informational)](docs/04-development/ai-skills.md)

## Features

- **Strictly Typed**: Configuration object (`ClientConfig`) ensures type safety before requests start.
- **Layered Architecture**: Adapters (Guzzle) are isolated from Core Logic.
- **Resilience**: Built-in Retry (Backoff/Jitter) and Circuit Breaker.
- **Async & Concurrency**: Support for non-blocking requests (`getAsync`) and Batch Processing.
- **Observability**: detailed Logging and PSR-16 Caching integration.
- **Performance**: < 10μs overhead per request.

## Installation

```bash
composer require jooservices/client
```

## Quick Start

### Basic Usage

Use the **ClientBuilder** to create an instance.

```php
use JOOservices\Client\Client\ClientBuilder;

$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.com')
    ->withTimeout(5)
    ->withHeader('Authorization', 'Bearer token')
    ->build();

$response = $client->get('/users/1');

echo $response->status(); // 200
print_r($response->json()); // ['id' => 1, ...]
```

### Async Requests & Batching

```php
// Single Async Request
$promise = $client->getAsync('/users/1');
$response = $promise->wait();

// Batch Processing (Concurrent)
$results = $client->batch([
    'user1' => fn() => $client->getAsync('/users/1'),
    'user2' => fn() => $client->getAsync('/users/2'),
]);

print_r($results['user1']->json());
```

## Advanced Configuration

### Resilience (Retry & Circuit Breaker)

```php
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Resilience\CircuitBreakerConfig;

$client = ClientBuilder::create()
    ->withRetry(new RetryConfig(
        maxAttempts: 3,
        baseDelayMs: 100
    ))
    ->withCircuitBreaker(new CircuitBreakerConfig(
        failureThreshold: 5,
        recoveryTimeoutMs: 10000
    ))
    ->build();
```

### Logging & Caching

```php
use JOOservices\Client\Logging\MonologFactory;
use JOOservices\Client\Cache\FilesystemCache;

$logger = MonologFactory::createDaily('my-app', __DIR__ . '/logs');
$cache = new FilesystemCache(__DIR__ . '/cache');

$client = ClientBuilder::create()
    ->withLogger($logger, logBodies: true)
    ->withCache($cache, defaultTtl: 3600)
    ->build();
```

## Quality Assurance

We use strict static analysis and testing.

```bash
composer quality
```

This runs:
- **Pint**: Code Style Fixer
- **PHPStan**: Static Analysis (Level 9)
- **PHPUnit**: Unit, Feature & Integration Tests (with 98% coverage gate)
- **PHPBench**: Performance Analysis

## AI Development Workflow

This package includes AI-oriented scaffolding to keep delivery consistent with quality gates.

- Agent guidance: [AGENTS.md](AGENTS.md), [CLAUDE.md](CLAUDE.md)
- Tooling folders: `.claude/commands`, `.cursor/rules`, `ai/skills`, `antigravity/prompts`, `jetbrains/prompts`
- Development process references: [docs/04-development](docs/04-development)

When AI changes code, run:

```bash
composer lint
composer quality
```

## Docker Development

If PHP is not installed locally, run everything in Docker.

```bash
docker compose up -d --build mongodb
docker compose run --rm php composer install
docker compose run --rm php composer test
```

For live network integration tests (real sites), run:

```bash
docker compose run --rm -e JOOCLIENT_RUN_LIVE_NETWORK_TESTS=1 php \
    vendor/bin/phpunit tests/Feature/Logging/RealSiteIpLoggingTest.php
```

This test hits:
- `https://httpbin.org/get`
- `https://example.com`
- `https://google.com`

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md) for details.
