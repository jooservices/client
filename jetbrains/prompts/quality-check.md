# Quality Check Prompt

Use this prompt when JetBrains AI should validate a change before handoff.

## Prompt Template

```text
Validate the current jooservices/client changes before completion.

Requirements:
- Preserve the public API and package identity.
- Run composer lint.
- Run composer quality.
- If docs changed, verify local Markdown links and numbered docs structure.
- If coverage-sensitive behavior changed, run composer test:coverage.

Report findings first, then list any residual risks.
```