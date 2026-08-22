# M0 Gate Report

- Milestone: M0 — Decisions and Technical Validation
- Completed: 2026-08-09
- Gate: **G0 PASS**
- Next authorized milestone: M1 — Plugin Skeleton and Toolchain

## Outcome

M0 is complete. The implementation defaults, provider contracts, private storage strategy, background-job boundary and WooCommerce product-page mounting strategy are sufficiently defined to begin M1 without knowingly carrying an architecture-blocking ambiguity.

This is a mock-first architecture approval. It does not claim live OpenAI/SeaAI connectivity, a production Action Scheduler callback cycle, or completed WooCommerce 11 E2E compatibility testing; those checks remain assigned to their later gates.

## Task closure

| ID | Result | Deliverable / decision |
| --- | --- | --- |
| M0-01 | PASS | [ADR-001](ADR-001-product-defaults-and-readiness.md) freezes MVP defaults and records decision owners/deadlines. |
| M0-02 | PASS | Credential readiness is recorded in ADR-001. No secret was supplied or stored; live smoke tests are due before G4. |
| M0-03 | PASS | [ADR-002](ADR-002-seaai-contract.md) adopts synchronous-only SeaAI Universal X and fails closed on unexpected `task_id`. |
| M0-04 | PASS | [OpenAI contract](openai-contract.md) plus repeatable OpenAI and SeaAI fixtures define the minimum request/response/error surface. |
| M0-05 | PASS | The private storage strategy and [storage probe](spikes/storage-probe.php) verify a private cross-SAPI filesystem handoff outside the web root. |
| M0-06 | PASS | [ADR-004](ADR-004-frontend-mounting.md) selects a supported WooCommerce action and approves a dynamic block fallback. |

## Frozen implementation decisions

- Provider selection is mutually exclusive: OpenAI or Third-party API (SeaAI).
- OpenAI uses `gpt-image-2` through `POST /v1/images/edits` with the user/scene image and product image.
- SeaAI uses `model_name=universal_x`, defaults to `quality=low`, and accepts only synchronous `images` for MVP.
- Guest use defaults to enabled; guest and logged-in limits default independently to 3 dispatched generations per site-local day.
- One image is generated per request. Inputs/results remain in private temporary storage for at most 24 hours and never enter the Media Library.
- The default product-page mount is `woocommerce_after_add_to_cart_form` at priority 20.
- `sea-tryon/virtual-try-on`, implemented as an API v3 dynamic block, is the approved fallback for templates without the supported action path.

## Verified evidence

- 11 JSON fixtures parsed successfully.
- The OpenAI success payload strictly decoded to a valid PNG signature.
- `storage-probe.php` passed PHP 7.4 and PHP 8.3 syntax checks.
- PHP 7.4 CLI → Apache and Apache → PHP 8.3 CLI file handoffs both passed checksum validation; cleanup removed the probe payload.
- Secret-pattern scanning found no unredacted API key or Bearer credential in M0 artifacts.
- Independent QA found one quota-timing inconsistency; it was corrected to atomic counting on the initial provider dispatch, with no recount on retries.

Full independent results: [M0-QA-REPORT.md](M0-QA-REPORT.md).

## Deferred, non-blocking dependencies

| Dependency | Owner | Required gate |
| --- | --- | --- |
| OpenAI development key with `gpt-image-2` access | Product owner | G4 |
| SeaAI development gateway URL and API key | Product owner / SeaAI owner | G4 |
| Real Action Scheduler callback and runtime storage self-test | Development / QA | G5 |
| Released WooCommerce 11 compatibility and product-page E2E matrix | Development / QA | G6/G7 |

If credentials are not available by M4, M1-M3 may still proceed, but G4 cannot pass and no real-provider claim may be made.

