# Development Setup

Setup guides for developing and contributing to JOOClient (mock-first; no Docker required).

## Contents

- **[Local Setup](local-setup.md)** - Local development environment
- **[DB Setup Without Laravel](db-setup-without-laravel.md)** - Capsule + .env, table SQL, no framework
- **[DB Setup With Laravel](db-setup-with-laravel.md)** - Provider, publish config/migrations, artisan flow
- **[Test Database](test-database.md)** - Optional DB configuration (not required for default mock-based tests)

## Quick Start

1. Clone the repository
2. Install dependencies: `composer install`
3. (Optional) Set up DB/Redis only if running integration checks (see [Test Database](test-database.md))
4. Run tests with mocks: `composer test`

## See Also

- **[Architecture](../architecture/)** - System design
- **[Codebase](../codebase/)** - Code structure
- **[Contributing](../contributing/)** - Contribution guidelines

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
