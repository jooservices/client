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
4.  **Sync Develop**: `git checkout develop && git pull --ff-only origin develop`
5.  **Create a Branch**: `git checkout -b feature/my-new-feature`
6.  **Develop** your changes.
7.  **Run Quality Checks**: Ensure everything passes before pushing.
    ```bash
    composer lint:fix
    composer lint:all
    composer test
    composer check
    ```
    Use `composer test:coverage` when you need the enforced 98% coverage gate.
    Run `composer ci` when workflow files, Composer metadata, or release-surface files change.

8.  **Commit**: Use Conventional Commit messages such as `feat(http): Add retry header propagation`.
9.  **Push** and **Create Pull Request** into `develop`.

Release preparation is separate from normal feature work: create `release/<version>` from `develop`, stabilize there, then open the release PR into `master`.

## Hooks

- `composer install` auto-installs CaptainHook hooks.
- Pre-commit runs PHP linting, `gitleaks protect --staged`, `composer lint:pint`, `composer lint:phpcs`, `composer lint:phpstan`, `composer lint:phpmd`, and `composer lint:cs`.
- Pre-push runs `composer test` and an unpushed-commits gitleaks scan against the current upstream branch when `gitleaks` is available locally.

## Testing

We use a "Real Component" testing strategy:
- **Feature Tests** (`tests/Feature/`): Verify real side effects (file I/O) and integrations using `Guzzle\MockHandler` for the network layer only.
- **Benchmarks** (`tests/Benchmark/`): Ensure no performance regression.

Run tests:
```bash
composer test
composer test:coverage
```

## Security

If you discover a security vulnerability, please report it privately instead of using the issue tracker.
