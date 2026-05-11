# CI/CD

## Workflows

- `ci.yml`: security audit, lint matrix, dependency review, tests and coverage upload, benchmark, optional live tests
- `release.yml`: validate tags, create GitHub releases, and optionally notify Packagist
- `semantic-pr.yml`: PR title validation with uppercase-subject enforcement
- `pr-labeler.yml`: automatic labels
- `secret-scanning.yml`: gitleaks scan
- `scorecard.yml`: OSSF scorecard

## Main CI Flow

- Trigger branches: `develop` and `master`
- `security`: `composer validate --strict` and `composer audit --no-dev --locked --abandoned=fail`
- `lint`: matrix over `lint:pint`, `lint:phpcs`, `lint:phpstan`, `lint:phpmd`, and `lint:cs`
- `dependency-review`: pull-request only and non-blocking
- `tests`: `composer test:coverage`, Codecov upload, and coverage artifact upload
- `benchmark`: `composer bench` after tests
- `live-network`: optional workflow-dispatch job for real external logging verification

## Auxiliary Automation

- `semantic-pr.yml` enforces Conventional Commit types and requires pull-request subjects to start with an uppercase letter.
- `pr-labeler.yml` applies DTO-style labels such as `documentation`, `dependencies`, `ci/cd`, `configuration`, `source`, and `tests`.
- `release.yml` includes a Packagist notification step that runs when credentials are configured.
- `release.yml` validates tagged releases with the same PHPUnit coverage-capable PHP setup used by CI so `composer test` does not fail on missing coverage drivers.

## Branch Model

- Normal implementation work targets `develop`.
- Release preparation moves from `develop` into `master` through a release branch.
- Tag-driven release automation starts from `master`.
