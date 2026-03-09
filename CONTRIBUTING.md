# Contributing to JOOservices Client

Thank you for considering contributing to the JOOservices Client!

## Coding Standards

We adhere to strict coding standards to ensure high quality and maintainability.

- **PSR-12**: Code style is enforced via [Laravel Pint](https://laravel.com/docs/pint).
- **Static Analysis**: We use [PHPStan](https://phpstan.org/) at the highest level.
- **Testing**: All features must be covered by tests using [PHPUnit](https://phpunit.de/) (PHPUnit 12).

## Workflow

1.  **Fork** the repository.
2.  **Clone** your fork locally.
3.  **Install Dependencies**: `composer install`
4.  **Create a Branch**: `git checkout -b feature/my-new-feature`
5.  **Develop** your changes.
6.  **Run Quality Checks**: Ensure everything passes before pushing.
    ```bash
    composer quality
    ```
    This runs:
    - Pint (Linting)
    - PHPStan (Static Analysis)
    - PHPUnit (Tests)
    - PHPBench (Performance)

7.  **Commit**: Use descriptive commit messages.
8.  **Push** and **Create Pull Request**.

## Testing

We use a "Real Component" testing strategy:
- **Feature Tests** (`tests/Feature/`): Verify real side effects (file I/O) and integrations using `Guzzle\MockHandler` for the network layer only.
- **Benchmarks** (`tests/Benchmark/`): Ensure no performance regression.

Run tests:
```bash
composer test
```

## Security

If you discover a security vulnerability, please report it privately instead of using the issue tracker.
