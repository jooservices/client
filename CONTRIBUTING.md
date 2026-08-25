# Contributing

Thank you for considering a contribution to `jooservices/client`.

> [!IMPORTANT]
> This package is a **complete rebuild** (`v4.0.0`) with **no backward compatibility** with `v3.x` and earlier.
> Contributions must target the new PSR-18 architecture — see [README › About v4.0.0](README.md#about-v400), [UPGRADE-4.0.md](UPGRADE-4.0.md), and [CHANGELOG.md](CHANGELOG.md).

## Requirements

- PHP `>= 8.5`
- Docker with Docker Compose — **all** PHP tooling runs inside the repository's `php85` image; nothing needs to be installed locally
- Composer knowledge for day-to-day scripts (executed in the container)

## Setup

```bash
make build     # build the tooling image
make install   # composer install inside the container
make shell     # interactive container shell
```

Install optional local git hooks with `composer hooks:install` (run inside the container via `make shell`, or `docker compose run --rm php composer hooks:install`). The generated hooks run `composer lint` / `composer test` and need a `php` binary on the host to execute — if your host has no PHP, run `make lint` / `make test` yourself before every push instead.

**Never bypass hooks with `--no-verify`.**

## Git workflow

- `master` — production; receives only approved release and hotfix merges
- `develop` — integration branch for normal work
- create `feature/*` / `fix/*` branches from the latest `develop`, then open the PR back into `develop`
- releases: cut `release/<version>` from `develop`, stabilize, PR into `master`, tag from `master`, merge `master` back into `develop`
- hotfixes: `hotfix/*` from `master`, merged back into both `master` and `develop`
- never commit directly to `develop` or `master`; every change arrives via PR with green checks

## Commit convention

Conventional Commits are enforced in three places:

- locally by CaptainHook (`commit-msg`)
- on every commit in a PR by `commitlint.yml`
- on PR titles by `semantic-pr.yml` (subject must start with an uppercase letter)

```text
feat(middleware): add idempotency key support
fix(transport): retry on curl connection reset
docs(readme): document redirect policy
```

Rules: imperative mood, uppercase first letter of the subject, no trailing period.

## Quality gates

Run the relevant gates before every push — CI runs the same chain and **every job is required**:

| Command | Purpose |
| --- | --- |
| `make validate` | `composer validate --strict` |
| `make lint` | Pint (`per` preset), PHPCS (`PSR12`), PHPStan (max level + strict rules), PHPMD, PHP-CS-Fixer (`declare(strict_types=1)` enforcement) |
| `make test` | PHPUnit Unit + Integration suites |
| `make test-coverage` | Coverage run (CI enforces an 85% per-suite Clover floor via `tools/coverage-enforce.php`) |
| `make audit` | Composer audit |
| `make bench` | phpbench |
| `make ci` | lint + coverage run + coverage enforcement (local CI parity) |

Coding rules:

- linters run at **maximum strictness with no ignore lists** — fix the source, do not suppress findings; new PHPStan `ignoreErrors` entries are not accepted
- when formatting rules conflict, **Pint wins**
- `declare(strict_types=1);` everywhere; follow existing module boundaries (`src/Client`, `src/Middleware`, `src/Transport`, `src/Resilience`, …)
- keep changes consistent with SOLID, DRY, KISS, YAGNI; avoid unrelated refactors

## Testing expectations

- every behavior change ships with tests; bug fixes ship with a regression test
- unit and integration suites each independently stay above the 85% CI coverage floor
- public API changes update the README / relevant docs in the same PR

## Pull requests

A good PR explains:

- **what** changed
- **why** the change is needed
- **how** it was tested (include command output or test names)

Also:

- target `develop`
- keep the diff focused — no debug code, warnings, notices, or drive-by file churn
- make sure every required CI check is green (validate → lint/test matrices and parallel security jobs → coverage upload)

## Reporting issues

- bugs and feature requests: [GitHub Issues](https://github.com/jooservices/client/issues)
- **security vulnerabilities: never in public issues** — follow [SECURITY.md](SECURITY.md)

## License

By contributing you agree that your contributions are licensed under the [MIT License](LICENSE).
