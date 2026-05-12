---
name: release-management
description: "Use when preparing, validating, tagging, or publishing jooservices/client releases."
---

# Release Management Skill

## Repository Truth

- Package: `jooservices/client`
- Versioning follows semantic versioning: `MAJOR.MINOR.PATCH`, tagged as `vX.Y.Z`.
- Normal work starts from `develop` and opens a PR back to `develop`.
- Release branches are named `release/<version>`, start from latest `develop`, and target `master`.
- Never commit directly to `master` or `develop`; all updates to those branches must go through pull requests.
- Stop and ask when version scope, changelog content, branch state, or compatibility impact is unclear.

## Version Decision

- Patch: compatible bug fixes, docs corrections, CI-only maintenance, dependency patch updates.
- Minor: backward-compatible HTTP client features, optional middleware/config additions, new adapters.
- Major: breaking public API behavior, removed methods, changed request/response contracts, dropped PHP or dependency support.

Do not widen Composer constraints or drop supported PHP/dependency versions without explicit approval.

## Preflight

1. Inspect current tags and releases:
   - `git tag --sort=-version:refname`
   - `gh release list --repo jooservices/client`
2. Inspect `CHANGELOG.md`, `README.md`, `composer.json`, `composer.lock`, and release workflow files.
3. Confirm `develop` and `master` are synchronized according to approved Git flow.
4. Validate locally:
   - `composer validate --strict`
   - `composer lint:all`
   - `composer test`
   - `composer ci` when coverage tooling is available locally

## Release Flow

1. Branch `release/<version>` from latest `develop`.
2. Update changelog and release-facing metadata only.
3. Open PR from `release/<version>` to `master`.
4. Merge only after checks pass, required reviews are approved, no requested changes remain, no unresolved review threads remain, and the branch is mergeable.
5. Tag from `master` using `vX.Y.Z`.
6. Create or verify the GitHub release and package publication.
7. Merge `master` back into `develop` through a pull request and normal review/check gates.
8. Clean up only safely merged branches.

## Failure Rules

- Do not bypass failing CI or review feedback.
- Do not force push protected branches.
- If the release workflow, Packagist update, or GitHub release state cannot be verified, stop and report.
