# Implementation Plan — Client Audit Hardening

## Scope and dispatch

This plan covers `jooservices/client`, an independent PHP PSR-18 HTTP client
library. The scope is limited to `projects/client`; downstream SDK and MCP
repositories are excluded. The library has a default `CurlTransport` (requires
`ext-curl`), optional `GuzzleTransport`, injectable PSR-18 transport, and
`FailoverTransport`, alongside request building, middleware/resilience,
redirect security, caching, response body limits, sanitized logging, and test
support.

The root dispatches tickets in order, one at a time, and accepts each handoff
before dispatching the next. This avoids collisions in shared files. Run ticket
commands from `projects/client/`. For each ticket that changes PHP source or
tests, also run the configured lint gate:

```bash
tools/ci/docker-compose run --rm php composer lint
```

Ticket 9 is independent final QA after implementation and review. Reporting
follows QA. A green focused check is not a substitute for the final gates.

## Acceptance criteria

- **AC-1:** Guzzle cannot silently follow a cross-host redirect when it cannot
  enforce the DNS addresses validated by redirect policy; preserve safe
  supported behavior and document the limitation.
- **AC-2:** Redirect target policy rejects the audit-verified non-global and
  special-use ranges while preserving valid global exceptions and intended
  private-target opt-in behavior.
- **AC-3:** Direct `PsrTransport` calls, retries, and failover preserve the
  original seekable body position and payload where replay is supported; they
  never silently retry or fail over with an empty body.
- **AC-4:** `withDeadline()` bounds cumulative attempts and backoff. No attempt
  starts after expiry, and each attempt timeout is at most the remaining
  budget.
- **AC-5:** Requests carrying `Range` or `If-Range` bypass cache reads and
  writes; a `206` response is never served or stored as a full `GET`
  representation.
- **AC-6:** The response body limit is enforced before or during
  materialization.
  Oversized bodies cannot cause unbounded reads or memory failure, including
  cache snapshots.
- **AC-7:** Log sanitization redacts sensitive query keys after percent
  decoding, handles semicolon-delimited query parameters and fragments, and
  retains safe values.
- **AC-8:** `make bench` succeeds in the standard Docker setup and generated
  artifacts are owned by the host user.
- **AC-9:** E2E paths cover default `ClientBuilder`/`CurlTransport`,
  `GuzzleTransport` with cURL and Stream handlers, injected PSR-18 through
  `PsrTransport`, and a real successful `FailoverTransport` fallback using a
  deterministic failing first transport. All paths use one local PHP target
  server; no external network is used, and all E2E suites run.
- **AC-10:** Configured quality and security gates are green. Assess next-minor
  readiness; do not select a version, tag, or publish without root
  confirmation.

## Tickets

### Ticket 1 — Harden redirect policy

- **Traceability:** AC-1, AC-2
- **Assigned to:** `joo-dev_1` (Codex runtime default)
- **Status:** done
- **Allowed files:**
  - `src/Transport/GuzzleTransport.php`
  - `src/Support/RedirectTargetPolicy.php`
  - `tests/Unit/Transport/GuzzleTransportTest.php`
  - `tests/Unit/Support/RedirectHandlerTest.php`
  - `tests/Fixtures/redirect-target-dns-stub.php`
  - `README.md`
- **Dependencies:** None.
- **Acceptance and edge cases:**
  - Fail closed for a cross-host Guzzle redirect when Guzzle cannot apply the
    DNS addresses validated by redirect policy; do not silently follow it.
  - Preserve safe redirect behavior that the transport can enforce.
  - Update the README `Redirects` note to state that cross-host Guzzle
    redirects fail closed when verified DNS pinning cannot be enforced.
  - Cover every non-global and special-use range verified by the audit,
    including the identified IANA special-purpose address gaps. Preserve
    valid global exceptions and the intended private-target opt-in behavior.
  - Keep DNS/IP denial tests deterministic and local. They must abort before
    any outbound request; use the approved DNS stub fixture as needed.
