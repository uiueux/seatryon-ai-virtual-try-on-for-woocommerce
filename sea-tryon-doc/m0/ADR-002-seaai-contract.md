# ADR-002: SeaAI Universal X contract and synchronous-only policy

- Status: Accepted for M0
- Date: 2026-08-08
- Scope: `Third-party API` provider only
- Decision owner: Sea Try-On project

## Decision

Sea Try-On will integrate SeaAI as a custom WordPress gateway, not as an
OpenAI-compatible endpoint. The provider adapter will use these endpoints below
the configured API root:

- `POST /forward/image/upload`
- `POST /forward/image/generate`

For `model_name=universal_x`, M0 adopts a **synchronous-only** contract. A
generation is successful only when a 2xx JSON object contains a non-empty
`images` array. An unexpected `task_id` is preserved in internal diagnostic
metadata, but the job fails with a normalized `provider_contract_error`; Woo
Try-On must not guess a polling request or call `/forward/image/query` for this
provider.

This restriction is deliberate. The locally installed gateway exposes a query
route, but it always delegates to the legacy RunningHub query implementation and
uses the legacy e-commerce key. The Universal X implementation calls the
Highway GPT Image 2 Edit endpoint synchronously and rejects upstream responses
that do not contain `images`. Therefore the existing query route is not evidence
of an asynchronous Universal X protocol.

## Evidence reviewed

The decision is based on versioned SeaAI Universal X contract notes and
implementation fixtures available to the project at the ADR date. Local source
paths and file fingerprints are intentionally omitted from the public record.

| Evidence | Relevant evidence |
|---|---|
| SeaAI Universal X contract notes | Fixed `universal_x`, accepted options, synchronous `images`; an unexpected `task_id` must be preserved. |
| SeaAI implementation fixtures | Multipart upload, JSON generation request, response parsing; no query implementation. |

Local gateway details can change independently of Sea Try-On. Before enabling
an async path in a later release, its owner must provide a versioned Universal X
query contract and integration fixtures.

## Configuration contract

The administrator selects the Third-party provider and configures:

- API root URL, defaulting to `https://theminitech.net/wp-json/seaai/v1` and remaining overridable;
- a user-created SeaAI key beginning with `sk-`.

The upstream Universal X/Highway credential belongs to the gateway and must
never be requested, stored, logged, or sent to the browser by Sea Try-On. The
adapter sends the SeaAI key server-side with `Authorization: Bearer <key>`.
Although the inspected gateway also accepts `X-API-Key`, Sea Try-On standardizes
on the Bearer header.

The API root must be an absolute `https` URL in production. A development-only
filter may permit `http://localhost` or a private test host; arbitrary cleartext
remote URLs are rejected.

## Upload contract

Request:

```http
POST {api_root}/forward/image/upload
Authorization: Bearer <redacted>
Accept: application/json
Content-Type: multipart/form-data; boundary=...

file=<binary image>
```

Required response:

```json
{
  "download_url": "https://gateway.example.test/path/reference.jpg",
  "file_name": "reference.jpg"
}
```

`download_url` is required and must be a valid HTTPS URL (subject to the local
development exception). `file_name` is optional. The plugin uploads the customer
image when needed; already public, validated product image URLs may be passed
directly to generation. Upload and generated results are not WordPress media
attachments.

## Generate contract

Request:

```http
POST {api_root}/forward/image/generate
Authorization: Bearer <redacted>
Accept: application/json
Content-Type: application/json
```

Canonical request body:

```json
{
  "model_name": "universal_x",
  "image_urls": [
    "https://gateway.example.test/reference/customer.jpg",
    "https://shop.example.test/product/product.png"
  ],
  "prompt": "Create a realistic virtual try-on while preserving identity and product details.",
  "resolution": "auto",
  "size": "auto",
  "n": 1,
  "quality": "low",
  "background": "auto",
  "output_format": "png"
}
```

Rules:

