# Security Policy

## Supported versions

| Version line | Status |
| --- | --- |
| `4.1.x` (this repository) | Current supported |
| `4.0.x` | Supported |
| `v3.x` and earlier | **End of life.** The archived previous implementation is a separate codebase lineage and receives no fixes |

The rebuild has **no backward compatibility** with older lines; security reports against archived versions cannot be actioned here.

## Reporting a vulnerability

**Do not open public GitHub issues for suspected vulnerabilities.**

Preferred: GitHub [private vulnerability reporting](https://github.com/jooservices/client/security/advisories/new) (Security Advisories).

Alternatively, email [admin@jooservices.com](mailto:admin@jooservices.com) with:

- a clear summary of the issue
- affected package version(s) / commit
- impact and expected risk
- reproduction details or proof of concept when available

Do not publish credentials, request bodies, or reproduction data containing secrets. If you are unsure whether something is security-related, contact us privately first rather than opening a public issue.

## What happens next

1. We acknowledge the report as soon as possible.
2. We investigate and validate, keeping you informed of progress.
3. Fixes land in the supported line with a coordinated disclosure; you are credited unless you prefer otherwise.

No guaranteed SLA is offered; handling time depends on severity, exploitability, and release risk.

## Scope

This policy covers repository-managed behavior, including:

- request signing, header validation, and CR/LF injection rejection
- TLS verification defaults and redirect policy (cross-origin credential stripping, public-to-private redirect restrictions)
- middleware pipeline behavior (authentication, retry, circuit breaker, rate limit, bulkhead, cache, logging)
- log and error sanitization of sensitive header/credential values
- dependency and CI/security-workflow configuration that affects package consumers or repository integrity

Automated scanning runs on every change and on schedule:

- Composer audit and OSV Scanner (dependency vulnerabilities)
- Gitleaks (secrets)
- Semgrep OSS (PHP SAST)
- CodeQL (GitHub Actions workflow analysis)
- Dependency Review (on pull requests)
- Trivy filesystem and container image scans (on release tags)
- OpenSSF Scorecard and zizmor (workflow supply-chain audit)
- SBOM generation on release tags

## Non-security issues

Bugs, feature requests, questions, and documentation improvements belong in [GitHub Issues](https://github.com/jooservices/client/issues).
