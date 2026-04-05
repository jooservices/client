# Release Readiness Prompt

Use this prompt when Antigravity should review release-sensitive changes.

## Prompt Template

```text
Check release readiness for jooservices/client.

Requirements:
- Run composer lint.
- Run composer quality.
- Verify docs links if release notes or docs changed.
- Confirm CHANGELOG.md and release workflows are aligned.
- Treat publishing credentials as optional and do not assume local secrets exist.

Summarize blockers first, then list any follow-up actions.
```