# Docs Verify Prompt

Use this prompt when JetBrains AI should review documentation edits.

## Prompt Template

```text
Review the documentation changes in jooservices/client.

Requirements:
- Keep docs aligned with docs/00-architecture through docs/04-development.
- Verify relative Markdown links resolve locally.
- Check that AI guidance references point to real command, rule, and prompt files.
- Flag stale references, missing skill-note updates, and numbered-index drift.

Return findings with concrete file paths and recommended fixes.
```