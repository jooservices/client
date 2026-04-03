# Documentation Standards

## Purpose

This document defines the standards and principles for maintaining documentation in the JOOservices HTTP Client repository.

## Audience

Documentation maintainers, contributors, AI assistants, and technical writers.

---

## Core Principles

### 1. Evidence-First Approach

**Confirmed** - All documentation MUST be based on actual repository evidence:
- Source code files
- Configuration files
- Test files
- Package dependencies
- Scripts and build files
- Existing documentation that can be verified

**Do NOT document:**
- Assumed features
- Planned features not yet implemented
- Framework patterns without evidence
- Ideal architectures that don't match reality

### 2. Distinguish Confidence Levels

Every significant claim should be categorized (see `confidence-levels.md`):
- **Confirmed**: Directly evidenced by repository files
- **Inferred**: Strong conclusion based on multiple evidence points 
- **Unknown**: Not evidenced / cannot be safely concluded
- **Risk**: Visible concern from code/config/docs/tests structure
- **Recommendation**: Suggested improvement, not asserted fact

### 3. Maintain Accuracy Over Time

When updating documentation:
- Verify claims against current source code
- Update changed functionality
- Mark deprecated features
- Remove obsolete information
- Document uncertainties honestly

---

## Documentation Structure

### Grouped Organization

Documentation is organized into numbered subdirectories by concern:

```
docs/
├── 00-architecture/    # Architecture, repository structure, data flow
├── 01-getting-started/ # Setup and quick start
├── 02-user-guide/      # API and usage guidance
├── 03-examples/        # Runnable examples
└── 04-development/     # Development standards, CI/CD, linting
```

### File Naming Conventions

- Use kebab-case: `system-architecture.md`
- Be descriptive: prefer `retry-middleware-implementation.md` over `retry.md`
- Use consistent prefixes for related docs when helpful

---

## Content Standards

### Required Sections

Most technical documentation should include:

1. **Purpose** - What this document covers
2. **Audience** - Who should read this
3. **Context/Summary** - High-level overview
4. **Main Content** - Detailed explanation
5. **Evidence** - Source code references, file paths
6. **Diagrams** - Visual aids when helpful (Mermaid only)
7. **Risks/Caveats** - Known issues or limitations
8. **Related Documents** - Cross-references

### Evidence References

When referencing code, include:
- File paths: `src/Client/ClientBuilder.php`
- Directory paths: `src/Middleware/`
- Class/interface names: `HttpClientInterface`
- Method names: `ClientBuilder::withRetry()`
- Config keys: `php` requirement in `composer.json`
- Package names: `guzzlehttp/guzzle`

Example:
```markdown
## Retry Middleware

**Evidence**: `src/Middleware/RetryMiddleware.php`

The retry middleware implements exponential backoff with jitter.
Configuration is handled via the `RetryConfig` value object
(`src/Resilience/RetryConfig.php`).
```

### Diagram Standards

Use Mermaid diagrams when they add clarity:
- Must reflect actual code structure
- Prefer simple over complex
- Include legend when needed
- Reference diagram in text

Approved diagram types:
- `flowchart TD` - Process flows
- `sequenceDiagram` - Interaction sequences
- `classDiagram` - Class relationships
- `erDiagram` - Entity relationships
- `stateDiagram-v2` - State transitions
- `graph TD` - Dependency graphs

---

## Writing Style

### Clarity and Precision

- Write for practitioners, not theorists
- Use active voice
- Be specific and concrete
- Avoid vague corporate language
- Prefer tables for inventories/mappings
- Use code examples from actual source when possible

### Example: Good vs Poor

❌ **Poor**: "The system ensures scalability and efficiency through a robust middleware architecture."

✅ **Good**: "The middleware pipeline (`src/Middleware/MiddlewarePipeline.php`) executes in FIFO order. Each middleware wraps the next, allowing retry, caching, and logging to be composed."

### Marking Uncertainty

When uncertain, be explicit:

```markdown
**Inferred**: The batch processing uses a concurrency limit of 25 based on
the default parameter in `HttpClient::batch()`.

**Unknown**: Whether batch failures are retried individually is not clear
from the implementation. Testing would clarify this behavior.
```

---

## Update Process

### When to Update

Update documentation when:
1. Changing functionality
2. Adding features
3. Deprecating code
4. Fixing bugs that affect behavior
5. Improving code organization
6. Discovering inaccuracies

### How to Update

1. Identify affected documentation files
2. Verify current state against source
3. Update content with evidence
4. Update release-readiness docs when needed
5. Check cross-references
6. Verify diagrams still match reality

### Deprecation Process

When deprecating features:
1. Mark as deprecated in relevant docs
2. Explain migration path if applicable
3. Note in changelog
4. Do not remove until feature is gone

---

## Quality Checklist

Before finalizing documentation:

- [ ] All claims supported by repository evidence
- [ ] File paths and references verified
- [ ] Diagrams match actual code structure
- [ ] Confidence levels marked where appropriate
- [ ] No framework assumptions without evidence
- [ ] Cross-references are valid
- [ ] Writing is clear and-pressure
- [ ] Examples compile/work
- [ ] Changelog updated
- [ ] Unknown items explicitly noted

---

## Related Documents

- [Confidence Levels](confidence-levels.md)
- [Templates and Writing Rules](templates-and-writing-rules.md)
- [Glossary](glossary.md)
- [PR #1 Review Verification](../PR1-REVIEW-VERIFICATION.md)
