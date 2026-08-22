# M0 Independent QA Report

- Date: 2026-08-09
- Reviewer: Independent QA agent
- Scope: `DEVELOPMENT_PLAN.md` M0/G0, ADR-001 through ADR-004, provider contracts, all M0 fixtures, and the storage probe
- Final gate decision: **G0 PASS**

## 1. Review method

This review did not accept the deliverable authors' pass statements as evidence. It independently inspected the artifacts and reran machine-verifiable checks in the local environment.

The WordPress project detector from the `wp-project-triage` skill was run first. It returned valid JSON with `project.kind`, `signals`, and `tooling`; the project kind is currently `unknown`, which is expected because M0 is documentation-only and no plugin bootstrap, header, `block.json`, or build/test configuration exists yet. Reclassification as a WordPress plugin is a G1 requirement after the M1 skeleton is created.

## 2. M0 task results

| Item | Result | Independent QA evidence |
| --- | --- | --- |
| M0-01 defaults and readiness | **PASS** | ADR-001 freezes guest access, separate logged-in/guest limits, provider mutual exclusivity, model/quality defaults, one result, 24-hour maximum retention, compatibility baseline, and no Media Library storage. Missing credentials have named owners and are required before M4 real-provider acceptance. |
| M0-02 credential readiness | **DEFERRED (non-blocking for G0)** | No OpenAI or SeaAI secret is present. No live call is claimed. ADR-001 assigns delivery to the Product owner / SeaAI owner before M4 acceptance, consistent with the mock-first G0 rule. |
| M0-03 SeaAI query decision | **PASS** | ADR-002 documents the inspected legacy query route and explicitly adopts synchronous-only `images` for Universal X. The unexpected `task_id` fixture requires `provider_contract_error`, preserves diagnostic metadata, disables polling, and disables retry. |
| M0-04 provider contracts and fixtures | **PASS** | All 11 JSON fixtures parse. OpenAI fixtures use `POST /v1/images/edits`, fixed `gpt-image-2`, two image parts, and the documented retry mappings. SeaAI fixtures cover upload, synchronous generation, supported mixed image-entry shapes, contract drift, and representative permanent/transient errors. |
| M0-05 private storage handoff | **PASS** | `storage-probe.php` passes syntax checks on PHP 7.4.16 and 8.3.1. QA reran both directions: PHP 7.4 CLI write → Apache `apache2handler` read, and Apache write → PHP 8.3 CLI read. Both preserved the nonce and validated SHA-256. Cleanup succeeded and the probe directory no longer exists. The resolved root is outside the WordPress web root. |
| M0-06 frontend mounting | **PASS** | ADR-004 identifies the public `woocommerce_after_add_to_cart_form` action for classic templates and supported WooCommerce product blocks, prohibits private/internal APIs, and approves an API v3 dynamic block fallback for templates that omit or disable the compatibility hook. WC 11 runtime matrix execution remains an explicitly scheduled later compatibility test. |

## 3. Fixture and contract checks

### OpenAI

- **PASS** — Success fixture base64 decodes strictly to 68 bytes with PNG signature `89504e470d0a1a0a`, MIME `image/png`, and dimensions 1×1.
- **PASS** — Success expectations explicitly prohibit WordPress attachment creation.
- **PASS** — User error is non-retryable; transient rate limit honors `Retry-After`; server error uses bounded retry; total attempts are capped where specified.
- **PASS** — The previously detected quota inconsistency is fixed. `openai-contract.md` now requires one atomic count when the initial Provider request is actually dispatched and forbids recounting retries for the same job, matching ADR-001 and `DEVELOPMENT_PLAN.md`.
- **DEFERRED (M4)** — Malformed response, authentication, access, quota-specific 429, timeout, and oversized-result data-provider cases are specified but do not each have a standalone M0 fixture. This does not block the minimum repeatable M0 mock contract.

### SeaAI

- **PASS** — Upload requires multipart field `file` and an HTTPS `download_url`.
- **PASS** — Generation fixes `model_name=universal_x`, uses two ordered reference URLs in the normal case, `n=1`, and plugin default `quality=low`.
- **PASS** — String and object image entries normalize to the expected HTTPS URLs.
- **PASS** — 400/402/403 fixtures are non-retryable; 502/503 are only retry-eligible under bounded retry policy; a Universal X `task_id` never triggers the legacy query endpoint.
- **DEFERRED (M4)** — Dedicated 401 and network-failure fixture cases remain part of the full adapter contract-test matrix. Their behavior is already defined in ADR-002 and their absence does not block G0.

## 4. Privacy and security checks

- **PASS** — Secret scan found no API key or unredacted Bearer credential in M0 artifacts.
- **PASS** — Fixtures contain synthetic identifiers and URLs only; the storage probe contains no customer data.
- **PASS** — M0 contracts consistently reject attachment creation, Media Library registration, public upload fallback, and public result URLs.
- **PASS** — The storage probe left no temporary payload or directory after QA.

## 5. G0 gate decision

| G0 condition | Result | Rationale |
| --- | --- | --- |
| Repeatable OpenAI and SeaAI mock contracts; live credentials have owner/deadline | **PASS** | Contracts and fixtures are replayable; live credentials are explicitly due before M4 acceptance. |
| SeaAI async contract or explicit synchronous-only launch | **PASS** | ADR-002 selects synchronous-only `images` and fails closed on `task_id`. |
| Private temporary files work across job/REST process boundaries | **PASS** | The private temp root is outside the web root and real CLI/Apache cross-SAPI handoff passed in both directions. A full Action Scheduler callback is correctly retained as an M5 integration test. |
| Supported block-product automatic mount or approved dynamic fallback | **PASS** | ADR-004 provides both the public WooCommerce action path and dynamic block fallback. |
| Open decisions have owner, default, and deadline | **PASS** | ADR-001 freezes defaults and names credential owners/deadline; later live and compatibility validations are assigned to their planned gates. |

**Final result: G0 PASS.** M1 may start. This decision approves the mock-first architecture and M0 technical contracts; it does not claim live OpenAI/SeaAI connectivity, a production Action Scheduler cycle, or the later WC 11 E2E matrix.
