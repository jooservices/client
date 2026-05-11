# Claude Commands

Repository-specific command playbooks for Claude workflows.

## Available Commands

- `quality-check.md`: final validation flow for code changes.
- `docs-verify.md`: markdown-link and docs-structure verification.
- `release-readiness.md`: release preflight checks.

## Usage Notes

- Keep commands aligned with [AGENTS.md](../../AGENTS.md) and [CLAUDE.md](../../CLAUDE.md).
- Prefer the repository's existing Composer entry points over ad hoc shell replacements.
- When docs change, use [docs-verify.md](docs-verify.md) to check relative Markdown links.
- Normal implementation branches from `develop`; release readiness applies when work is preparing to move into `master`.

