# jooservices/client

[![CI](https://github.com/jooservices/client/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/jooservices/client/actions/workflows/ci.yml)
[![Coverage (develop)](https://codecov.io/gh/jooservices/client/branch/develop/graph/badge.svg?token=LUIWX086RP)](https://codecov.io/gh/jooservices/client/branch/develop)
[![Quality Gate (master)](https://sonarcloud.io/api/project_badges/measure?project=jooservices_client&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=jooservices_client)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/jooservices/client/badge)](https://securityscorecards.dev/viewer/?uri=github.com/jooservices/client)
[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-blue.svg)](https://www.php.net/)
[![GitHub Release](https://img.shields.io/github/v/release/jooservices/client?display_name=tag)](https://github.com/jooservices/client/releases)
[![Packagist Version](https://img.shields.io/packagist/v/jooservices/client)](https://packagist.org/packages/jooservices/client)
[![Total Downloads](https://img.shields.io/packagist/dt/jooservices/client)](https://packagist.org/packages/jooservices/client)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A PHP 8.5+ PSR-18 HTTP client with a strict standards core and batteries included: fluent request building, a ranked middleware pipeline, resilience (retry, circuit breaker, rate limit, bulkhead, fallback, deadline), hardened security defaults, response-to-DTO mapping via `jooservices/dto`, and deterministic test fakes.

> [!WARNING]
> **`v4.0.0` is a complete ground-up rebuild around PSR-7 / PSR-17 / PSR-18 and is NOT backward compatible with any previous version.**
> Client verb methods and Guzzle option bags are gone; there are no legacy shims, no deprecation bridges, and no compatibility code.
> Upgrading means rewriting call sites against the new API — see [`UPGRADE-4.0.md`](UPGRADE-4.0.md) and the [changelog](CHANGELOG.md).

## Upgrade highlights

- v4 is a strict PSR-18 `HttpClient` — `sendRequest()` plus `send($request, $options)`; verb methods are removed.
- Guzzle option bags are replaced by a portable, validated `RequestOptions` DTO.
- Fluent immutable `RequestBuilder` produces a `PreparedRequest` (PSR-7 form plus options).
- `Response` wrapper adds status helpers, cached JSON, download-size ceiling, opt-in `throw()`, and DTO mapping.
- Deterministic test fakes replace live-network dependencies.

## Features

- PSR-18 `HttpClient` built through the immutable `ClientBuilder`; base URI treated as a directory prefix, protocol-relative URIs rejected.
- Fluent `RequestBuilder`: verb methods, headers, query, raw body, `withJson()` / `withMultipart()` (auto `Content-Type`).
- `Response::from()`: status helpers, header access, BOM-stripping cached JSON, 100 MB body ceiling, `throw()`, `toPsrResponse()` escape hatch.
- Ranked middleware pipeline with canonical ordering and presets: observability, resilience, auth/security, and DX.
- Resilience: retry, circuit breaker, rate limit, bulkhead, fallback, deadline — in-memory stores by default, PSR-16 adapters supported.
- Security defaults: TLS verification on, CR/LF injection rejected, credential stripping and private-IP policy on redirects, download-size guard, log sanitizer.
- Transports: `CurlTransport` (default), `PsrTransport`, `GuzzleTransport` (optional), `FailoverTransport`.
- Deterministic testing: fake registry, scripted `TestResponseSequence`s, recorded-request assertions, `InteractsWithHttpClient` trait.

## Requirements

- PHP `>= 8.5`
- Extension: `curl`
- Core dependencies: `psr/http-client`, `psr/http-message`, `psr/http-factory`, `nyholm/psr7`, `jooservices/dto ^3.0`
- Optional: `guzzlehttp/guzzle` (GuzzleTransport), `justinrainbow/json-schema` (schema validation), `psr/log` (logging), `psr/simple-cache` (cache middleware + persistent stores)
- Docker (recommended — all local tooling runs in `php:8.5-cli-bookworm`)

## Installation

```bash
composer require jooservices/client:^4.0
```

## Quick start

```php
use JOOservices\Client\Client\ClientBuilder;
use JOOservices\Client\Response\Response;
use JOOservices\Client\Resilience\RetryConfig;
use JOOservices\Client\Testing\RecordedRequest;
use JOOservices\Client\Testing\TestResponse;
use JOOservices\Client\Testing\TestResponseSequence;

// Build once — immutable configuration, canonical middleware ranking
$client = ClientBuilder::create()
    ->withBaseUri('https://api.example.test/v1')
    ->withBearerToken($token)
    ->withRetry(new RetryConfig(maxAttempts: 3))
    ->build();

// Fluent request construction → PreparedRequest (PSR-7 form + portable options)
$request = $client->requestBuilder()->post('users')->withJson($user)->build();

$upload = $client->requestBuilder()->post('media')->withMultipart([
    ['name' => 'title', 'contents' => 'Photo'],
    ['name' => 'file', 'contents' => fopen($photoPath, 'rb'), 'filename' => 'photo.jpg', 'contentType' => 'image/jpeg'],
])->build();

// PSR-18 send; per-request options override builder defaults
$psrResponse = $client->send($request->toPsr(), $request->options());

// Opt-in HTTP-status exception, then map the body to a DTO
$response = Response::from($psrResponse)->throw()->toDto(UserDto::class);

// Deterministic tests — no network
ClientBuilder::fake();
ClientBuilder::respond(
    'GET',
    'users/*',
    (new TestResponseSequence())->push(TestResponse::json(['id' => 'u_123'])),
);
ClientBuilder::assertSent(
    fn (RecordedRequest $record) => $record->request->getMethod() === 'GET',
);
```

## Design notes

- `sendRequest(RequestInterface)` is strict PSR-18: HTTP 4xx/5xx responses are returned, not thrown. Use `Response::from($response)->throw()` when status exceptions are wanted.
- Each layer owns one concern:

| Layer | Responsibility |
| --- | --- |
| `ClientBuilder` | Immutable configuration; canonical middleware ranking; terminal `build(): HttpClient` |
| `HttpClient::send($request, $options)` | Default headers, base URI resolution, portable per-request options |
| `RequestBuilder` | Fluent request construction → `PreparedRequest` with PSR-7 form plus options |
| `Response` | Optional convenience over PSR-7; always returns the raw response via `toPsrResponse()` |

- Builder options are defaults, never overrides: explicit `send()` / `RequestBuilder` options win per request.

## Documentation

- [Changelog](CHANGELOG.md) — version history and upgrade notes
- [`UPGRADE-4.0.md`](UPGRADE-4.0.md) — migrating from pre-v4 APIs
- [Development workflows](WORKFLOWS.md) — branches, CI, releases, and repository automation
- [Security policy](SECURITY.md) — private vulnerability reporting

## Development

All PHP tooling runs inside Docker (`php:8.5-cli-bookworm` via Docker Compose); Composer downloads dependencies from Packagist.

```bash
make build     # build the tooling image
make install   # composer install in the container
make shell     # interactive container shell
```

| Command | Purpose |
| --- | --- |
| `make validate` | `composer validate --strict` |
| `make lint` | Pint, PHPCS, PHPStan, PHPMD, PHP-CS-Fixer |
| `make test` | PHPUnit (Unit + Integration, no coverage) |
| `make test-coverage` | PHPUnit with PCOV Clover coverage |
| `make audit` | Composer audit |
| `make bench` | phpbench |
| `make ci` | lint + coverage run + 85% coverage gate (local CI parity) |

Coverage is enforced at an **85% floor** by `tools/coverage-enforce.php`. Git hooks are opt-in: run `composer hooks:install` from a clone when you want commit-message, lint, and test hooks (Captainhook).

## Community

- [Contributing guide](CONTRIBUTING.md) — setup, git workflow, commit convention, quality gates, PR rules
- [Security policy](SECURITY.md) — how to report vulnerabilities privately
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Support](SUPPORT.md)
- [Governance](GOVERNANCE.md)

## License

MIT — see [LICENSE](LICENSE).
