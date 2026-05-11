# Release Readiness

Review release prerequisites before tagging or merging release-sensitive changes.

## Use When

- Version metadata, changelog, workflows, or publishing configuration changed.
- A release tag is about to be created.

## Required Checks

1. Run `composer check`.
2. Run `composer ci`.
3. Run [docs-verify.md](docs-verify.md) if release notes or documentation changed.

## Release Preconditions

- `CHANGELOG.md` reflects the intended release.
- Workflow files under `.github/workflows/` still match the documented release process.
- Optional publishing secrets are treated as optional and not assumed to exist locally.
- Any Packagist notification step remains non-destructive when secrets are missing.

## Expected Outcome

- The branch is ready for the release workflow's `validate`, `release`, and optional `publish` stages.
- Any repo-specific divergence from DTO is documented rather than hidden.