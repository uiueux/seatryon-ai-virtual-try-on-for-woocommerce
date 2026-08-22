# M7 QA and Release-Readiness Report

- Date: 2026-08-09
- Milestone: M7 — Security, compatibility, performance, and regression QA
- Local code/security verdict: **PASS**
- Full G7 verdict: **BLOCKED / DEFERRED**
- Open code P0/P1 defects: **0**

## Completed evidence

| Area | Result | Evidence |
| --- | --- | --- |
| PHP compatibility | PASS (available matrix) | PHP 7.4, 8.1, and 8.3 each passed 306 PHPUnit tests / 935 assertions. PHP 7.4/8.3 also passed real M3, M5, and M6 WordPress integration paths. |
| Static quality | PASS | PHPCS/WPCS/PHPCompatibility passed 146 production PHP files; PHPStan level 6 reported zero errors. |
| JavaScript/CSS | PASS | JS/CSS lint, eight Jest tests (including axe-core semantic accessibility), production build, RTL CSS, and no-source-map verification passed. |
| REST security | PASS | Logged-in/guest auth, same origin, action tokens, exact expiry, replay, cross-session/owner denial, schema/error mapping, quota, upload, result, delete, and cleanup passed real WP REST integration. |
| Provider security | PASS (offline) | Frozen request shapes, error maps, private storage, key isolation, bounded retries, malformed responses, SSRF/special-IP rejection, and zero legacy SeaAI query calls passed fixtures. |
| Privacy | PASS | No Media Library attachment, no public result URL, exporter/eraser, TTL/delete/deactivate/uninstall cleanup, and redacted logs are covered. |
| Source audit | PASS | 144 `src/*.php` files contain the direct-access guard; no WooCommerce internal API use, debug dumps, raw credential, or production attachment creation was found. |
| Frontend E2E | PASS (local Chromium) | Real product page, keyboard close/focus return, guest-disabled login state, 320 px viewport, and empty console warning/error log passed. |
| Theme rendering | PASS (available matrix) | Exactly one trigger and modal under Storefront 4.6.2, Twenty Twenty-Five, and the installed Art theme. |
| Operational behavior | PASS (deterministic) | Dispatch ledger, worker locks, retry ceiling, quota idempotency, cleanup locks, recurring cleanup, hidden-tab polling pause, and polling backoff are tested. |
| Production dependencies | PASS | Runtime package has no Node production dependencies; `npm audit --omit=dev` reported zero vulnerabilities. |
| Distribution | PASS | Deterministic Windows release builder, forbidden-content scan, credential scan, and SHA-256 output passed. |

## Non-production development dependency advisory

The full npm development tree reports 34 upstream/transitive advisories (26 moderate, 8 high) through the current pinned WordPress build/environment tooling. The production ZIP excludes `node_modules`, npm metadata, source code, and these tools; `npm audit --omit=dev` is clean. No unsafe forced override was applied.

Composer's executable is unavailable in this Windows environment, so a fresh `composer audit` command could not be run. The locked packages are development-only and are excluded from the production ZIP; PHPCS, PHPStan, and PHPUnit were executed from the installed lock successfully.

## External blockers to full G7

1. No OpenAI or SeaAI credentials were supplied, so real person/scene generations, image-quality review, success rate, P50/P95 latency, billing, and provider-account behavior remain unverified.
2. WooCommerce 11.x was not installed in the local environment. The official release page available during testing still listed 10.9.4 as stable, while WooCommerce's 11.0 pre-release notice scheduled final release for July 28, 2026. A real 11.x run is required before extending the compatibility header.
3. WordPress 6.9, a second third-party theme, Firefox, Safari, Edge, 200%/400% browser zoom, NVDA/VoiceOver, WAVE, and Woo QIT were not available in this workspace. Axe-core semantic checks did pass in Jest; color contrast was verified only through CSS/manual viewport review because jsdom has no canvas rendering.
4. Docker is unavailable, so the configured `wp-env` 11.12.0 environment could not be started.

These are release-evidence gaps rather than accepted product defects. Compatibility claims remain restricted to actually tested versions.

**Final M7 status:** local implementation and security regression **PASS**; full G7 **BLOCKED / DEFERRED** until the external matrix above is completed.