- **Focused verification:**

  ```bash
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=Unit --filter 'GuzzleTransportTest|RedirectHandlerTest'
  ```

- **Do NOT:** Modify files outside the allowed list, weaken redirect policy,
  add external-network tests, or change downstream SDK/MCP repositories.
- **Ask for help when:** The audit-verified address list or expected global
  exceptions are unavailable; Guzzle behavior cannot be made fail-closed
  without changing the approved scope; or a required focused check fails.
- **Progress / handover:** Done and accepted by root. Changed the six assigned
  files: `src/Transport/GuzzleTransport.php`,
  `src/Support/RedirectTargetPolicy.php`,
  `tests/Unit/Transport/GuzzleTransportTest.php`,
  `tests/Unit/Support/RedirectHandlerTest.php`,
  `tests/Fixtures/redirect-target-dns-stub.php`, and `README.md`. Focused
  PHPUnit passed: 50 tests, 105 assertions. `composer lint` and
  `git diff --check` passed. PHPUnit emitted a non-fatal cache permission
  warning; it did not affect the passing test result.

### Ticket 2 — Replay request bodies on retry and failover

- **Traceability:** AC-3
- **Assigned to:** `joo-dev_2` (Codex runtime default)
- **Status:** done
- **Allowed files:**
  - `src/Middleware/RetryMiddleware.php`
  - `src/Transport/FailoverTransport.php`
  - `src/Transport/PsrTransport.php`
  - `tests/Unit/Middleware/RetryMiddlewareTest.php`
  - `tests/Unit/Transport/FailoverTransportTest.php`
  - `tests/Unit/Transport/PsrTransportTest.php`
  - `tests/Integration/GuzzleTransportTest.php`
- **Dependencies:** None. Root dispatches after Ticket 1 to keep execution
  sequential.
- **Acceptance and edge cases:**
  - Direct `PsrTransport` calls preserve a seekable body's original position
    and payload where replay is supported.
  - Exercise a PSR-18 client that consumes the request body and then errors;
    prove the next supported retry or fallback receives the complete original
    payload from the original seekable position.
  - Cover seekable request bodies for the supported transports and confirm
    middleware retry and failover preserve the original position and payload;
    there is no silent retry or fallback with an empty body.
  - Preserve legitimate non-retry behavior. If a body cannot be replayed,
    fail safely using the existing error contract rather than sending an empty
    request.
- **Focused verification:**

  ```bash
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=Unit --filter 'RetryMiddlewareTest|FailoverTransportTest|PsrTransportTest'
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=Integration --filter GuzzleTransportTest
  ```

- **Do NOT:** Change retry eligibility, add unsupported transport behavior,
  modify files outside the allowed list, or change downstream repositories.
- **Ask for help when:** A supported transport or existing error contract is
  unclear; a non-seekable body has no safe existing failure behavior; or a
  required focused check fails.
- **Progress / handover:** Done and accepted by root. Changed the assigned
  paths: `src/Middleware/RetryMiddleware.php`,
  `src/Transport/FailoverTransport.php`, `src/Transport/PsrTransport.php`,
  `tests/Unit/Middleware/RetryMiddlewareTest.php`,
  `tests/Unit/Transport/FailoverTransportTest.php`,
  `tests/Unit/Transport/PsrTransportTest.php`, and
  `tests/Integration/GuzzleTransportTest.php`. Focused Unit verification
  passed: 14 tests, 23 assertions. Integration verification passed: 2 tests,
  9 assertions. `composer lint` and `git diff --check` passed. PHPUnit emitted
  a non-fatal cache permission warning; it did not affect the passing results.

### Ticket 3 — Bound cumulative retry deadlines

- **Traceability:** AC-4
- **Assigned to:** `joo-dev_3` (Codex runtime default)
- **Status:** done
- **Review:** F-2 verified
- **Allowed files:**
  - `src/Middleware/DeadlineMiddleware.php`
  - `src/Middleware/RetryMiddleware.php`
  - `src/Middleware/MiddlewarePipeline.php`
  - `tests/Unit/Middleware/DeadlineMiddlewareTest.php`
