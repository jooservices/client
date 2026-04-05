# Coding Standards

## Baseline

- PSR-12 coding style
- Strict typing enabled for package code
- Static analysis at PHPStan max level with strict rules and PHPUnit integration

## Tooling

- Pint
- PHPCS
- PHP-CS-Fixer
- PHPMD

## Responsibilities

- Pint is the primary formatter.
- PHP-CS-Fixer is limited to non-overlapping PHPDoc cleanup.
- PHPCS focuses on structural rules that should not compete with Pint.
- PHPStan and PHPMD enforce correctness and maintainability.

## Main Commands

- `composer lint`
- `composer lint:all`
- `composer lint:fix`
- `composer test`
- `composer test:coverage`
