# Linting Standards

## Command Map

- `composer lint`
- `composer lint:all`
- `composer lint:fix`
- `composer lint:pint`
- `composer lint:pint:fix`
- `composer lint:phpcs`
- `composer lint:phpstan`
- `composer lint:phpmd`
- `composer lint:cs`
- `composer lint:cs:fix`

## Gate Expectations

All lint commands must pass before merge.

## Tool Order

1. Pint
2. PHP-CS-Fixer
3. PHPCS
4. PHPStan
5. PHPMD

Pint owns broad formatting decisions. PHP-CS-Fixer is intentionally limited to PHPDoc cleanup. PHPCS checks structure. PHPStan and PHPMD cover correctness and maintainability.