- **Dependencies:** Ticket 2 accepted; `RetryMiddleware.php` is shared with
  that ticket.
- **Acceptance and edge cases:**
  - Count attempt time and backoff against the single `withDeadline()` budget.
  - Do not begin another attempt after the deadline expires.
  - Clamp each attempt timeout to no more than the time remaining when that
    attempt begins.
  - Cover expiry during an attempt and expiry during backoff, proving no later
    attempt starts after expiry.
- **Focused verification:**

  ```bash
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=Unit --filter 'DeadlineMiddlewareTest|RetryMiddlewareTest'
  ```

- **Do NOT:** Change unrelated retry policy, add a new deadline API, modify
  files outside the allowed list, or change downstream repositories.
- **Ask for help when:** The existing deadline clock or timeout contract is
  unclear; cumulative-budget behavior conflicts with existing public API
  behavior; or a required focused check fails.
- **Progress / handover:** Review found that a downstream middleware could
  consume the remaining budget before delegating while the terminal transport
  still started. The fix now checks expiry at the pipeline terminal boundary,
  clamps timeout there, and adds regressions for an exhausted budget and a
  shortened remaining timeout. Changed the four allowed paths. Focused deadline
  and retry verification passed: 11 tests, 29 assertions; `composer lint` and
  `git diff --check` passed. PHPUnit emitted a non-fatal cache permission
  warning. Reviewer verified F-2 at the terminal boundary.

### Ticket 4 — Bypass cache for range traffic

- **Traceability:** AC-5
- **Assigned to:** `joo-dev_4` (Codex runtime default)
- **Status:** done
- **Review:** F-1 verified
- **Allowed files:**
  - `src/Middleware/CacheMiddleware.php`
  - `tests/Unit/Middleware/CacheMiddlewareTest.php`
- **Dependencies:** None. Root dispatches sequentially; Ticket 5 also edits
  these files and depends on this ticket.
- **Acceptance and edge cases:**
  - Requests with `Range` or `If-Range` bypass both cache reads and writes.
  - An existing cached `206` entry is not returned as a full `GET`
    representation.
  - A range request followed by a plain `GET` cannot make the plain request
    receive a partial response; the plain `GET` retains its normal cache
    behavior.
  - Never store a `206` response as a full `GET` representation.
- **Focused verification:**

  ```bash
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=Unit --filter CacheMiddlewareTest
  ```

- **Do NOT:** Change general cache policy beyond range semantics, modify files
  outside the allowed list, or change downstream repositories.
- **Ask for help when:** Existing cache-key or cache-entry behavior makes the
  range/full-response distinction unclear; or a required focused check fails.
- **Progress / handover:** Initial change accepted, then reviewer found F-1:
  accepting only status 200 narrowed the prior cache policy for other 2xx
  responses. Fixed by retaining cacheability for prior 2xx statuses except
  206 and adding a Faker-backed 204 reuse regression. Changed
  `src/Middleware/CacheMiddleware.php` and
  `tests/Unit/Middleware/CacheMiddlewareTest.php`. Focused Unit verification
  passed after fix: 9 tests, 44 assertions. `composer lint` and
  `git diff --check` passed. PHPUnit emitted a non-fatal cache permission
  warning; it did not affect the passing result. Reviewer verified the 204
  regression and restored 2xx policy.

### Ticket 5 — Bound response and cache materialization

- **Traceability:** AC-6
- **Assigned to:** `joo-dev_5` (Codex runtime default)
- **Status:** done
- **Review:** F-3 verified
- **Allowed files:**
  - `src/Response/Response.php`
  - `src/Support/StreamContents.php`
  - `src/Middleware/CacheMiddleware.php`
  - `src/Validation/JsonSchemaBodyValidator.php`
  - `tests/Unit/Response/ResponseTest.php`
  - `tests/Unit/Support/StreamContentsTest.php`
  - `tests/Unit/Middleware/CacheMiddlewareTest.php`
  - `tests/Unit/Signing/HmacSha256SignerTest.php`
  - `tests/Unit/Validation/JsonSchemaBodyValidatorTest.php`
