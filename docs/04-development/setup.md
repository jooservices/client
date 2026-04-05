# Development Setup

## Local Setup

1. `composer install`
2. Optional Docker MongoDB: `docker compose up -d mongodb`
3. Run baseline checks: `composer lint:all`
4. Run tests: `composer test`

## Hooks

Composer installs CaptainHook hooks automatically through `post-install-cmd` and `post-update-cmd`.

If hooks are missing, install them manually with `composer hook:install`.

Install `gitleaks` locally if you want the default pre-commit and pre-push secret scans to pass.
