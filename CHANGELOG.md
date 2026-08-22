# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Validation pending

- Real WordPress AI Connector and SeaAI person/scene staging generations.
- WooCommerce 11.x, QIT, and the remaining release compatibility matrix.

## [1.1.0] - 2026-08-19

- Replaced the direct OpenAI image-edit API with the provider-agnostic WordPress 7.0 AI Client.
- Moved provider selection and credentials to WordPress Settings > Connectors.
- Removed plugin-level OpenAI key and quality settings while retaining the existing stored provider identifier for upgrade compatibility.
- Added connector capability checks, WordPress AI error normalization, and private output validation.

## [1.0.0] - 2026-08-10

### Added

- First public SeaTryon release.

### Fixed

- Loaded WordPress's file API on demand during public REST image processing so generation no longer fails before a job is created when `wp_tempnam()` was not preloaded.
- Allowed a SeaAI HTTP loopback gateway to be saved on a loopback WordPress development site, while continuing to require HTTPS for remote production gateways.
- Declared High-Performance Order Storage compatibility through WooCommerce's public `FeaturesUtil` API so the plugin is no longer classified as HPOS-incompatible.

## [0.1.0] - 2026-08-09

### Added

- Initial activatable plugin architecture and lifecycle foundation.
- PHP and JavaScript quality tooling.
- CI matrices for PHP 7.4 and 8.3 plus JavaScript lint and production builds.
- WordPress.org-style release metadata and audited ZIP packaging.
- WooCommerce settings, product-level experience controls, configuration notices, and aggregate statistics.
- OpenAI GPT Image 2 and SeaAI Universal X synchronous adapters with strict response validation and SSRF protection.
- Private upload/result storage, asynchronous Action Scheduler jobs, bounded retries, TTL cleanup, and privacy exporter/eraser integration.
- Separate guest and logged-in quotas with action-bound guest tokens, replay protection, ownership checks, and authenticated result streaming.
- Accessible product-page modal, responsive/RTL/reduced-motion styles, REST polling, variation invalidation, result download, retry, and delete flows.