- **Dependencies:** Ticket 4 accepted; `CacheMiddleware.php` and
  `CacheMiddlewareTest.php` are shared with that ticket.
- **Acceptance and edge cases:**
  - Enforce the response body ceiling before or while materializing content.
  - Cover known and unknown body sizes, and abort chunked reads when the
    ceiling is exceeded.
  - Preserve the expected cursor behavior for seekable streams.
  - Ensure cache snapshots are also bounded; an oversized body must not lead
    to unbounded reads or memory failure through caching.
  - Keep the 100 MiB response ceiling scoped to response materialization; do
    not reject large outgoing bodies while signing requests.
  - Keep JSON Schema response validation on the bounded response-body path.
- **Focused verification:**

  ```bash
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=Unit --filter 'ResponseTest|StreamContentsTest|CacheMiddlewareTest|HmacSha256SignerTest|JsonSchemaBodyValidatorTest'
  ```

- **Do NOT:** Raise or remove the existing response-body ceiling, redesign
  caching, add unrelated response features, modify files outside the allowed
  list, or change downstream repositories.
- **Ask for help when:** The configured ceiling or seekable-stream contract is
  unclear; bounded caching conflicts with Ticket 4 semantics; or a required
  focused check fails.
- **Progress / handover:** Ticket 5 is complete. `StreamContents::read()` keeps
  its generic unbounded behavior for request signing; `Response`,
  `CacheMiddleware`, and `JsonSchemaBodyValidator` use the bounded
  `readResponseBody()` path. Known sizes and valid `Content-Length` values are
  rejected before reading, while unknown or under-reported bodies are read in
  bounded chunks and rejected at cap + 1. Seekable cursors are restored on
  success and failure. Non-seekable cache candidates bypass caching without
  being consumed; oversized seekable responses remain unchanged and uncached.
  Ticket 4's cacheable-2xx-except-206 policy, range bypass, and tests are
  preserved.

  Focused verification passed: 28 tests, 89 assertions, 1 skipped because the
  optional `justinrainbow/json-schema` dependency is absent. The HMAC regression
  signs a request stream reporting 104,857,601 bytes and verifies its signature
  and cursor restoration. Response-side tests cover prechecks, invalid length
  fallback, chunk-abort, cursor restoration, and cache bypass. `composer lint`
  passed. PHPUnit emitted a non-fatal cache permission warning; it did not
  affect the passing results. `git diff --check` passed.

### Ticket 6 — Redact sensitive log query values

- **Traceability:** AC-7
- **Assigned to:** `joo-dev_6` (Codex runtime default)
- **Status:** done
- **Allowed files:**
  - `src/Logging/LogSanitizer.php`
  - `tests/Unit/Logging/LogSanitizerTest.php`
- **Dependencies:** None. Root dispatches sequentially.
- **Acceptance and edge cases:**
  - Detect sensitive query parameter names after percent decoding.
  - Parse semicolon-delimited query parameters so sensitive values are
    redacted there as well as with the existing supported delimiter.
  - Sanitize sensitive query data in fragments according to the existing
    sanitizer contract.
  - Retain safe query and fragment values in sanitized output.
- **Focused verification:**

  ```bash
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=Unit --filter LogSanitizerTest
  ```

- **Do NOT:** Expand the sensitive-key policy beyond the established
  sanitizer contract, remove safe values, modify files outside the allowed
  list, or change downstream repositories.
- **Ask for help when:** Existing sensitive-key rules or fragment handling are
  unclear; or a required focused check fails.
