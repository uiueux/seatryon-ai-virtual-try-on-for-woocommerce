# M5 QA Report

- Date: 2026-08-09
- Gate: G5 — API and privacy
- Verdict: **PASS**
- Open M5 blockers: **0**

## Security and lifecycle matrix

| Area | Result | Evidence |
| --- | --- | --- |
| Route contract | PASS | Every route declares methods, arguments, validation, permission callback, and stable public error mapping. |
| Logged-in authentication | PASS | Cookie authentication requires a valid REST nonce and the derived owner identity must match the job. |
| Guest authentication | PASS | HttpOnly session plus action/product/expiry-bound HMAC tokens; exact-expiry rejection, same-origin checks, replay CAS, and bounded stale-marker cleanup are tested. |
| Ownership | PASS | Unknown and cross-owner job IDs return the same non-enumerating 404 behavior; guest cross-session access is denied. |
| Upload | PASS | MIME/bytes/decode/size validation, orientation normalization, randomized private paths, and cleanup on every create failure are covered. |
| Quota | PASS | Preflight is advisory; the authoritative atomic charge occurs at initial provider dispatch. A stable job dispatch ID prevents duplicate charging. |
| Scheduling | PASS | Public Action Scheduler APIs, unique actions, delayed initialization, bounded attempts, exponential retry, and group cancellation are covered. |
| Job concurrency | PASS | Exact JSON compare-and-swap, owner/idempotency locks, worker locks, delete/cleanup locks, and retained-job scheduling recovery close known races. |
| Result delivery | PASS | Result bytes are streamed only after ownership authorization with no-store/nosniff headers; private paths and provider URLs are not serialized. |
| Privacy | PASS | Exporter lists metadata only, eraser invokes the same locked cleanup service, and deactivation/uninstall remove indexed and orphaned private data. |
| Media Library | PASS | Integration assertions show no attachment is created for customer uploads or generated results. |

## Regression evidence

- Full PHP unit suite on PHP 7.4 and 8.3: **306 tests / 935 assertions**.
- Full WPCS/PHPCompatibility scan: **146/146 files clean**.
- PHPStan level 6: **zero errors**.
- `tests/qa/m5-wordpress-runtime.php`: **PASS** on PHP 7.4 and 8.3.
- `tests/qa/m5-rest-integration.php`: **PASS** on PHP 7.4 and 8.3 with self-restoring database and upload fixtures.

## Residual operational note

A callback that persisted `DISPATCH_STARTED` but lost the provider response is intentionally not replayed automatically. It remains processing until TTL cleanup rather than risking a second paid generation. This is an explicit at-most-once safety decision, not an unhandled retry path.

**Final M5 QA status: PASS.**
