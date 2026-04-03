# AI Skills

## Purpose

Define how AI assistants should work in this repository and where AI-specific guidance lives.

## Scope

- Keep package purpose intact: HTTP client + logging + MongoDB config.
- Prefer minimal diffs and preserve public API compatibility.
- Validate quality commands before finalizing changes.

## AI Guidance Locations

- `AGENTS.md`: Core behavior and package constraints for coding agents.
- `CLAUDE.md`: Required final validation commands (`composer lint`, `composer quality`, docs-link verification).
- `ai/skills/`: Domain notes for common tasks:
	- `http-logging.md`
	- `mongodb-config.md`
	- `live-network-diagnostics.md`
- `.claude/commands/README.md`: Command catalog conventions for Claude workflows.
- `.cursor/rules/README.md`: Cursor rules index and expected scope.

## Update Policy

- When adding/changing middleware behavior, update `ai/skills/http-logging.md` if logging context changes.
- When changing MongoDB logging schema/config, update `ai/skills/mongodb-config.md`.
- When changing live-network test gating or diagnostics, update `ai/skills/live-network-diagnostics.md`.
- Keep docs links aligned with the numbered docs structure (`00-architecture` to `04-development`).

## Verification Checklist

- Run `composer lint`.
- Run `composer quality`.
- Verify markdown links after doc changes.
