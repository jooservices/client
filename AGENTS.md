# JOOservices Client Repository Instructions

This repository builds `jooservices/client`, a PHP 8.5+ HTTP client package under the `JOOservices\Client\` namespace.

## Core intent

- Preserve the public API and package identity before introducing abstractions.
- Keep changes minimal, typed, and aligned with the current `src/` module layout.
- Treat docs, workflows, hooks, and tests as part of the implementation, not follow-up work.
- If requirements are unclear, conflicting, or unsupported by the real repository state, stop and ask instead of guessing.

## Package-specific constraints

- Keep the layered client design intact: adapters own transport integration, middleware owns resilience and observability, and value objects or DTO-backed config remain the typed contract.
- Preserve client-specific features that intentionally differ from DTO: retry, circuit breaker, async or batch handling, cache integration, benchmark tooling, and structured logging.
- Keep PSR contracts explicit. Do not add Laravel application layers to the core package.
- Do not log sensitive request or response bodies by default. Body logging must stay opt-in and explicit.
- Do not remove runtime assets such as `config/`, `scripts/`, `phpbench.json`, `docker-compose.yml`, or `Dockerfile` unless the task explicitly requires it.

## Repository quality rules

- Formatting authority: `Pint`
- Narrow cleanup: `PHP-CS-Fixer`
- Structural checks: `PHPCS`
- Static analysis: `PHPStan`
- Maintainability checks: `PHPMD`
- Tests: `PHPUnit`

## Required command map

- `composer lint`
- `composer lint:all`
- `composer lint:fix`
- `composer bench`
- `composer test`
- `composer test:coverage`
- `composer check`
- `composer ci`

## Git branch workflow

- `develop` is the integration branch for normal feature and fix work.
- `master` is the stable release branch.
- Normal implementation branches start from the latest `develop` and open PRs back into `develop`.
- Release branches use `release/<version>` from `develop`, then open PRs into `master`.
- Tags are created from `master`, and release or hotfix changes must be merged back into `develop`.
- Never commit directly to `develop` or `master` unless an approved emergency procedure explicitly requires it.

## Completion checklist

1. Keep the change minimal and package-appropriate.
2. Update docs and contributor guidance when public behavior or workflow changes.
3. Run the relevant composer validation commands.
4. Re-check hooks, workflows, and release impact when repository metadata changes.
5. Use Conventional Commit titles for commits and PRs.
