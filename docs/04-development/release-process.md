# Release Process

## Versioning

- Follow semantic versioning.
- Tag format: `vX.Y.Z`.

## Release Flow

1. Start from the latest `develop` branch.
2. Create `release/<version>` from `develop`.
3. Limit release-branch changes to changelog, release metadata, workflow-safe fixes, and final stabilization.
4. Run `composer check` and `composer ci`.
5. Open the release PR from `release/<version>` into `master`.
6. Merge the approved release PR into `master`.
7. Create and push the `vX.Y.Z` tag from `master`.
8. Validate GitHub release artifacts and optional Packagist notification.
9. Merge `master` back into `develop` so release metadata stays synchronized.

## Current Note

- Standards alignment work can land on `develop` without creating a release tag.
