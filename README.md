# jooservices/client

[![CI](https://github.com/jooservices/client/actions/workflows/ci.yml/badge.svg?branch=develop)](https://github.com/jooservices/client/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/jooservices/client/graph/badge.svg?token=LUIWX086RP)](https://codecov.io/gh/jooservices/client)
[![Quality gate status](https://sonarcloud.io/api/project_badges/measure?project=jooservices_client&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=jooservices_client)
[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/jooservices/client/badge)](https://securityscorecards.dev/viewer/?uri=github.com/jooservices/client)
[![PHP Version](https://img.shields.io/badge/PHP-8.5%2B-blue.svg)](https://www.php.net/)
[![Release](https://img.shields.io/badge/version-4.1.0-blue.svg)](CHANGELOG.md)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

A PHP 8.5+ PSR-18 HTTP client with a strict standards core and batteries included: fluent request building, a ranked middleware pipeline, resilience (retry, circuit breaker, rate limit, bulkhead, fallback, deadline), hardened security defaults, response-to-DTO mapping via `jooservices/dto`, and deterministic test fakes.

> [!WARNING]
> **`v4.0.0` is a complete ground-up rebuild around PSR-7 / PSR-17 / PSR-18 and is NOT backward compatible with any previous version.**
> Client verb methods and Guzzle option bags are gone; there are no legacy shims, no deprecation bridges, and no compatibility code.
> Upgrading means rewriting call sites against the new API — see [About v4.0.0](#about-v400), [`UPGRADE-4.0.md`](UPGRADE-4.0.md), and the [changelog](CHANGELOG.md).

## About v4.0.0

| | |
| --- | --- |
| Status | **`v4.1.0` — current release** |
| First public line | `v4.0.0` — earlier releases belong to the retired implementation ([changelog](CHANGELOG.md) starts here) |
| Compatibility | **None with older versions.** Client verbs (`$client->get()`, `post()`, …) and Guzzle-style option bags are removed |
| Core contract | PSR-18 `sendRequest(RequestInterface)` — HTTP 4xx/5xx responses are returned, never thrown |
| Per-request options | Portable `RequestOptions` DTO accepted by `send($request, $options)` — timeout, connect timeout, proxy, TLS verification, redirects |
| Exceptions | HTTP-status exceptions are opt-in via `Response::from($response)->throw()` |
| Response mapping | `jooservices/dto ^3.0` integration through `Response::toDto()` / `collect()` |

## Highlights vs the previous line

| Area | Previous | This rebuild (`v4.0.0`) |
| --- | --- | --- |
| Core interface | Client verb methods | Strict PSR-18 `HttpClient`: `sendRequest()` + `send($request, $options)` |
| Request options | Guzzle option bags | Portable `RequestOptions` DTO validated at the boundary |
| Requests | Ad hoc construction | Fluent immutable `RequestBuilder` → `PreparedRequest` (`toPsr()`, `options()`) |
| Responses | Direct PSR-7 handling | `Response` wrapper: status helpers, cached JSON, download-size ceiling, opt-in `throw()`, DTO mapping |
| Middleware | — | 22 ranked middleware with canonical ordering, presets, and `insertMiddlewareBefore()` / `insertMiddlewareAfter()` |
| Resilience | — | Retry, circuit breaker, rate limit, bulkhead, fallback, deadline — pluggable in-memory / PSR-16 stores |
| Security | — | TLS-on-by-default, CR/LF rejection, credential stripping on redirects, private-IP redirect policy, log sanitizer |
| Testing | — | Deterministic fakes: `ClientBuilder::fake()`, `HttpFakeRegistry`, `TestResponseSequence`, `assertSent()` |

## Features

**Core**

- PSR-18 `HttpClient` built through the immutable `ClientBuilder`; base URI resolution rejects protocol-relative URIs
- Fluent `RequestBuilder`: verb methods, headers, query, raw body, `withJson()` (auto `Content-Type`)
- `Response::from()`: status helpers, header access, BOM-stripping cached JSON, 100 MB body ceiling, `throw()`, `toPsrResponse()` escape hatch
- Per-request portable options layered over builder defaults

**Middleware pipeline**

- Observability: logging (sanitized), metrics, correlation ID, trace context, progress
- Resilience: retry, circuit breaker, rate limit, bulkhead, fallback, deadline, request coalescing
- Auth/security: authentication, OAuth token refresh, HMAC request signing, idempotency keys, WAN-IP awareness
- DX: user agent (fixed / generated / rotating), API version, cache, response validation, interceptors (`onRequest()` / `onResponse()` / `onError()`)
- Canonical outermost-to-innermost ranking is applied on `build()` by default. `withStandardMiddlewareOrder()` and `withProductionMiddlewareOrder()` are explicit aliases of that same ranked list; custom middleware must be placed with `insertMiddlewareBefore()` / `insertMiddlewareAfter()` or `build()` rejects it as unranked

**Resilience state**

- Validated config DTOs: `RetryConfig`, `CircuitBreakerConfig`, `RateLimitConfig`, `BulkheadConfig`, `FallbackConfig`
- In-memory stores by default; swap in PSR-16 adapters for persistent, multi-process resilience state
- Dedicated exceptions: `CircuitOpenException`, `RateLimitExceededException`, `BulkheadRejectedException`

**Transports**

- `CurlTransport` (default) — native cURL with streaming response bodies
- `PsrTransport` — wraps any PSR-18 client; `GuzzleTransport` via optional `guzzlehttp/guzzle`
- `FailoverTransport` — ordered transport list with capability reporting

**Security defaults**

- TLS certificate verification enabled out of the box; CR/LF header injection rejected
- Cross-origin redirects strip credential headers (including common API-key/token names)
- Public-origin redirects to private/link-local IP targets rejected (explicit opt-in available)
- Response download-size guard; sensitive data redacted from logs

**Validation, auth, testing**

- Opt-in JSON Schema response validation (`justinrainbow/json-schema`)
- Bearer token, API key, basic auth; `HmacSha256Signer` for request signing
- Deterministic testing: fake registry, scripted `TestResponseSequence`s, recorded-request assertions, `InteractsWithHttpClient` trait

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

## Design contract

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

- [Changelog](CHANGELOG.md) — starts at `v4.0.0`
- [`UPGRADE-4.0.md`](UPGRADE-4.0.md) — migrating from pre-v4 APIs
- [`WORKFLOWS.md`](WORKFLOWS.md) — CI and release workflow notes

## Development

All tooling runs inside Docker (`php:8.5-cli-bookworm` via Docker Compose); Composer downloads dependencies from Packagist.

```bash
make build     # build the tooling image
make install   # composer install in the container
```

| Command | Purpose |
| --- | --- |
| `make validate` | `composer validate --strict` |
| `make lint` | Pint, PHPCS, PHPStan, PHPMD, PHP-CS-Fixer |
| `make test` | PHPUnit (no coverage) |
| `make test-coverage` | PHPUnit with PCOV Clover coverage |
| `make bench` | phpbench |
| `make ci` | lint + coverage run + coverage gate (local CI parity) |

Coverage is enforced at an **85% floor** by the reusable `tools/coverage-enforce.php`.

Git hooks are opt-in so installing this library outside a Git checkout never fails: run `composer hooks:install` from a clone when you want commit-message, lint, and test hooks (Captainhook).

## Branch model & CI

- `master` — production; `develop` — integration
- Feature/fix branches from `develop`, PR back into `develop`; releases via tags from `master`; hotfixes from `master`
- PRs required, all CI checks green before merge

Required CI flow (Docker-based quality gate):

```text
Pull-request gate (ci.yml):
docker build → composer install → validate --strict
  → lint ×5 (Pint, PHPCS, PHPStan, PHPMD, PHP-CS-Fixer)
  → PHPUnit coverage (PCOV) → 85% Clover floor

Post-merge sanity (ci-post-merge.yml):
same gate on push heads
```

Workflows:

| Workflow | Trigger | Purpose |
| --- | --- | --- |
| `ci.yml` | pull_request → `master` / `develop` | Full quality gate: validate, lint ×5, tests with coverage, 85% floor |
| `ci-post-merge.yml` | push → `master` / `develop` | Same gate on merged heads |
| `codeql.yml` | push/PR; weekly | CodeQL analysis |
| `commitlint.yml` | pull_request | Conventional Commits on every PR commit |
| `semantic-pr.yml` | pull_request | Conventional Commits PR title |
| `pr-labeler.yml` | pull_request | Path labels |
| `release.yml` | tag `v*.*.*` | Tag reachability from `master`, full quality gate, GitHub Release |
| `scorecard.yml` | push to `master`; weekly | OpenSSF Scorecard |
| `workflow-audit.yml` | `.github/**` changes; weekly | actionlint + zizmor on workflow files |
| `link-check.yml` | weekly | Markdown link check |
| `stale.yml` | daily | Stale issue/PR housekeeping |

Quality gates: Pint · PHPCS · PHPStan · PHPMD · PHP-CS-Fixer · 85% coverage floor.

**CI secrets:** none required — the entire gate runs inside Docker via Composer scripts; no third-party upload steps (Codecov / Sonar) are wired.

## Community

- [Contributing guide](CONTRIBUTING.md) — setup, git workflow, commit convention, quality gates, PR rules
- [Security policy](SECURITY.md) — how to report vulnerabilities privately
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Support](SUPPORT.md)
- [Governance](GOVERNANCE.md)

## License

MIT — see [LICENSE](LICENSE).
