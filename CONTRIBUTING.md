# Contributing

Run all checks in Docker:

```bash
make ci
make bench
```

Use PHP 8.5+, keep PSR-18 behavior intact, add regression coverage for each behavior change, and do not add local Composer repositories.

Install optional local hooks from a Git checkout with `composer hooks:install`.
