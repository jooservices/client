# Changelog

All notable changes to this package are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [Semantic Versioning](https://semver.org/).

> [!WARNING]
> **This changelog starts at `v4.0.0`.** The package was fully rebuilt from scratch — a new codebase with fresh git history.
> Earlier releases belong to the archived previous implementation and are **not ancestors** of this line.
> **There is no backward compatibility with any previous version:** no shims, no deprecation bridges, no migration path. Upgrading means rewriting call sites against the new API.

## [Unreleased]

## [4.1.0] - 2026-08-28

### Fixed

- Apply the canonical middleware order on `build()` by default. `withStandardMiddlewareOrder()` and `withProductionMiddlewareOrder()` remain explicit aliases of the same ranked list.
- Include `Cookie` in the HTTP cache key principal and omit `Set-Cookie` / `Set-Cookie2` from stored responses so cookie sessions cannot leak across callers.
- Bracket IPv6 addresses in cURL `CURLOPT_RESOLVE` pins (`host:port:[2001:db8::1]`).
- Sanitize exception messages in `LoggingMiddleware` (cURL errors often embed URLs with query secrets) and treat `credential` as a secret needle in `LogSanitizer`.

## [4.0.0] - 2026-08-25

- Rebuilt the client around PSR-7, PSR-17 and PSR-18.
- Added outbound middleware, resilience, transports and deterministic fakes.
- Added cross-origin redirect credential protection and public-to-private redirect policy.
- Added streaming cURL bodies, PSR-16 resilience adapters, optional JSON Schema validation, WAN-IP middleware, reusable fakes, Docker quality gates, CI/release workflows and benchmark support.

[Unreleased]: https://github.com/jooservices/client/compare/v4.1.0...HEAD
[4.1.0]: https://github.com/jooservices/client/compare/v4.0.0...v4.1.0
[4.0.0]: https://github.com/jooservices/client/releases/tag/v4.0.0

