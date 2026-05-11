# Quality Check Prompt

Use this prompt when you want Antigravity to validate a change before handoff.

## Prompt Template

```text
Review the current branch in jooservices/client and run the repository validation flow.

Requirements:
- Preserve the public API and package identity.
- Run composer lint:all.
- Run composer test.
- Run composer check.
- If docs changed, verify local Markdown links and the numbered docs structure.
- If coverage-sensitive code changed, run composer test:coverage.
- If workflow, composer, or release-surface files changed, run composer ci.

Report findings first, then list any remaining risks or checks that could not be completed.
```