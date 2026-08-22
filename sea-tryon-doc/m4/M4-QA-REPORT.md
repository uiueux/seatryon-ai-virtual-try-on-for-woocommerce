# M4 Independent QA Report

- Date: 2026-08-09
- Milestone: M4 — Provider adapters
- Gate: G4 — Provider contract
- Adapter code-contract verdict: **PASS**
- Full G4 verdict: **BLOCKED / DEFERRED**
- Open M4 adapter defects: **0**
- External blocker: no real OpenAI and SeaAI test credentials or staging endpoints were supplied

## 1. Scope and baseline

Independent QA reviewed `src/Http/**`, `src/Image/**`,
`src/Provider/OpenAI/**`, `src/Provider/SeaAI/**`, their PHP tests, and all
committed M0 provider fixtures. Acceptance was checked against:

- `sea-tryon-doc/REQUIREMENTS.md`, especially API-001 through API-013;
- the M4/G4 criteria in `sea-tryon-doc/DEVELOPMENT_PLAN.md`;
- `sea-tryon-doc/m0/openai-contract.md`;
- `sea-tryon-doc/m0/ADR-002-seaai-contract.md`;
- external SeaAI Universal X contract notes (local path intentionally omitted).

The conclusion does not rely on developer completion statements. Request
bodies, fixtures, error matrices and SSRF cases were replayed independently.

## 2. Finding raised and closed

| ID | Severity | Finding | Resolution and independent re-test | Final status |
| --- | --- | --- | --- | --- |
| QA-M4-01 | P1 security | `UrlSafetyPolicy` relied on PHP `FILTER_FLAG_NO_PRIV_RANGE` / `FILTER_FLAG_NO_RES_RANGE`, which accepted IPv4-mapped IPv6 loopback/private addresses, Alibaba metadata/CGNAT, benchmark and multicast ranges. The first patch also accepted IPv4-compatible IPv6 and deprecated site-local ranges. | The policy now applies explicit binary CIDR classification before the PHP public-IP flags. Independent replay rejected `::ffff:127.0.0.1`, `::ffff:10.0.0.1`, `::127.0.0.1`, `::10.0.0.1`, `100.100.100.200`, `198.18.0.1`, `224.0.0.1`, `fec0::1`, `3fff::1`, `5f00::1` and `192.88.99.1`, while public IPv4 and IPv6 controls remained accepted. | CLOSED |

The remote downloader still uses `wp_safe_remote_request()` through the shared
client, with unsafe URL rejection and redirect revalidation enabled. The local
HTTP exception remains limited to an explicit boolean plus a real
`local`/`development` environment and literal loopback host; production cannot
be weakened by the boolean.

## 3. Automated verification

| Check | Result | Evidence |
| --- | --- | --- |
| PHP 7.4 syntax | PASS | 20 M4 production files, zero failures. |
| PHP 8.3 syntax | PASS | The same 20 files, zero failures. |
| PHPCS / WPCS / PHPCompatibility | PASS | 20 M4 production files, zero errors. |
| PHPStan level 6 | PASS | M4 production scope, zero errors with 1 GB limit. |
| HTTP PHPUnit, PHP 7.4 / 8.3 | PASS | 16 tests, 49 assertions on each version. |
| Image PHPUnit, PHP 7.4 / 8.3 | PASS | 33 tests, 46 assertions on each version. |
| Provider PHPUnit, PHP 7.4 / 8.3 | PASS | 48 tests, 250 assertions on each version. |
| Focused SSRF regression, PHP 7.4 / 8.3 | PASS | 29 tests, 29 assertions on each version. |
| M0 fixture parsing | PASS | 11 JSON fixtures parsed, zero failures. |

The PHP 8.3 MAMP CLI emits a startup warning for its locally missing MySQLi
DLL. The warning occurs before plugin execution and did not affect these tests.

## 4. Contract matrix

