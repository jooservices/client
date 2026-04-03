# Confidence Levels

## Purpose

This document defines the confidence levels used throughout documentation to distinguish between facts, interpretations, and unknowns.

## Audience

All documentation readers and contributors.

---

## Overview

Not all documentation can be stated with equal certainty. To maintain intellectual honesty and help readers assess reliability, we label significant claims with confidence levels.

---

## Confidence Level Definitions

### **Confirmed**

**Definition**: Directly evidenced by repository files with no interpretation needed.

**When to use**:
- Direct code references
- Explicit configuration values
- Package dependencies
- File/directory structure
- Test behaviors
- Documented interfaces

**Examples**:

✅ "The library requires PHP ^8.5 according to `composer.json`."

✅ "The `HttpClient` class implements `HttpClientInterface` (see `src/Client/HttpClient.php` line 17)."

✅ "Tests are written using PHPUnit (`composer.json` dev dependencies)."

---

### **Inferred**

**Definition**: Strong conclusion based on multiple evidence points, patterns, or standard practices, but not explicitly stated.

**When to use**:
- Architectural patterns observed in code structure
- Design intentions clear from implementation
- Common conventions followed but not documented
- Purpose derived from naming and usage

**Examples**:

✅ **Inferred**: "The middleware pipeline uses FIFO ordering based on the `array_reverse()` call in `MiddlewarePipeline::buildHandlerStack()` and the comment explaining Guzzle's LIFO stack behavior."

✅ **Inferred**: "The `ClientConfig` value object enforces immutability through readonly properties, following the Value Object pattern."

✅ **Inferred**: "The library is designed for reusable HTTP client needs across multiple projects, given its generic naming and lack of domain-specific code."

**Caution**: Clearly explain the reasoning and evidence that supports the inference.

---

### **Unknown**

**Definition**: Cannot be determined from repository evidence.

**When to use**:
- Missing documentation
- Unclear implementation intent
- Behavior not visible in code or tests
- Configuration without clear default
- External integrations not evident

**Examples**:

✅ **Unknown**: "Whether the MongoDB logging is intended for production use or testing only is not documented."

✅ **Unknown**: "The performance characteristics under high concurrency are not documented and no load tests are present."

✅ **Unknown**: "The intended use case for `MongoDbLogger` in relation to Laravel's standard logging is unclear."

**Important**: Marking something as Unknown is valuable—it highlights gaps and invites clarification.

---

### **Risk**

**Definition**: Potential problem, vulnerability, or technical debt visible in the code, configuration, tests, or documentation.

**When to use**:
- Missing error handling
- Unvalidated inputs
- Coupling concerns
- Performance concerns
- Security concerns
- Lack of tests for critical paths
- Deprecated dependencies
- Incomplete implementations

**Examples**:

⚠️ **Risk**: "The `persistWithIlluminate()` method in `MongoDbLogger` silently catches all exceptions, which could hide configuration problems."

⚠️ **Risk**: "The `composer.json` declares `mongodb/laravel-mongodb` as a dependency but no Laravel facade or service provider exists, creating unnecessary dependency bloat."

⚠️ **Risk**: "No integration tests exist for MongoDB logging functionality despite the code being present."

**Severity Levels** (optional sub-classification):
- **High Risk**: Could cause data loss, security issues, or system failure
- **Medium Risk**: Could cause incorrect behavior or maintenance problems
- **Low Risk**: Could cause minor inconvenience or confusion

---

### **Recommendation**

**Definition**: Suggested improvement based on best practices, not a statement of current reality.

**When to use**:
- Proposed refactoring
- Suggested additional features
- Documentation improvements
- Testing gaps
- Security hardening suggestions

**Examples**:

💡 **Recommendation**: "Consider adding integration tests for the MongoDB logging to verify Laravel Eloquent integration."

💡 **Recommendation**: "The PHP version should be consistent across `composer.json` (^8.5) and README badge (>=8.2)."

💡 **Recommendation**: "Extract the `persistWithIlluminate()` MongoDB logic into a separate repository class to improve testability."

---

## Usage Guidelines

### In Technical Documentation

When documenting system behavior, architecture, or implementation details:

```markdown
## Middleware Execution Order

**Confirmed**: Middlewares are stored in an array and processed in reverse
order before being pushed to Guzzle's HandlerStack
(`MiddlewarePipeline::buildHandlerStack()`, lines 92-108).

**Inferred**: This reverse iteration ensures FIFO semantics where the first
middleware added becomes the outermost layer, based on how Guzzle's `push()`
adds to the top of the stack.

**Unknown**: Whether middleware order can be explicitly controlled beyond
insertion order is not documented or evident in the API.
```

### In Risk Assessment

When identifying problems:

```markdown
## Security Review Findings

**Risk (Medium)**: The `FilesystemCache` creates cache directories with
default permissions. If the web server runs with permissive umask, cache
files could be world-readable. Evidence: `mkdir($dir, 0755, true)` in
`FilesystemCache.php` line 25.

**Recommendation**: Consider explicitly setting restrictive permissions
(0700) for sensitive cached data.
```

### In Architecture Documentation

When explaining design:

```markdown
## System Architecture

**Confirmed**: The system uses a layered architecture with clear separation:
- Client layer (`src/Client/`)
- Contract layer (`src/Contracts/`)
- Adapter layer (`src/Adapters/`)
- Middleware layer (`src/Middleware/`)

**Inferred**: This follows hexagonal architecture principles where the
adapter (`GuzzleHttpClientAdapter`) isolates Guzzle dependencies from core
business logic, allowing transport substitution.
```

---

## When NOT to Use Labels

Don't over-label obvious facts or every sentence. Use confidence levels for:
- Significant architectural claims
- Security or risk assertions
- Behavioral explanations
- Design intent interpretation
- Gaps or unknowns

Simple factual statements don't need labels:

```markdown
## Installation

Install via Composer:

```bash
composer require jooservices/client
```

---

## Label Format

Use bold with colon for inline labeling:

```markdown
**Confirmed**: The library requires PHP 8.5+.
**Inferred**: The value object pattern is used for configuration.
**Unknown**: The production usage of MongoDB logging.
**Risk**: Missing input validation on cache keys.
**Recommendation**: Add integration tests.
```

For longer sections, use headings:

```markdown
### Middleware Architecture

#### Confirmed Implementation

The middleware pipeline implementation...

#### Inferred Design Intent

Based on the code structure...

#### Unknown Aspects

The following cannot be determined...
```

---

## Related Documents

- [Documentation Standards](documentation-standards.md)
- [Known Gaps, Risks, and Tech Debt (retained audit artifact)](../PR1-REVIEW-VERIFICATION.md)
- [Source Evidence Gaps (retained audit artifact)](../PR1-REVIEW-VERIFICATION.md)
