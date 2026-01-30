# Testing

Testing documentation for JOOClient.

## Contents

- **[Coverage](coverage.md)** - Test coverage information

## Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
vendor/bin/phpunit --filter FactoryTest

# With coverage
XDEBUG_MODE=coverage composer test:coverage
```

## Test Structure

```
tests/
├── Factory/          # Factory tests
├── Logging/          # Logging tests
├── Cache/            # Cache tests
├── Middlewares/      # Middleware tests
└── Integration/      # Integration tests
```

## See Also

- **[Setup](../setup/)** - Development setup
- **[Contributing](../contributing/)** - Contribution guidelines

---

**Copyright (c) 2025 Viet Vu <jooservices@gmail.com>**  
**Company: JOOservices Ltd**  
Licensed under the MIT License.
