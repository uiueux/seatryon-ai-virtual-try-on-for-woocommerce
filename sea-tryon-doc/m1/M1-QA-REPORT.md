# M1 Independent QA Report

Date: 2026-08-09  
Scope: M1 plugin scaffold, lifecycle, dependency guard, PHP/JS quality tooling, CI, and release-package rules  
Result: **G1 PASS**

This report records an independent verification of the repository state. Author summaries were not used as evidence; every PASS below was obtained from source inspection or a repeated local command.

## Gate summary

| G1 criterion | Result | Evidence |
| --- | --- | --- |
| Plugin loads without a fatal when WooCommerce is missing | PASS | Real WordPress 7.0.2 bootstrap with only `sea-tryon/sea-tryon.php` active returned `dependency=0`, registered the English admin notice, and produced no fatal. |
| Plugin loads with supported WooCommerce | PASS | Real WordPress 7.0.2 bootstrap with WooCommerce 10.9.4 and Sea Try-On active returned `dependency=1` and fired `sea_tryon_loaded`. |
| PHP quality commands are executable | PASS | Composer metadata validation and the complete `composer run check` chain passed under PHP 7.4. PHPCS, PHPStan level 6, PHPUnit, and runtime smoke tests also passed independently. |
| JavaScript/CSS lint and production build are executable | PASS | `npm.cmd run lint:js`, `lint:css`, `build`, and `test` all exited 0. |
| PHP 7.4 and 8.3 syntax compatibility | PASS | All 12 first-party PHP files found at test time passed `php -l` with PHP 7.4.16 and PHP 8.3.1. |
| Release contents exclude development/private artifacts | PASS | `.distignore` excludes docs, tests, fixtures, source assets, build scripts, dependency trees/config, CI metadata, maps, environment files, and common key files. A distribution-membership dry run contained only runtime PHP, `readme.txt`, `LICENSE`, and compiled assets. |
| Project triage recognizes the plugin | PASS | Deterministic triage returned primary kind `wp-plugin`; plugin detection found exactly one entrypoint with the expected metadata. No block is part of M1, so `wp-block-plugin` is not expected yet. |

No unresolved FAIL remains.

## Detailed results

### Project shape and metadata

| Check | Result | Notes |
| --- | --- | --- |
| Deterministic WordPress triage | PASS | `project.primary=wp-plugin`; Composer, PHPUnit, wp-env, npm, and `@wordpress/scripts` signals were detected. |
| Entrypoint count | PASS | One plugin entrypoint: `sea-tryon.php`. |
| Header | PASS | Name, description, version `0.1.0`, WordPress `6.9`, PHP `7.4`, WooCommerce `10.9`, GPL license, text domain, and domain path are present. |
| Version consistency | PASS | Header constant, `package.json`, `readme.txt` stable tag, and changelog release are all `0.1.0`. |
| Text domain/default language | PASS | Runtime strings are English and use `seatryon-ai-virtual-try-on-for-woocommerce`; the header declares `Text Domain: seatryon-ai-virtual-try-on-for-woocommerce` and `Domain Path: /languages`. |
| Bootstrap architecture | PASS | One lightweight bootstrap, PSR-4 Composer autoload plus first-party fallback, and service boot on `plugins_loaded` priority 20. |

Observation: the internal `README.md` says a `WC tested up to` claim is withheld, while the plugin header declares `WC tested up to: 10.9`. The installed 10.9.4 bootstrap passed, so this documentation drift is not a G1 blocker, but the sentence should be reconciled before release documentation is frozen.

### Lifecycle and dependency behavior

| Check | Result | Notes |
| --- | --- | --- |
| Activation hook placement | PASS | Registered at main-file scope, not from another hook. |
| Real activation/deactivation | PASS | WordPress `activate_plugin()` and `deactivate_plugins()` completed without error. |
| Existing active-plugin state restored | PASS | The pre-test `active_plugins` snapshot was restored byte-for-byte after all temporary scenarios. |
| Activation safety | PASS | Only `sea_tryon_data_version` is added, with autoload disabled; activation makes no remote calls and does not flush rewrites. |
| Missing/old WooCommerce guard | PASS | No WooCommerce-dependent service boots; an English, escaped notice is registered for users with plugin-management capability. |
| Supported WooCommerce path | PASS | WooCommerce 10.9.4 satisfies the minimum and `sea_tryon_loaded` fires. |
| Deactivation safety | PASS | Only plugin-owned Action Scheduler hooks in group `sea-tryon` are unscheduled when the public function exists. Merchant data is retained. |
| Uninstall safety | PASS | Direct access and non-uninstall execution are guarded; M1 deliberately performs no destructive cleanup. |

