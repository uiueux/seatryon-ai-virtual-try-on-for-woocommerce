# M2 Independent QA Report

> Milestone: M2 — Core domain services  
> Gate: G2 — Core gate  
> QA date: 2026-08-09  
> QA status: **PASS**  
> Scope: `src/`, `tests/php/`, M2 requirements, ADR-001 through ADR-004

## 1. Conclusion

G2 passes after independent review and regression testing.

The final implementation satisfies the M2 contract for typed settings, provider-safe secrets and DTOs, controlled prompts, job state and idempotency, private temporary storage, daily dispatch quota, atomic locking, and redacted WooCommerce logging. No open P0/P1 security, privacy, correctness, or compatibility blocker remains in M2.

This conclusion does not claim live Provider connectivity, REST/Action Scheduler integration, or production concurrency certification. Those items remain assigned to their later milestones as listed in section 7.

## 2. Baseline reviewed

QA read and checked the implementation against:

- `sea-tryon-doc/DEVELOPMENT_PLAN.md`, including all M2 tasks, constraints and G2 criteria;
- `sea-tryon-doc/REQUIREMENTS.md`;
- `sea-tryon-doc/m0/ADR-001-product-defaults-and-readiness.md`;
- `sea-tryon-doc/m0/ADR-002-seaai-contract.md`;
- the private storage implementation and storage probe;
- `sea-tryon-doc/m0/ADR-004-frontend-mounting.md`;
- the WordPress plugin-development security and data/cron rules;
- every M2 source file and every PHP unit-test file.

The review did not rely on developer completion statements. Findings were reproduced with independent adversarial commands before fixes were accepted.

## 3. Automated verification

| Check | Final result | Evidence |
| --- | --- | --- |
| PHP 7.4 syntax | PASS | 67 PHP source/test/entry files linted successfully with PHP 7.4.16. |
| PHP 8.3 syntax | PASS | The same 67 files linted successfully with PHP 8.3.1. |
| PHPCS / WPCS / PHPCompatibility | PASS | 45 configured production PHP files, zero errors. |
| PHPStan level 6 | PASS | 45 production files, zero errors, 1 GB memory limit. |
| PHPUnit on PHP 7.4 | PASS | 98 tests, 271 assertions. |
| PHPUnit on PHP 8.3 | PASS | 98 tests, 271 assertions. |
| Runtime dependency smoke, PHP 7.4 | PASS | WooCommerce missing, too old and supported modes. |
| Runtime dependency smoke, PHP 8.3 | PASS | WooCommerce missing, too old and supported modes. |
| Direct-access guards | PASS | 43/43 files under `src/` contain the `ABSPATH` guard. |
| Test-log scan | PASS | No generated `.log`, `.out` or `.tap` test logs were present. |
| Pure-domain smoke | PASS | PHP 7.4 with no WordPress/WooCommerce runtime loaded composed a furniture prompt and generated a 128-bit ID. |

The MAMP CLI distributions do not load a `php.ini` by default. PHPUnit was therefore run with a minimal explicit `mbstring` extension load for both PHP versions. This is an environment configuration detail, not a plugin defect. PHPStan 1.12 also printed its upstream age notice but completed with zero findings.

## 4. M2 acceptance matrix

