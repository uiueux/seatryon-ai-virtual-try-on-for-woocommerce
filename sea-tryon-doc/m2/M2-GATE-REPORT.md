# M2 Gate Report

- Milestone: M2 — Core Contracts and Services
- Completed: 2026-08-09
- Gate: **G2 PASS**
- Next milestone: M3 — WooCommerce Settings and Product Fields

## Delivered

- Provider interfaces and validated request/result/error DTOs.
- Seven experience modes and controlled English prompts for people, apparel, accessories, glasses, wigs, furniture and general product placement.
- A PHP 7.4-compatible job state machine, 128-bit CSPRNG IDs, owner-scoped idempotency and atomic repository contract.
- Typed settings defaults, mutually exclusive provider secret access, constant/filter overrides and fixed-length masking.
- Recursive log redaction for credentials, cookies, sessions, signatures, result URLs, image payloads and IPv4/IPv6 addresses.
- Private site-isolated temporary storage with public-root fail-closed behavior, path containment, symlink safety and 24-hour maximum TTL.
- Logged-in/guest quota identities, site-timezone day buckets, dispatch-id idempotency and bounded limits.
- Atomic locks using option-value CAS for database storage and backend TTL-only semantics for persistent object caches.
- A safe WooCommerce logger wrapper.

## Independent verification

- PHP 7.4 and PHP 8.3 lint: 67 files PASS.
- PHPCS/WPCS/PHPCompatibility: 45 source files PASS.
- PHPStan level 6: 45 source files, zero errors.
- PHPUnit on both supported PHP branches: 98 tests / 271 assertions PASS.
- All 43 source files have direct-access guards.
- Original QA reproductions for lock TOCTOU, public DocumentRoot escape, scope symlink handling, sensitive log fields, unsafe provider request IDs and insecure SeaAI URLs are closed.

Full evidence: [M2-QA-REPORT.md](M2-QA-REPORT.md).

## Deferred to later gates

- Live OpenAI/SeaAI calls: G4.
- Real Action Scheduler and multi-process database/cache integration: G5/G7.
- Unix-native filesystem and symlink matrix: G7.

**Decision:** G2 passes and M3 may start.

