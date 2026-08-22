# M1 Gate Report

- Milestone: M1 — Plugin Skeleton and Toolchain
- Completed: 2026-08-09
- Gate: **G1 PASS**
- Next milestone: M2 — Core Contracts and Services

## Delivered

- A single PHP bootstrap with WordPress/WooCommerce metadata, constants and PSR-4 loading.
- WooCommerce 10.9+ dependency guard with an English, translatable and escaped admin notice.
- Safe activation, deactivation and non-destructive uninstall skeletons.
- Composer-locked PHPCS/WPCS, PHPCompatibility, PHPStan level 6 and PHPUnit tooling.
- npm-locked `@wordpress/scripts` build/lint/test tooling with separate frontend/admin sources and compiled LTR/RTL assets.
- WordPress/WooCommerce wp-env definition, GitHub quality/release workflows, release documentation and audited distribution rules.

## Verification summary

- Project triage: `wp-plugin`; one detected plugin entrypoint.
- PHP 7.4.16 and 8.3.1 syntax: PASS.
- PHPCS/WPCS: PASS.
- PHPStan level 6: PASS, zero errors.
- PHPUnit: PASS, 2 tests / 2 assertions.
- Dependency runtime smoke: missing, old and supported WooCommerce states PASS.
- Real WordPress 7.0.2 activation matrix: WooCommerce missing and WooCommerce 10.9.4 active PASS; original `active_plugins` state restored.
- npm engine check, JavaScript/CSS lint, build and test: PASS.
- Compiled assets contain no source maps.
- Distribution membership and secret scan: PASS after correcting `.distignore` source/tool exclusions.

Full independent evidence: [M1-QA-REPORT.md](M1-QA-REPORT.md).

## Non-blocking environment notes

- Docker is unavailable, so wp-env was not started; the installed WordPress site supplied the runtime gate.
- The local Windows host lacks the Bash/rsync/zip combination used by the release workflow; distribution membership was dry-run locally and the exact ZIP path remains enforced in Ubuntu CI.
- MAMP emits extension-loading warnings in some CLI configurations. These are environment warnings and did not affect the completed quality gates.

**Decision:** G1 passes and M2 may start.