| Area | Status | Independent assessment |
| --- | --- | --- |
| M2-01 Settings repository | PASS | Frozen defaults match ADR-001: global off, OpenAI selected, guests allowed, both limits 3, OpenAI `auto`, SeaAI `low`, debug off and success count zero. Values are typed, bounded and allowlisted. |
| Provider mutual exclusion | PASS | Only `openai` or `seaai` is exposed, and `SecretStore` returns only the selected Provider's key. |
| SeaAI URL transport | PASS | Persisted merchant values require HTTPS. HTTP is limited to local/development runtime, loopback by default, or an explicit development-only filter; production cannot opt in. |
| M2-02 Secret handling | PASS | Database option, constant and filter precedence were inspected. Blank/masked saves preserve the old secret, saves request autoload off, masks reveal no fragment, and a separate-process constant override/mask smoke passed. |
| Redaction | PASS | Nested arrays, throwables, known secrets, Bearer tokens, JSON/query secrets, image payloads, cookies, sessions, signatures, result/download URLs, IPv4 and IPv6 are covered. An adversarial nested-context replay returned only redacted values. |
| M2-03 Provider contracts | PASS | Provider adapters receive normalized DTOs and return private storage references, never image bytes or public result URLs. MIME, byte size, option allowlists and safe Provider request IDs are validated. |
| M2-04 Prompt composer | PASS | All nine experience types are covered. Person modes preserve identity/pose/body/background while applying the product; furniture and product-placement modes preserve scene layout/perspective/lighting and require realistic scale/contact/occlusion. Merchant text is stripped of HTML/control bytes, UTF-8 checked and limited to 2,000 characters. |
| M2-05 Job model | PASS | Generated IDs are 128-bit CSPRNG values. Owner hashes and idempotency fingerprints are validated, raw idempotency keys are not persisted in jobs, legal transitions are explicit, and terminal/expiry behavior is tested. |
| Job creation race contract | PASS | `save_if_absent()` explicitly requires an atomic unique owner/idempotency operation and returns the race winner. The service replay path returns the original job. The concrete repository remains correctly deferred to M5. |
| M2-06 Temporary storage | PASS | Storage uses site-isolated 128-bit scopes and exclusive random filenames, accepts only storage-relative identifiers, canonicalizes paths, prevents traversal and refuses a public root. It creates no attachment or public URL. |
| Broader web-root containment | PASS | A trustworthy canonical `DOCUMENT_ROOT` ancestor is used when WordPress is installed below the web root. The previously missed `htdocs/temp` sibling case now fails closed. Independent replay confirmed `public_sibling_temp_rejected=true`. |
| Symlink handling | PASS | File resolution uses canonical containment; tree cleanup does not follow child links. Top-level scope links are unlinked directly, and deterministic tests confirm the external target is unchanged for explicit deletion and TTL cleanup. |
| TTL and deletion | PASS | TTL is constrained to 1–86,400 seconds, boundary cleanup is tested, and explicit file/scope deletion is supported. |
| M2-07 Quota identity | PASS | Logged-in and guest namespaces are separate; raw guest session IDs are not persisted. Site-local midnight resets are tested. Limits are 1–100. |
| Dispatch charging/idempotency | PASS | A stable dispatch ID consumes exactly once, replays do not consume again, full quota fails closed, and persistence/lock failures prevent Provider authorization. Actual worker call ordering is an M5 integration item. |
| Atomic lock | PASS | Non-persistent sites use `add_option()` plus an atomic database compare-and-delete for release and expired takeover. Persistent caches use atomic `wp_cache_add()` and backend TTL without unsafe active deletion. |
| Lock race regression | PASS | The original get-then-delete race was independently reproduced before the fix. After the fix, the same interleaving produced `second_acquired=false`, `competitor_survived=true`, `cas_calls=1`. Dedicated release/takeover/cache tests are included. |
| M2-08 Logging | PASS | `WC_Logger` source is forced to `sea-tryon`; debug is opt-in; messages and recursive context are redacted before the backend receives them; absent WooCommerce safely no-ops. |
| Pure-domain isolation | PASS | Domain, DTO, prompt, quota and contract behavior can be exercised with only Composer autoload and an `ABSPATH` sentinel; no WordPress/WooCommerce function is needed by pure-domain execution. |
| ADR alignment | PASS | Defaults match ADR-001; SeaAI remains the ADR-002 synchronous-only contract until a versioned async contract exists; storage follows ADR-003; no M2 code conflicts with the ADR-004 M6 mounting decision. |

## 5. Findings raised and closed during QA

