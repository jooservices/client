# Release Readiness Prompt

Use this prompt when JetBrains AI should review release-sensitive changes.

## Prompt Template

```text
Check whether jooservices/client is ready for release-related changes.

Requirements:
- Run composer lint:all.
- Run composer test.
- Run composer test:coverage.
- Run composer ci.
- Verify docs links if release notes or docs changed.
- Confirm CHANGELOG.md and release workflows are aligned.
- Treat publishing credentials as optional and never assume secrets are available locally.

List blockers first, then any remaining follow-up actions.
```