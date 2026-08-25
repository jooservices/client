# Workflows

CI runs Docker-based dependency installation from Packagist, linting, tests and
Clover coverage. The reusable coverage tool is `tools/coverage-enforce.php`; it
enforces the 85% floor from Clover reports.

The repository includes CI, post-merge, CodeQL, commit/PR semantics, link,
label, release, Scorecard, stale, and workflow-audit workflows. Release tags
must be reachable from `master` and pass the same Docker quality gate.