| ID | Severity | Initial finding | Resolution | Final status |
| --- | --- | --- | --- | --- |
| QA-M2-01 | P1 | Lock ownership used a non-atomic get-then-delete sequence, allowing a stale owner to delete a replacement lock. | Option locks now use conditional database compare-and-delete; cache locks rely on atomic add and TTL. Deterministic interleaving tests were added. | PASS |
| QA-M2-02 | P1 | Storage checked only `ABSPATH`, which could miss a public parent `DOCUMENT_ROOT`. | The factory now selects a verified canonical DocumentRoot ancestor and rejects public sibling temp directories. | PASS |
| QA-M2-03 | P1 | Nested log fields for sessions, cookies, signatures and result URLs, plus IPv6 text, were not fully redacted. | Sensitive-key coverage and validated IPv6 redaction were added with regression tests. | PASS |
| QA-M2-04 | P1 | `ProviderResult` described its Provider request ID as sanitized but accepted a URL containing a token. | Request IDs now use a bounded safe-ASCII allowlist; URL/token input is rejected and tested. | PASS |
| QA-M2-05 | P2 | A top-level scope symlink could be reported removed while the link remained. | Explicit and TTL cleanup now unlink the link itself without following the target. | PASS |
| QA-M2-06 | P2 | Persisted SeaAI base URLs accepted arbitrary cleartext HTTP. | Persisted settings are HTTPS-only; the narrowly scoped development override is runtime-only. | PASS |
| QA-M2-07 | P2 | Multiple domain/support source files lacked the direct-access guard required by the requirements. | All 43 source files now contain the guard. | PASS |

## 6. G2 gate criteria

| G2 criterion | Status | Notes |
| --- | --- | --- |
| Prompt, state machine, quota boundary, idempotency, redaction and TTL tests pass | PASS | Covered by 98 tests / 271 assertions on PHP 7.4 and 8.3. |
| Pure domain code can be tested without WordPress/WooCommerce | PASS | Independent PHP 7.4 smoke passed with no framework runtime. |
| No API key, image data or token enters test logs | PASS | Logger tests prove redaction; independent test-log scan found no generated logs. Synthetic secrets remain only as intentional test inputs in source. |
| ADR-001 through ADR-004 exist and agree with the implementation boundary | PASS | No M2 conflict found. |

**G2 result: PASS. M3, M4 and the M6 UI shell may proceed according to the dependency plan.**

## 7. Deferred verification

These are planned later-stage checks, not M2 failures:

| Item | Status | Required gate |
| --- | --- | --- |
| Real OpenAI and SeaAI credential/connectivity tests | DEFERRED | G4; credentials are not present. |
| SeaAI async query support | DEFERRED | Out of MVP under ADR-002 unless a versioned Universal X query contract is supplied. |
| Real WordPress database CAS under multi-process contention | DEFERRED | G5/G7 integration and operational concurrency testing. |
| Persistent Redis/Memcached object-cache contention and TTL behavior | DEFERRED | G7 compatibility matrix. Unit contract for cache-present/cache-absent paths passes. |
| Action Scheduler dispatch ordering, retries and no-double-charge behavior | DEFERRED | G5 worker integration. |
| Native Unix symlink behavior and filesystem permissions | DEFERRED | G7 platform compatibility. Deterministic no-follow tests pass; Windows test host cannot create native symlinks. |
| REST ownership, HMAC guest session and result streaming | DEFERRED | G5. |
| SeaAI root-path validation and Provider SSRF/DNS/result allowlist | DEFERRED | M3/M4 and G4/G5. M2 enforces transport policy only. |

## 8. Residual non-blocking notes

- Cache-backed locks intentionally remain until their short TTL because WordPress exposes no portable public compare-and-delete cache primitive. This favors correctness over immediate lock reuse and must be observed in G7 load testing.
- `DOCUMENT_ROOT` is used only when it canonicalizes to an ancestor of `ABSPATH`; unrelated or unavailable values safely fall back to the WordPress root. M5's runtime storage diagnostic should record the effective private/public roots without exposing them to shoppers.
- PHPStan 1.12 is valid for the current PHP 7.4 baseline but is an older maintained line. Dependency modernization can be considered after the compatibility baseline changes.
