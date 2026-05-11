# JOOservices HTTP Client

A robust, layered HTTP client wrapper designed for extensibility, strict typing, and high performance. Built with a clean, package-oriented architecture that decouples transport integration from client behavior.

[![CI](https://github.com/jooservices/client/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/jooservices/client/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/jooservices/client/branch/develop/graph/badge.svg)](https://codecov.io/gh/jooservices/client)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/jooservices/client/badge)](https://securityscorecards.dev/viewer/?uri=github.com/jooservices/client)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.5-blue)](https://php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](https://opensource.org/licenses/MIT)
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

Request and response body logging should stay opt-in. Keep `logBodies: false` unless the integration explicitly needs body-level diagnostics and the payload is safe to record.

## Quality Assurance

The repository uses the DTO-style quality contract with a few client-specific additions.

```bash
composer lint:all
composer test
composer check
```

Additional validation commands:

- `composer lint:fix`
- `composer test:coverage`
- `composer bench`
- `composer ci`

Intentional client-specific differences from the DTO baseline:

- 98% coverage gate on `composer test:coverage`
- dedicated benchmark workflow with PHPBench
- optional live-network workflow for real external IP logging checks
- active CI secret scanning via `secret-scanning.yml`

Repository-standard auxiliary automation now also matches DTO more closely:

- semantic PR titles require an uppercase subject
- pull requests are auto-labeled with DTO-style label categories
- releases validate tags before publishing GitHub releases and can notify Packagist when credentials are configured

Coverage remains an intentional client-specific divergence: this repo keeps a 98% gate and a narrower excluded-source set so the enforced threshold stays meaningful for the exercised client runtime surface.

## AI Development Workflow

This package includes AI-oriented scaffolding to keep delivery consistent with quality gates.

- Agent guidance: [AGENTS.md](AGENTS.md), [CLAUDE.md](CLAUDE.md)
- Tooling folders: `.claude/commands`, `.cursor/rules`, `ai/skills`, `antigravity/prompts`, `jetbrains/prompts`
- Development process references: [docs/04-development](docs/04-development), [docs/05-maintenance](docs/05-maintenance)

When AI changes code, run:

```bash
composer lint:all
composer test
composer check
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

Normal feature and fix work branches from `develop` and PRs back into `develop`. Release preparation uses `release/<version>` branches from `develop` into `master`.
