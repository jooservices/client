# jooservices/client

This file adds project-only rules.

- PHP `>= 8.5`, PSR-18 `HttpClient` + `jooservices/dto ^3.0` for response mapping
- First public line: **`v4.0.0`**; current line: **`v4.2.0`** — no backward compatibility with pre-v4
- All PHP tooling via Docker (`php:8.5-cli-bookworm`)
- CI on GitHub-hosted `ubuntu-latest` runners via `tools/ci/docker-compose`
- Lints at **max** with **no ignore**: Pint `per`, PHPCS, PHPStan max + strict rules, PHPMD, PHP-CS-Fixer
- Coverage floor **85%** (`tools/coverage-enforce.php`); Unit + Integration suites
- HTTP 4xx/5xx from `sendRequest()` are returned, never thrown — use `Response::throw()` when needed
- Branch model: `develop` for integration, `master` for production, tags from `master`
