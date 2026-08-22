# M3 Independent QA Report

- Date: 2026-08-09
- Milestone: M3 — WooCommerce Administration
- Gate: G3 — Administration Acceptance
- Verdict: **PASS**
- Blocking defects: **0 open**

## 1. Scope

Independent review covered `src/Admin/**`, the M3 service wiring in `src/Plugin.php`, M3 unit tests, and a self-restoring integration smoke against the local WordPress/WooCommerce runtime. The review used `sea-tryon-doc/REQUIREMENTS.md` and the G3 criteria in `sea-tryon-doc/DEVELOPMENT_PLAN.md` as the acceptance baseline.

Two defects found during review were rejected before final acceptance and then re-tested after correction:

1. An enabled product did not require a readable main product image (`PRD-003`).
2. SeaAI Base URL validation accepted URLs that did not identify a gateway root ending in `/wp-json/seaai/v1` (`ADM-004`).

Both are closed. Product enablement now requires a real image attachment with a readable attached file, and SeaAI settings now require HTTPS, the expected gateway-root suffix, and no userinfo, query, or fragment. WordPress subdirectory installations remain supported.

## 2. Automated quality results

| Check | Result | Evidence |
| --- | --- | --- |
| PHP 7.4 syntax, M3 Admin/Plugin/QA scope | PASS | 27 files, no syntax errors. |
| PHP 8.3 syntax, M3 Admin/Plugin/QA scope | PASS | 27 files, no syntax errors. |
| PHPCS | PASS | 63/63 first-party PHP files at the repository snapshot. |
| PHPStan level 6 | PASS | 63/63 analyzed files, zero errors. |
| M3 Admin PHPUnit, PHP 7.4 | PASS | 35 tests, 98 assertions. |
| M3 Admin PHPUnit, PHP 8.3 | PASS | 35 tests, 98 assertions. |
| Repository PHPUnit, PHP 7.4 | PASS | 133 tests, 369 assertions. |
| Repository PHPUnit, PHP 8.3 | PASS | 133 tests, 369 assertions. |
| JavaScript/style lint | PASS | `npm run lint`. |
| Production asset build | PASS | `npm run build`; verified artifacts contain no source maps. |
| Direct-access guards | PASS | Every `src/Admin/**/*.php` file contains the `ABSPATH` guard. |
| Internal/retired API scan | PASS | No `Automattic\\WooCommerce\\Internal`, `@internal`, `@woocommerce/product-editor`, or `product-block-editor-v1` usage. |

The M3 integration artifact is `tests/qa/m3-wordpress-integration.php`.

## 3. Real WordPress/WooCommerce integration

The self-restoring integration smoke passed with both PHP 7.4.16 and PHP 8.3.1 against:

- WordPress 7.0.2
- WooCommerce 10.9.4
- `WP_DEBUG=true`

Verified behavior:

- `Plugin` registers the Products settings filters, Classic Product Editor render/save actions, reset action, and diagnostic notices only after the supported WooCommerce runtime is available.
- The WooCommerce settings HTML contains only the fixed credential mask; neither stored provider key appears in the response.
- Blank OpenAI and masked SeaAI submissions preserve stored credentials; both credential options remain non-autoloaded.
- Only the selected provider's key is returned by the server-side active credential boundary.
- Daily limits are clamped to 1–100 by field-level sanitizers.
- An authorized statistics reset checks the WooCommerce capability and nonce and changes only the aggregate counter.
- A subscriber cannot save WooCommerce settings or reset statistics, and neither rejected attempt mutates data.
- Simple and variable parent products save, reload, and render enabled, sanitized prompt, and experience-type metadata through WooCommerce CRUD.
- A product without a readable main image cannot be enabled and receives a WooCommerce inline error.
- Configuration notices are invisible to unauthorized users, visible to a store manager/administrator, escaped, and contain no API key.
- The exercised plugin paths emitted no PHP warning, notice, or deprecation under `WP_DEBUG`.

The script snapshots all involved options, creates isolated draft products and users, and restores/removes them in `finally`. A post-run audit found no QA users or products and confirmed the original `active_plugins` value (`woocommerce/woocommerce.php`) was unchanged.

## 4. G3 gate matrix

| G3 criterion | Result | Notes |
| --- | --- | --- |
| Unauthorized users cannot read/modify settings or reset statistics | PASS | WooCommerce settings page/save use `manage_woocommerce`; real rejected save/reset attempts caused no mutation. Diagnostic and reset notices also check capability. |
| Blank key does not delete; full key is not displayed | PASS | Real WooCommerce render/save plus unit tests; fixed mask only and autoload off. |
| Providers are mutually exclusive and hidden fields do not pollute the selected provider | PASS | Provider allowlist and conditional rows verified; masked/blank hidden credentials preserve stored values; active credential access returns only the selected provider. |
| Simple/variable product metadata save, read, and validation | PASS | Real CRUD round trips for both parent types; prompt sanitization, experience allowlist, nonce/capability, atomic failure, and readable-image requirement covered. |
| No notice/deprecation under WP_DEBUG | PASS | Error handler covered plugin boot, settings render/save, reset, products, storage health, and notice rendering. |
| Public WooCommerce APIs only | PASS | Settings API filters, `woocommerce_product_options_advanced`, `woocommerce_admin_process_product_object`, WooCommerce CRUD, and public WordPress APIs only. |
| Diagnostic notices are authorized, escaped, and not duplicated | PASS | Unit coverage verifies duplicate dependency suppression and dynamic-context escaping; real integration verifies audience and secret-free output. |
| Plugin integration is correct | PASS | Supported WooCommerce admin runtime registers all M3 services; dependency guard behavior remains intact. |

## 5. Deferred observations

- **DEFERRED to M7:** exhaustive browser/viewport, screen-reader, RTL, and multisite visual matrices. M3 uses native WooCommerce form controls and public rendering helpers; conditional rows expose `hidden` and `aria-hidden`, and no G3 blocker was observed.
- Local PHP installations print startup warnings for a missing PHP 7.4 GD DLL and a duplicate/missing PHP 8.3 MySQLi DLL. These occur before WordPress/plugin execution, are environment configuration defects, and were not emitted by the plugin. Required test extensions remained available and all suites passed.

## 6. Decision

**G3 PASS.** M3 is accepted with no open P0/P1 correctness, security, privacy, compatibility, or data-loss blocker. M4/M5 work may consume the finalized settings, product metadata, and diagnostic contracts.
