# M4 Gate Report

- Date: 2026-08-09
- Milestone: M4 — Provider adapters
- Adapter code-contract: **PASS**
- Full G4 gate: **BLOCKED / DEFERRED**

## Completed scope

- WordPress HTTP transport, bounded multipart/JSON handling and safe error normalization.
- OpenAI GPT Image 2 edit adapter with two ordered input images and private PNG output.
- SeaAI Universal X synchronous adapter with two ordered uploads, `quality=low`, private output and zero legacy query calls.
- Explicit SSRF protection for private, metadata, special-purpose, transition, multicast and IPv4-embedded IPv6 ranges.
- Safe internal retention of an allow-listed unexpected SeaAI `task_id`; it is not included in shopper-facing messages.
- Offline fixtures and contract tests for success, malformed responses, authorization, throttling, provider failures and transport errors.

## Verification

Independent QA passed the M4 PHP 7.4/8.3 test suites, syntax checks, PHPCS, PHPStan and all 11 M0 provider fixtures. The detailed evidence and the closed SSRF finding are recorded in [M4-QA-REPORT.md](M4-QA-REPORT.md).

## External gate blocker

G4 requires real staging generations for both providers in person and scene modes. No real OpenAI or SeaAI credentials/staging endpoint are available in this workspace, so those four calls were not run. Mock responses are not accepted as evidence of live connectivity, account access, billing or generated-image quality.

## M5 handoff

- The runtime factory must prove inactive provider keys/endpoints are never read.
- OpenAI uses `size=auto`; SeaAI uses the frozen `1024x1024` target.
- Queue retries must be bounded and must not repeat quota consumption or provider dispatch for an already-ledgered attempt.
- The safe SeaAI diagnostic reference must remain internal when job errors are persisted and serialized to REST clients.
- Production construction must use the safe WordPress HTTP client.

M4 implementation is accepted for M5 consumption. The full G4 gate remains open until credentials and staging evidence are supplied.
