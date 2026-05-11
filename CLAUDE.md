# CLAUDE

Guidance for AI-assisted development in `jooservices/client`.

## Package Identity

- HTTP client wrapper on top of Guzzle
- Middleware pipeline for resilience and observability
- DTO or value-object configuration with PSR-aligned contracts
- Optional MongoDB-ready logging integration

## Working Rules

- Inspect the real repository state before changing behavior, workflow, or docs.
- Pint wins if formatting tools disagree.
- Keep request and response body logging opt-in; do not widen sensitive logging defaults.
- If repo truth is missing or conflicting, stop and ask instead of inferring.

## Branch Workflow

- Branch normal work from `develop`.
- Open feature or fix PRs into `develop`.
- Use `release/<version>` branches from `develop` for release preparation into `master`.

## Required Checks Before Finalizing

- `composer lint:all`
- `composer test`
- `composer test:coverage` for coverage-sensitive or CI-sensitive changes
- `composer check`
- `composer ci` for workflow, composer, or release-surface changes
- Verify docs links after documentation changes
