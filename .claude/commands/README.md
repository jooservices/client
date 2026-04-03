# Claude Commands

Custom command definitions for repository-specific tasks can be added here.

## Current Status

No custom command files are committed yet.

## Recommended Command Set

- `quality-check.md`: run `composer lint` and `composer quality`.
- `docs-verify.md`: verify documentation links and numbered index consistency.
- `release-readiness.md`: check release workflow preconditions.

## Notes

- Keep commands aligned with `AGENTS.md` and `CLAUDE.md` requirements.
- Prefer commands that invoke existing composer scripts to avoid drift.