- **Progress / handover:** Done and accepted by root. Changed
  `src/Logging/LogSanitizer.php` and
  `tests/Unit/Logging/LogSanitizerTest.php`. Focused Unit verification passed:
  4 tests, 4 assertions. Coverage includes encoded sensitive keys,
  semicolon-delimited parameters, fragments, and query values containing `?`.
  `composer lint` and `git diff --check` passed. PHPUnit emitted a non-fatal
  cache permission warning; it did not affect the passing result.

### Ticket 7 — Run benchmarks as the host user

- **Traceability:** AC-8
- **Assigned to:** `joo-dev_7` (Codex runtime default)
- **Status:** done
- **Allowed files:** `Makefile`
- **Dependencies:** None. Root dispatches sequentially.
- **Acceptance and edge cases:**
  - `make bench` succeeds with the standard Docker setup.
  - Route the Makefile Docker invocation through the existing
    `tools/ci/docker-compose` wrapper so the container uses the host UID/GID.
  - If the benchmark run creates artifacts, verify their numeric owner/group
    match `id -u` and `id -g` on the host. The verified stdout-only result with
    no generated files is accepted by the user.
- **Focused verification:** Run from `projects/client/`:

  ```bash
  bench_started=$(date +%s)
  make bench
  id -u
  id -g
  find . -type f -newermt "@${bench_started}" -not -path './vendor/*' -not -path './.git/*' -printf '%U:%G %p\n'
  ```

  Run the block in one shell. If it lists artifacts, record each path and
  confirm its numeric UID:GID matches the host values above. If it lists no
  files, record that benchmark output is stdout-only, as accepted by the user.
- **Do NOT:** Change benchmark cases or configuration, edit files other than
  `Makefile`, or change Docker images/toolchains.
- **Ask for help when:** `make bench` fails, a generated artifact is not owned
  by the host user, the output differs unexpectedly from the accepted
  stdout-only behavior, or a required check fails.
- **Progress / handover:** Done and accepted by root. `Makefile` now defaults
  `DOCKER_COMPOSE` to `tools/ci/docker-compose`. `make bench` exited 0 and
  emitted PHPBench 1.7.0 / PHP 8.5.11 output with a 23.922 μs result. Host
  UID:GID was 1000:1000. The `find` check since benchmark start produced no
  files; the user explicitly accepted stdout-only, so there were no generated
  artifacts requiring ownership verification.

### Ticket 8 — Exercise the local E2E transport matrix

- **Traceability:** AC-9
- **Assigned to:** `joo-dev_8` (Codex runtime default)
- **Status:** done
- **Allowed files:** `tests/E2E/HttpClientE2ETest.php`
- **Dependencies:** Tickets 1–7 accepted. The E2E matrix depends on the
  supported transport behavior established by those tickets.
- **Acceptance and edge cases:**
  - Use one local PHP HTTP target server shared by the transport matrix.
  - Exercise the default `ClientBuilder` path through `CurlTransport`.
  - Exercise `GuzzleTransport` with both cURL and Stream handlers.
  - Exercise an injected PSR-18 client through `PsrTransport`.
  - Exercise a real successful `FailoverTransport` fallback to the same local
    target, using a deterministic failing first transport.
  - Send E2E requests only to the local target. Do not use external network
    services; keep DNS/IP denial coverage in deterministic local unit tests.
  - Run the complete E2E suite, not only a filtered test.
- **Focused verification:**

  ```bash
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=E2E
  ```

- **Do NOT:** Add network access to external hosts, create a second target
  server for a transport, edit source outside the allowed test files, or
  change downstream repositories.
- **Ask for help when:** A supported transport cannot share the local target
  server, the deterministic first transport cannot fail in a controlled way,
  or a required E2E check fails.
- **Progress / handover:** Done and accepted by root. Changed only
  `tests/E2E/HttpClientE2ETest.php`. All five transport paths shared one local
  PHP server and port: default `ClientBuilder`/`CurlTransport`,
  `GuzzleTransport` with cURL, `GuzzleTransport` with Stream, injected PSR-18
  through `PsrTransport`, and a successful `FailoverTransport` fallback after
  a deterministic first-transport failure. The complete E2E suite passed:
  5 tests, 46 assertions. `composer lint` and `git diff --check` passed.
  PHPUnit emitted a non-fatal cache permission warning; it did not affect the
  passing result.