- `model_name` is always `universal_x` and is not administrator-editable.
- `prompt` must be non-empty after trimming.
- `image_urls` must contain at least one validated URL; normal try-on requests
  contain the customer/scene image and the product image.
- `n` is `1` for the MVP.
- Sea Try-On's default `quality` is `low`. The reference CLI currently defaults
  to `high`; that CLI default does not override the product requirement.
- Supported qualities are `low`, `medium`, and `high`.
- Supported backgrounds are `auto` and `opaque`.
- Supported formats are `png` and `jpeg`.
- Supported preferred sizes include `1024x1024`, `1536x1024`, `1024x1536`,
  `2048x2048`, and `auto`. The adapter must use an allow-list rather than pass
  arbitrary values.
- Sea Try-On defaults both `resolution` and `size` to `auto`.
- `mask` is optional and omitted when empty.

Required successful response:

```json
{
  "images": [
    { "url": "https://gateway.example.test/generated/result.png" }
  ],
  "provider": "universal_x",
  "points_cost": 2,
  "points_balance": 98
}
```

Each `images` entry may be an HTTPS URL string or an object with an HTTPS `url`.
At least one valid result URL is required. `provider`, `points_cost`, and
`points_balance` are informative and must not be used as proof of success.

## Query investigation

The installed gateway's route contract is discoverable:

```http
POST {api_root}/forward/image/query
Authorization: Bearer <redacted>
Content-Type: application/json

{"task_id":"provider-task-id"}
```

It returns the decoded RunningHub response without a stable normalized schema.
Internally it converts `task_id` to the upstream field `taskId`. Missing input is
HTTP 400, missing legacy provider configuration is HTTP 503, and upstream/query
errors are HTTP 502.

This route is **out of scope for Universal X** because:

1. it selects the legacy e-commerce API key, not the Universal X key;
2. it calls the RunningHub `/openapi/v2/query` API;
3. the Universal X generator accepts only a synchronous `images` response;
4. the supplied client contains no polling implementation.

Consequently, a `task_id` response fixture is a contract-drift test, not a happy
path. Enabling polling requires a new ADR defining endpoint ownership, request
fields, terminal statuses, result shape, intervals, timeout, and point charging.

## Error normalization and retry

| Gateway outcome | Plugin error code | Automatic retry |
|---|---|---|
| HTTP 400 | `provider_invalid_request` | No |
| HTTP 401 | `provider_auth_missing` | No |
| HTTP 403 | `provider_auth_rejected` or `provider_rate_limited` | No; preserve the safe gateway message to disambiguate |
| HTTP 402 | `provider_insufficient_balance` | No |
| HTTP 502 | `provider_upstream_failure` | Only bounded retry when retry policy permits |
| HTTP 503 | `provider_unavailable` | Only bounded retry when retry policy permits |
| Network timeout/connection failure | `provider_network_failure` | Only bounded retry when retry policy permits |
| 2xx without valid `images`, including `task_id` | `provider_contract_error` | No |
| Invalid JSON | `provider_invalid_response` | No |

Do not retry 400, 401, 402, or 403 automatically. Transient 502, 503, and network
failures may use bounded exponential backoff through the shared job policy. API
keys, full request headers, customer image bytes, and unrestricted upstream
response bodies must not appear in logs.

Default user-facing messages are English. Raw provider errors may be recorded
only after secret removal and length limiting; they are not displayed directly
to shoppers.

## Mock acceptance criteria

The fixtures in `fixtures/` cover:

- successful multipart upload;
- successful synchronous generation;
- mixed supported image entry shapes;
- the unexpected `task_id` fail-safe;
- representative permanent and transient HTTP errors;
- the discovered legacy query request solely as a non-Universal-X evidence case.

Adapter contract tests must assert that no query request is emitted after
`seaai-generate-task-unexpected.json`.

## Consequences

This decision gives M1-M4 a deterministic adapter surface and avoids routing a
Universal X identifier to an unrelated provider. It does not block a future
async implementation; it requires that implementation to arrive with evidence
and tests rather than inferred fields.