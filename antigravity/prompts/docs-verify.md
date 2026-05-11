# Docs Verify Prompt

Use this prompt when Antigravity should review documentation edits.

## Prompt Template

```text
Audit the documentation changes in jooservices/client.

Requirements:
- Keep docs aligned with docs/00-architecture through docs/05-maintenance.
- Verify relative Markdown links resolve locally.
- Check that AI guidance files point to real command, rule, and prompt files.
- Flag stale references, missing updates to ai/skills/*.md, and numbered-index drift.

Return concrete fixes or findings with file paths.
```