### Review phase — Review implementation tickets 1–8

- **Traceability:** AC-1 through AC-9; review each implementation ticket and
  its test evidence.
- **Assigned to:** `joo-reviewer` (Codex runtime default)
- **Status:** done
- **Allowed files:** None for the reviewer. If findings exist, `joo-doc-writer`
  may create or update `findings.md` only from root-approved findings.
- **Dependencies:** Tickets 1–8 accepted; their diffs and focused verification
  evidence are available.
- **Acceptance and edge cases:**
  - Follow `.ai/skills/code-review/SKILL.md` and the reviewer contract:
    trigger scan; Round 1 Entry & Scope, Task & Tests, Code Quality; triggered
    Security and Performance checks in Round 2; Docs in Round 3 after earlier
    rounds pass.
  - Give every checklist item a verdict and report findings with evidence,
    severity, and suggested disposition. Do not leave findings untriaged.
  - Root triages each finding. If findings exist, root approves any
    `findings.md` update by `joo-doc-writer`; assigned developers fix or root
    explicitly rejects/defer findings, and the reviewer verifies dispositions.
  - Close the phase only after all findings are fixed and verified or
    explicitly rejected with evidence, and the reviewer approves.
- **Verification:** Review the complete implementation diff and evidence for
  tickets 1–8. Re-run the affected ticket's focused command when verifying a
  fix.
- **Do NOT:** Edit implementation files, write `findings.md` directly,
  self-triage findings, or approve unresolved findings.
- **Ask for help when:** The scope gate fails, required diff/test evidence is
  missing, a finding's disposition is unclear, or a required re-verification
  fails.
- **Progress / handover:** Review completed and accepted by root. Rounds 1–3
  passed with no open findings. The reviewer verified F-1 (cacheable 2xx
  behavior), F-2 (deadline enforcement at the terminal boundary), and F-3
  (response-only size cap preserving HMAC request signing). Scorecard: zero
  critical, required-open, optional, or nit findings. The optional JSON Schema
  validator test was skipped because its dependency is not installed; this is
  recorded as a QA limitation.

### Ticket 9 — Independent final QA and next-minor readiness assessment

- **Traceability:** AC-10; independently verify AC-1 through AC-9 from the
  accepted implementation and review evidence.
- **Assigned to:** `joo-qa` (Codex runtime default)
- **Status:** done
- **QA:** Accepted — local checks pass; remote OSV, Dependency Review,
  Codecov, and Sonar statuses remain unverified. Semgrep scanned tracked files
  only; the optional JSON Schema test was skipped.
- **Allowed files:** None for QA; return evidence to root. If root approves a
  QA ledger, `joo-doc-writer` may write only `qa.md` from that approved
  evidence.
- **Dependencies:** Tickets 1–8 accepted; Review phase passed and findings
  are closed.
- **Acceptance and edge cases:**
  - Run project verification in the project containers and independently
    record environment, branch/commit, commands, results, and any unavailable
    gates.
  - Verify the complete E2E suite and final quality/security gates listed
    below; do not infer a pass from a developer handoff or missing result.
  - Map verification evidence to AC-1 through AC-10, including regression,
    failure-mode, integration, and benchmark/ownership results.
  - Assess next-minor readiness from the evidence; state blockers or
    uncertainty. Do not select a version or edit changelog/release metadata.
  - Gitleaks and Semgrep must use the pinned images/digests from the project
    CI workflow. Record CI-only security gates, including OSV Scanner and
    Dependency Review, when they cannot be run locally.
