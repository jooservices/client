# AI Skills

## Purpose

Define how AI assistants should work in this repository and where AI-specific guidance lives.

## Scope

- Keep package purpose intact: HTTP client + logging + MongoDB config.
- Prefer minimal diffs and preserve public API compatibility.
- Validate canonical composer commands before finalizing changes.

## AI Guidance Locations

- `AGENTS.md`: Core behavior and package constraints for coding agents.
- `CLAUDE.md`: Required final validation contract (`composer lint:all`, `composer test`, `composer check`, `composer ci`, docs verification).
- `ai/skills/`: Domain notes for common tasks:
	- `http-logging.md`
	- `mongodb-config.md`
	- `live-network-diagnostics.md`
- `.claude/commands/`: Claude command playbooks:
	- `quality-check.md`
	- `docs-verify.md`
	- `release-readiness.md`
- `.cursor/rules/`: Cursor rule files:
	- `api-stability.mdc`
	- `docs-structure.mdc`
	- `validation-gates.mdc`
- `antigravity/prompts/`: Prompt templates mirroring the validation and docs workflows.
- `jetbrains/prompts/`: Prompt templates for JetBrains AI workflows.

## Update Policy

- When adding/changing middleware behavior, update `ai/skills/http-logging.md` if logging context changes.
- When changing MongoDB logging schema/config, update `ai/skills/mongodb-config.md`.
- When changing live-network test gating or diagnostics, update `ai/skills/live-network-diagnostics.md`.
- When changing validation expectations, update `.claude/commands/` and `.cursor/rules/` together.
- When changing AI workflow wording, keep Antigravity and JetBrains prompt templates in sync with the Claude command set.
- Keep docs links aligned with the numbered docs structure (`00-architecture` to `05-maintenance`).

## Verification Checklist

- Run `composer lint:all`.
- Run `composer test`.
- Run `composer check`.
- If docs changed, follow `.claude/commands/docs-verify.md` and verify markdown links after doc changes.
- If coverage-sensitive behavior changed, run `composer test:coverage`.
- If workflow, composer, or release surfaces changed, run `composer ci`.
