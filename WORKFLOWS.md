# GitHub Actions workflow flow

This document describes the workflows currently defined in
`.github/workflows/`. All jobs run on GitHub-hosted `ubuntu-latest` runners.
PHP-related commands run through the repository Docker Compose setup
(`tools/ci/docker-compose`), which executes containers as the runner user
(`DOCKER_UID`/`GID`) so no root-owned files are produced. The pull-request
gate and the post-merge pass are split into two workflows; branch protection
on `master`/`develop` requires the pull-request checks before merge.

## Overall event flow

```mermaid
flowchart TD
    native[GitHub Secret Scanning and Push Protection] --> Alerts[GitHub security alerts or blocked push]

    pr[PR to master or develop] --> CI[CI — full quality gate]
    pr --> CodeQL[CodeQL]
    pr --> Commitlint[Commitlint]
    pr --> Semantic[Semantic PR Title]
    pr --> PathLabel[PR Labeler]
    pr --> Audit{Changed files under .github?}
    Audit -->|yes| WorkflowAudit[Workflow audit]

    push[Push to master or develop] --> PostMerge[CI post-merge]
    push --> CodeQL
    push --> Audit

    master[Push to master] --> Scorecard[OpenSSF Scorecard]

    tag[Push tag v*.*.*] --> Release[Release]

    weekly[Weekly schedules] --> CodeQL
    weekly --> LinkCheck[Link check]
    weekly --> Scorecard
    weekly --> WorkflowAudit

    daily[Daily schedule] --> Stale[Stale]

    manual[workflow_dispatch] --> LinkCheck
    manual --> Scorecard
    manual --> Stale
    manual --> WorkflowAudit
```

## Pull-request gate (`ci.yml`)

**Trigger:** pull requests targeting `master` or `develop`.
Concurrent runs for the same pull request cancel older in-progress runs.

```mermaid
flowchart TD
    PR[Pull request] --> V[Validate]
    V --> L[Lint matrix x5 — fail-fast]
    L --> S[Security matrix x3 — fail-fast]
    L --> T[Test matrix x2 — fail-fast]
    S --> C[Coverage upload]
    T --> C

    L --- L1[Pint · PHPCS · PHPStan · PHPMD · PHP-CS-Fixer]
    S --- S1[Dependencies: Composer audit + OSV Scanner + Dependency Review]
    S --- S2[Secrets: Gitleaks OSS CLI in pinned Docker image]
    S --- S3[SAST: Semgrep OSS]
    T --- T1[Unit suite + coverage artifact]
    T --- T2[Integration suite + coverage artifact]
    C --- C1[Enforce 85% floor on Unit, merge Clover reports]
    C --- C2[Upload to Codecov and SonarQube]
```

Every job checks out the source, builds the PHP image, restores or installs
Composer dependencies (cache keyed on `composer.lock`), then runs its tool.
The security matrix legs share the job definition and select their tool via
the matrix name; `fail-fast` cancels pending legs once one leg fails.

## Post-merge pass (`ci-post-merge.yml`)

**Trigger:** pushes to `master` or `develop` (i.e., right after a merge).

```text
Validate → Test matrix ×2 (fail-fast) → Coverage upload → Codecov + Sonar
```

A light sanity pass only: linting and security scanning already gated the
pull request, so the post-merge run verifies the freshly created merge
commit and refreshes the coverage baseline.

## Release flow (`release.yml`)

**Trigger:** push of a tag matching `v*.*.*`. Runs are not cancelled.

```mermaid
flowchart TD
    Tag[Push v*.*.* tag] --> Checkout[Checkout full history]
    Checkout --> Master{Tag commit is reachable from origin/master?}
    Master -->|no| Stop[Fail release]
    Master -->|yes| Setup[Build PHP image, install dependencies]
    Setup --> Quality[Composer validate, lint, PHPUnit coverage]
    Quality --> Trivy[Scan filesystem and PHP Docker image with Trivy]
    Trivy --> SARIF[Upload filesystem SARIF]
    SARIF --> SBOM[Generate SPDX JSON SBOM]
    SBOM --> GHRelease[Create GitHub Release with generated notes and SBOM]
```

The workflow fails if the tag is not on `origin/master`. It is therefore the
publication path for the `4.2.0` / current 4.x line; do not tag until the maintainer
approves the release.

## Other workflows

| Workflow | Trigger | Flow / result |
| --- | --- | --- |
| `codeql.yml` | Push/PR on `master` or `develop`; Monday 06:00 UTC | Checkout → initialize CodeQL for GitHub Actions only → analyze and publish security results. |
| `commitlint.yml` | PR opened, edited, synchronized, reopened | Checkout full history → validate every PR commit against `.github/commitlint.config.mjs`. |
| `semantic-pr.yml` | PR opened, edited, synchronized | Validate PR title type and require an uppercase first subject character. Skipped for Dependabot pull requests. |
| `pr-labeler.yml` | PR opened, synchronized, reopened | Checkout → apply labels from `.github/labeler.yml` based on changed paths. |
| `link-check.yml` | Monday 04:00 UTC; manual | Checkout → Lychee checks Markdown links, excluding `vendor`, Packagist, Codecov, and mail links. |
| `scorecard.yml` | Push to `master`; Monday 00:00 UTC; manual | OpenSSF Scorecard → upload SARIF. |
| `stale.yml` | Daily 01:00 UTC; manual | Mark issues/PRs stale after 60 inactive days; close 14 days later, except pinned/security/dependencies. |
| `workflow-audit.yml` | `.github/**` changes on push/PR; Monday 03:00 UTC; manual | Independent jobs: Actionlint checks workflow syntax and Zizmor scans workflow security, then uploads Zizmor SARIF when produced. |

## Scheduled maintenance timeline

All cron expressions use UTC, not the runner's local timezone.

```mermaid
gantt
    title Scheduled workflows (UTC)
    dateFormat  HH:mm
    axisFormat  %H:%M
    section Monday
    OpenSSF Scorecard      :milestone, 00:00, 0m
    Stale (also daily)     :milestone, 01:00, 0m
    Workflow audit         :milestone, 03:00, 0m
    Link check             :milestone, 04:00, 0m
    CodeQL                 :milestone, 06:00, 0m
```

## Branch protection

Both `master` and `develop` require pull requests with these status checks:
`Validate`, the five `Lint (…)` legs, the three `Security (…)` legs,
`Test (Unit)`, `Test (Integration)`, `Coverage upload`,
`Analyze GitHub Actions`, `Validate commit messages`, and
`Validate PR Title`. Strict mode requires the branch to be up to date.
Force pushes and deletions are denied. Admins cannot bypass protection on
either branch (`enforce_admins` is on).

## Notes

- All jobs use GitHub-hosted `ubuntu-latest`. There is no self-hosted runner pool.
- All declared workflows use dedicated repository configuration; none use
  `jooservices/workflows`.
- Secret scanning has two layers: GitHub Secret Scanning and Push Protection
  detect or block supported secrets at GitHub, while the pull-request gate
  scans the checked-out Git history with the MIT-licensed Gitleaks OSS CLI.
- The coverage job uploads the merged report to Codecov and SonarQube. It
  normalizes a scheme-less `SONAR_HOST_URL` to `https://…` before scanning.
- Containers run as the runner user through `tools/ci/docker-compose`
  (`DOCKER_UID`/`DOCKER_GID`), so workspace files are never root-owned.