- **Verification commands:** Run from `projects/client/`:

  ```bash
  tools/ci/docker-compose run --rm php composer ci
  tools/ci/docker-compose run --rm php ./vendor/bin/phpunit --testsuite=E2E
  make bench
  tools/ci/docker-compose run --rm php composer audit
  docker run --rm --volume "$PWD:/repo:ro" --workdir /repo ghcr.io/gitleaks/gitleaks:v8.30.1@sha256:b109bc5f8f76a38196a3e413704fc5b9e3c32360bce4e4b603bd6f45b3721dbb detect --source . --redact --exit-code 1
  docker run --rm --volume "$PWD:/src:ro" semgrep/semgrep@sha256:67319956da3dcb58baf5b322899c15458e3963e7018a86aeeb5cd224e69cb77a semgrep scan --config p/php --error
  ```

- **Do NOT:** Modify source, tests, or release metadata; choose a version,
  create a tag, publish, or claim a gate passed without recorded evidence.
- **Ask for help when:** Any configured gate fails or is unavailable, a
  required CI-only status cannot be inspected, readiness evidence is
  inconclusive, or release metadata requirements need clarification.
- **Progress / handover:** QA completed and accepted by root. In the project
  container, isolated `composer ci` passed: 298 tests, 840 assertions, one
  optional-dependency skip, and 92.40% coverage (1921/2079). E2E passed: 5
  tests, 46 assertions. `composer audit`, pinned Gitleaks, and pinned Semgrep
  passed; Semgrep scanned 124 tracked PHP targets and did not include new
  untracked tests. `make bench` passed at 20.973 μs ±3.98% and created no
  artifact. The unisolated CI invocation failed only because pre-existing
  coverage/cache outputs are owned by UID 65534; those files were preserved.
  Next-minor readiness is conditional: local evidence is green, but remote
  OSV, Dependency Review, Codecov, and Sonar statuses were not available, and
  the optional JSON Schema validator path did not execute in this container.

### Reporting phase — Final traceability

- **Traceability:** AC-1 through AC-10.
- **Assigned to:** `joo-reporter` (Codex runtime default)
- **Status:** done
- **Allowed files:** `docs/01-projects/client/report.md` only; the reporter
  owns this single final artifact.
- **Dependencies:** Review phase closed; Ticket 9 QA passed; root-approved
  implementation, review, findings, and QA evidence is available.
- **Acceptance and edge cases:**
  - Map every AC to its ticket, implementation evidence, review disposition,
    and QA result.
  - List known risks, unresolved items, and missing or unverified evidence;
    do not infer success from absent evidence.
  - Write the final traceability report for root review. The root verifies the
    report before presenting it at the user's final acceptance gate.
- **Do NOT:** Modify source, tests, or release metadata; invent evidence;
  declare overall completion; commit, publish, or tag.
- **Ask for help when:** Any AC lacks implementation/review/QA evidence, a
  finding remains unresolved, or the approved artifacts conflict.
- **Progress / handover:** `joo-reporter` created
  `docs/01-projects/client/report.md`, mapping AC-1–AC-10 to implementation,
  review, and QA evidence, with known risks and unverified gates. Root reviewed
  the traceability and confirmed the conditional readiness boundary. No
  version was selected; no tag or publish was created.

## Decision log

| Decision | Approved choice | Rationale |
| --- | --- | --- |
| Repository scope | `projects/client` only; downstream SDK/MCP repos are excluded. | Keep this audit-hardening plan within the independent PSR-18 client package. |
| Dispatch | Root dispatches tickets sequentially and accepts each handoff. | Avoid overlapping writes, including shared retry and cache files. |
| E2E target | One local PHP HTTP target server is shared by the supported transport matrix. | Keep real transport checks local and avoid external network dependencies. |
| DNS/IP denial tests | Deterministic local unit tests abort before outbound requests. | Keep denial behavior reproducible and local. |
| Delivery gates | Review follows implementation; independent QA follows review; reporting follows QA. | Apply the standard full-delivery review, QA, and traceability contracts. |
| Release readiness | Assess next-minor readiness, but root confirms metadata/version decisions. | No version selection, tag, or publish is authorized by this plan. |
