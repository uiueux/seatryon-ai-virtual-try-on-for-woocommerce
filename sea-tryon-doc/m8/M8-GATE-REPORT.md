# M8 Packaging Report

- Date: 2026-08-09
- Milestone: M8 — Documentation and release packaging
- Packaging deliverables: **PASS**
- Full G8 release gate: **BLOCKED** by G7

## Prepared artifacts

- Merchant setup, privacy/data-flow, and operations/rollback guides.
- Updated `README.md`, WordPress.org-style `readme.txt`, and `CHANGELOG.md`.
- Translation catalogs are intentionally not bundled with the open-source release.
- Linux CI/Bash build path and a deterministic Windows PowerShell build path.
- Audited `dist/sea-tryon-0.1.0.zip` plus SHA-256 sidecar.
- Production archive excludes tests, fixtures, internal documents, development source, source maps, Composer/npm dependencies, CI configuration, caches, and likely credential files.
- Two consecutive builds produced the same SHA-256: `1944acea44e8f3959c561365ec19fc782ed321e6ba3da37d8ceca4f720a25653`.
- The extracted package passed all-file PHP 7.4/8.3 syntax checks and missing/supported-WooCommerce runtime smokes without `vendor`.

## Release decision

The package version remains `0.1.0` and is labeled a validation build. It is not promoted to `1.0.0` because G7 is not complete. A final release manager must rerun all gates after supplying the external provider and compatibility matrix, update version/stable tags, regenerate the POT/package/checksum, perform a clean install/upgrade/uninstall smoke on an isolated site, and sign off the privacy/provider disclosures.

**Final G8 status: BLOCKED pending G7.**
