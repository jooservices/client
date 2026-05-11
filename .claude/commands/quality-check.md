# Quality Check

Run the repository validation flow before handing off changes.

## Use When

- Source code, tests, or quality configuration changed.
- You need the minimum final validation expected by [AGENTS.md](../../AGENTS.md) and [CLAUDE.md](../../CLAUDE.md).

## Repository Constraints

- Preserve the public API exposed from `src/`.
- Keep the package identity intact: layered HTTP client, middleware pipeline, structured logging, and MongoDB logging support.
- Do not remove runtime assets such as `config/`, `scripts/`, `phpbench.json`, or Docker files.

## Standard Flow

1. Run `composer lint:all`.
2. Run `composer test`.
3. Run `composer check`.
4. If documentation changed, run the procedure in [docs-verify.md](docs-verify.md).
5. If coverage-sensitive files changed, run `composer test:coverage`.
6. If workflow, composer, or release-surface files changed, run `composer ci`.

## Expected Outcome

- Linting, static analysis, and the standard test suite pass.
- Any failures are fixed at the root cause rather than suppressed.
- The final response calls out anything that could not be validated.