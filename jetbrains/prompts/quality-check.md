# Quality Check Prompt

Use this prompt when JetBrains AI should validate a change before handoff.

## Prompt Template

```text
Validate the current jooservices/client changes before completion.

Requirements:
- Preserve the public API and package identity.
- Run composer lint:all.
- Run composer test.
- Run composer check.
- If docs changed, verify local Markdown links and numbered docs structure.
- If coverage-sensitive behavior changed, run composer test:coverage.
- If workflow, composer, or release-surface files changed, run composer ci.

Report findings first, then list any residual risks.
```