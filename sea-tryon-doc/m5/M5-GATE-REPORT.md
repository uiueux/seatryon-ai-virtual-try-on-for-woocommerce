# M5 Gate Report

- Date: 2026-08-09
- Milestone: M5 — REST, jobs, security, and privacy
- Gate: **G5 PASS**

## Delivered

- Discoverable `sea-tryon/v1` REST routes for job creation, status, authenticated result streaming, deletion, and guest-token refresh.
- WordPress cookie/REST-nonce authentication for logged-in users and high-entropy HttpOnly guest sessions with short-lived, action-bound HMAC tokens.
- Same-origin, ownership, consent, product, variation, upload, quota, token-expiry, replay, and idempotency enforcement.
- Server-side JPEG/PNG/WebP validation, orientation handling, bounded normalization, and private temporary storage outside a trusted public document root.
- Versioned job snapshots, exact compare-and-swap repository writes, bounded indexes, per-job locks, persistent dispatch ledgers, and Action Scheduler integration.
- Atomic quota consumption at first provider dispatch; retries and duplicate callbacks do not consume quota or increment success statistics twice.
- Bounded retry/backoff with `Retry-After`, at-most-once handling for ambiguous started dispatches, TTL cleanup, DELETE cleanup, deactivation cleanup, and uninstall cleanup.
- WordPress Privacy Policy Guide text plus personal-data exporter and eraser hooks.
- Production composition root using only the selected provider, the safe WordPress HTTP client, and provider-specific size defaults.

## Verification

- PHP 7.4 and PHP 8.3: 306 PHPUnit tests / 935 assertions passed.
- PHPCS: 146 first-party production PHP files passed.
- PHPStan level 6: 146 files, zero errors.
- Real WordPress 7.0.2 / WooCommerce 10.9.4 runtime integration passed on PHP 7.4 and PHP 8.3.
- Real REST integration passed on PHP 7.4 and PHP 8.3 for logged-in and guest flows, cross-owner/cross-session denial, token replay denial, status, result authorization, deletion, and cleanup.
- Media Library attachment count remained unchanged during upload/job/result lifecycle tests.
- Test products, users, options, scheduled actions, uploads, and active-plugin state were restored after every integration run.

Detailed evidence: [M5-QA-REPORT.md](M5-QA-REPORT.md).

## Deferred external evidence

The worker/provider boundary is fully fixture-tested, but real OpenAI and SeaAI dispatch remains part of the previously recorded live G4 blocker because no credentials were supplied. This does not reopen the G5 API/privacy contract.

**Decision:** G5 passes. M6 may consume the production REST and job contracts.
