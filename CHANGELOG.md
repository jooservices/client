# Changelog

All notable changes to this package are documented in this file. Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [Semantic Versioning](https://semver.org/).

> [!WARNING]
> **This changelog starts at `v4.0.0`.** The package was fully rebuilt from scratch — a new codebase with fresh git history.
> Earlier releases belong to the archived previous implementation and are **not ancestors** of this line.
> **There is no backward compatibility with any previous version:** no shims, no deprecation bridges, no migration path. Upgrading means rewriting call sites against the new API.

## [Unreleased]

### Added

- `allowRedirects` array options: `total_timeout` bounds the whole redirect chain with a single shared budget (also applied by `withDeadline()`), `track_redirects` exposes the final URL via `X-Joo-Effective-Uri` and the hop list via `X-Joo-Redirect-History`, and `cookies` (default `true`) replays cookies a server set on an earlier hop to a later one. The in-chain cookie jar honours domain, path, and `Secure` matching and never persists across requests.

### Fixed

- Same-host redirects no longer run the public/private DNS target policy, which previously rejected a legitimate relative redirect when the host only resolved via `/etc/hosts` or a transient lookup failed.
- Sensitive headers (including `Cookie`) are no longer stripped when only the port changes or when the scheme upgrades `http` → `https`; they are still stripped on a cross-host redirect or an `https` → `http` downgrade.

## [4.3.0] - 2026-10-02

### Added

- Opt-in response compression: `ClientBuilder::withCompression()` advertises supported encodings (`gzip`, `deflate`, and `br` where the runtime supports it) and returns decoded response bodies for the `CurlTransport` and `GuzzleTransport`. The `compression` request option overrides the builder default for a single request. Disabled by default — existing callers see no behavior change; caller-provided `Accept-Encoding` headers are preserved and decoded responses drop `Content-Encoding` / `Content-Length` / `Transfer-Encoding`.

## [4.2.0] - 2026-08-28

### Added

- `RequestBuilder::withMultipart()` builds `multipart/form-data` bodies (text fields, files, PSR-7 streams) with a generated boundary and matching `Content-Type` header.

### Fixed

- Keep the last base-URI path segment when sending a relative request if the configured value has no trailing slash (`https://site/wp-json` + `wp/v2/posts`).

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

[Unreleased]: https://github.com/jooservices/client/compare/v4.3.0...HEAD
[4.3.0]: https://github.com/jooservices/client/compare/v4.2.0...v4.3.0
[4.2.0]: https://github.com/jooservices/client/compare/v4.1.0...v4.2.0
[4.1.0]: https://github.com/jooservices/client/compare/v4.0.0...v4.1.0
[4.0.0]: https://github.com/jooservices/client/releases/tag/v4.0.0