| Area | Result | Independent assessment |
| --- | --- | --- |
| OpenAI request | PASS | Exact `POST https://api.openai.com/v1/images/edits`; fixed `gpt-image-2`; exactly two ordered `image[]` parts (customer/scene, then product); fixed `n=1`, `size=auto`, `output_format=png`, `background=auto`; allowlisted quality. |
| Forbidden OpenAI fields | PASS | No `input_fidelity`, `mask`, `stream`, `partial_images`, arbitrary model or extra MVP parameters. Multipart boundaries are random and filenames/headers reject injection. |
| OpenAI success | PASS | Bounded HTTP response; base64 length checked before decode and decoded size checked after; strict base64; actual decodable PNG required; result is written under the original private scope and returned only as a storage reference. No attachment/Media Library API is called. |
| OpenAI errors | PASS | User error, moderation, invalid request, 401, 403, quota-specific 429, transient 429 with bounded `Retry-After`, 408/timeout, 5xx, network failure and malformed success map to stable, safe errors with the required retry eligibility. Raw body and messages are not surfaced. |
| SeaAI request | PASS | Exactly two ordered `/forward/image/upload` calls, followed by one synchronous `/forward/image/generate`; fixed `model_name=universal_x`, two ordered `image_urls`, `n=1`, default quality normalization to `low`, auto background and PNG output. |
| SeaAI synchronous response | PASS | String and object image entries are accepted; empty/malformed shapes fail. Any `task_id`, including one accompanied by `images`, fails as `provider_contract_error`; no `/forward/image/query` request is emitted. |
| SeaAI result delivery | PASS | Upload/result URLs pass HTTPS, port, hostname, DNS and explicit IPv4/IPv6 CIDR checks. Download status, byte cap, Content-Type and decoded image are checked before same-scope private storage. No public URL or attachment ID is returned. |
| SeaAI errors | PASS | 400/401/402/403 are permanent; safe code/type fields disambiguate 403 rate limiting without reflecting messages; 502/503 and network failures are retry-eligible; malformed JSON and contract drift are permanent. |
| HTTP boundary | PASS | Uses WordPress safe HTTP, response byte limits, bounded timeouts and redirects, response-size recheck, CRLF rejection, safe request-ID allowlist and bounded `Retry-After`; request bodies are never placed in exceptions. |
| Confidentiality | PASS | Provider exceptions contain fixed safe messages. Authorization, multipart bytes, base64, result/download URLs and filesystem paths are not copied into adapter results or exceptions. M4 code has no logging call which could serialize a request/response body. |

## 5. G4 decision and deferred integration obligations

The M4 **adapter code contract passes** after closure of QA-M4-01. This means
the deterministic request, response, error, storage and SSRF behavior is
acceptable for consumption by the M5 worker.

The complete G4 gate **does not pass yet** because its staging criterion
requires, for both OpenAI and SeaAI, one real person-mode generation and one
real scene-mode generation. No real credentials/base URL were available, so
mock tests cannot be used to claim connectivity, account access, billing or
actual generated-image quality. This is an external **BLOCKED / DEFERRED** item.

The following wiring items remain assigned to M5 and must be verified before
G5/G7 acceptance:

1. The provider factory/worker must read and construct only the selected
   provider. A spy test must prove the inactive provider key getter and endpoint
   are never touched.
2. Provider retry flags must be converted into queue backoff without sleeping:
   initial request plus at most two transient retries, but at most one guarded
   retry for OpenAI ambiguous timeout/malformed-success cases. Retries must not
   consume quota again.
3. ADR-001's provider-specific size default must be preserved by wiring:
   OpenAI `auto`, SeaAI target `1024x1024`.
4. The safe, allowlisted unexpected SeaAI `task_id` should be retained only in
   internal diagnostics as required by ADR-002/its fixture, while remaining
   absent from shopper responses and while still producing zero query calls.
5. Production wiring must use `WordPressHttpClient`; an arbitrary injected test
   client is not a permitted production transport.

**Final M4 QA status:** adapter implementation **PASS**; full live G4
**BLOCKED / DEFERRED pending credentials and staging evidence**.