### PHP verification

| Command/check | PHP 7.4.16 | PHP 8.3.1 |
| --- | --- | --- |
| All first-party PHP syntax | PASS | PASS |
| PHPCS/WPCS | PASS, 6/6 files | PASS, 6/6 files |
| PHPStan level 6 | PASS, 0 errors | PASS, 0 errors |
| PHPUnit | PASS, 2 tests / 2 assertions | PASS, 2 tests / 2 assertions |
| Runtime dependency smoke | PASS: missing, old, supported | PASS: missing, old, supported |

Additional PHP evidence:

- `composer validate --strict`: PASS.
- `composer run check`: PASS under PHP 7.4, including `lint`, `phpstan`, `test`, and `test:runtime`.
- PHPStan inspection found WordPress and WooCommerce stubs, focused first-party paths, no baseline, and no broad ignored-error pattern.

Environment note: the local MAMP PHP 7.4 configuration emits a startup warning for a missing `php_gd.dll`; PHP 8.3 emits a startup warning for a missing `php_mysqli.dll`. These are MAMP installation/configuration warnings, not plugin diagnostics. All requested syntax and quality checks passed. The real database-backed WordPress activation matrix was therefore run with PHP 7.4, whose `mysqli` extension is available.

### JavaScript, CSS, and production assets

| Command/check | Result | Notes |
| --- | --- | --- |
| `npm.cmd run check-engines` | PASS | Node 24.13.0 and npm 11.6.2 satisfy the declared ranges. |
| `npm.cmd run lint:js` | PASS | Frontend/admin sources and scripts passed WordPress ESLint rules. |
| `npm.cmd run lint:css` | PASS | Both SCSS entries passed WordPress stylelint rules. |
| `npm.cmd run build` | PASS | Frontend/admin JS, LTR CSS, RTL CSS, and `.asset.php` files were generated. |
| Source-map audit | PASS | No `.map` file and no `sourceMappingURL` reference exists in `assets/build`. |
| `npm.cmd test` | PASS with note | The configured Jest command exits 0 with `--passWithNoTests`; M1 has no JS unit cases yet. |
| Dependency tree | PASS | Installed top-level versions match the lock: `@wordpress/env` 11.12.0 and `@wordpress/scripts` 34.0.0. |
| Production dependency audit | PASS | `npm.cmd audit --omit=dev` reported 0 vulnerabilities. |

### Release packaging and CI

| Check | Result | Notes |
| --- | --- | --- |
| `.distignore` development exclusions | PASS | Includes `/.wp-env.json`, `/composer.lock`, `/scripts/`, `/assets/src/`, `/tests/`, docs, dependency folders/config, and source maps. |
| Simulated packaged file set | PASS | Only bootstrap/lifecycle PHP, compiled assets, `readme.txt`, and `LICENSE` remain. |
| Credential scan | PASS | No OpenAI/SeaAI key assignment, bearer secret, known token prefix, private-key block, `.env`, key, PEM, or auth file was found in first-party non-document files. |
| Quality workflow | PASS | PHP 7.4/8.3 matrix runs Composer validation, install, lint, PHPStan, PHPUnit, and runtime dependency smoke; JS job runs npm clean install, lint, and build. |
| Release workflow | PASS | Builds assets, creates the ZIP, audits extracted contents, and uploads only an audited artifact. |
| Local ZIP creation | DEFERRED | Windows has no usable Bash/rsync/zip toolchain. The deny rules and resulting membership were verified locally; the Linux release workflow owns the byte-for-byte ZIP test. |
| `wp-env start` | DEFERRED | Docker is not installed. This does not block G1 because both dependency states and activation/deactivation were exercised against the local WordPress 7.0.2 installation. |

## G1 decision

**PASS. M1 is accepted and M2 may start.**

The two DEFERRED rows are environment-specific duplicate paths, not missing product behavior: local WordPress performed the runtime gate, and the release workflow performs the exact Bash/rsync/zip package gate. No credential, lifecycle, fatal-error, lint, static-analysis, unit-test, syntax, build, or source-map blocker remains.
