# Developer Documentation

Internal documentation for developing and maintaining JOOClient. See [Enterprise Overview and Implementation Plan](../ENTERPRISE_OVERVIEW.md) for SSA/SD/PM/BM alignment, principles, and phased rollout.

## 📚 Contents

### 🛠️ [Setup](setup/)
Development environment setup (mock-first, no Docker required):
- **[Local Setup](setup/local-setup.md)** - Local development environment
- **[DB Setup Without Laravel](setup/db-setup-without-laravel.md)** - Capsule + .env path
- **[DB Setup With Laravel](setup/db-setup-with-laravel.md)** - Provider + publish + migrations
- **[Test Database](setup/test-database.md)** - Optional DB setup for integration-style checks

### 📖 [Guides](guides/)
How-to guides for extending the library:
- **[Custom Adapters](guides/custom-adapters.md)** - creating transport adapters, middleware, and loggers.

### 🏗️ [Architecture](architecture/)
System design and architectural patterns:
- **[Overview](architecture/overview.md)** - High-level architecture
- **[Design Patterns](architecture/design-patterns.md)** - Patterns used
- **[Data Flow](architecture/data-flow.md)** - Request/response flow
- **[Feature Flow](architecture/feature-flow.md)** - Feature flow diagrams

### 📁 [Codebase](codebase/)
Code structure and analysis:
- **[Structure](codebase/structure.md)** - Directory structure
- **[Classes](codebase/classes.md)** - All classes and traits
- **[SOLID Analysis](codebase/solid-analysis.md)** - SOLID principles analysis

### 🔄 [Process Flows](process-flows/)
Detailed process flow diagrams with explanations:
- **[Factory Creation](process-flows/factory-creation.md)** - How Factory::make() works
- **[Request Lifecycle](process-flows/request-lifecycle.md)** - Complete request flow
- **[Middleware Stack](process-flows/middleware-stack.md)** - Middleware composition
- **[Logging Flow](process-flows/logging-flow.md)** - Logging process
- **[Caching Flow](process-flows/caching-flow.md)** - Cache hit/miss flow
- **[Retry Flow](process-flows/retry-flow.md)** - Retry logic flow
- **[Circuit Breaker Flow](process-flows/circuit-breaker-flow.md)** - Circuit breaker states
- **[Request Chaining Flow](process-flows/request-chaining-flow.md)** - Request chaining execution

### 🧪 [Testing](testing/)
Testing documentation:
- **[Test Structure](testing/test-structure.md)** - Test organization
- **[Writing Tests](testing/writing-tests.md)** - How to write tests
- **[Coverage](testing/coverage.md)** - Test coverage information

### 🤝 [Contributing](contributing/)
Contribution guidelines:
- **[Coding Standards](contributing/coding-standards.md)** - Code style and standards
- **[Pull Requests](contributing/pull-requests.md)** - PR guidelines

---

## 🎯 Quick Navigation

### When You Need to Know...

| Question | Document | Location |
|----------|----------|----------|
| **"How does X work?"** | Process Flows | `process-flows/` |
| **"What's the architecture?"** | Architecture | `architecture/` |
| **"Where is class Y?"** | Codebase | `codebase/` |
| **"How do I set up dev environment?"** | Setup | `setup/` |
| **"How do I write tests?"** | Testing | `testing/` |

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
