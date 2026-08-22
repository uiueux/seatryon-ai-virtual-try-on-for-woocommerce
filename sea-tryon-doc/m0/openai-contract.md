# M0 OpenAI GPT Image 2 Contract

Status: Accepted for mock-first implementation  
Contract version: 1.0  
Verified against official OpenAI documentation: 2026-08-08  
Scope: Sea Try-On MVP, OpenAI provider only

## 1. Decision

Sea Try-On will use the OpenAI Image API edit endpoint for one-shot virtual try-on and scene composition:

- Endpoint: `POST https://api.openai.com/v1/images/edits`
- Model: exactly `gpt-image-2`
- Transport: `multipart/form-data`
- Inputs: exactly two repeated `image[]` file parts
- Output count: `n=1`
- Response mode: non-streaming JSON containing base64 image data
- Result handling: private temporary job storage only; never create a WordPress attachment and never add the result to the Media Library

The first input is the user's person/room/scene image and the second input is the WooCommerce product image. The prompt must describe those roles explicitly. This stable ordering also leaves the first image as the primary edit canvas if a mask is added in a future version. MVP does not send a mask.

The Image API is preferred over the Responses API because the MVP is a single prompt/single result operation rather than a conversational, multi-turn edit flow.

## 2. Official basis

The official sources establish that:

- `gpt-image-2` supports image input/output and the `/v1/images/edits` endpoint.
- The edits endpoint can generate a new image from one or more reference images.
- Multiple binary inputs are sent as repeated `image[]` multipart parts.
- GPT Image responses return base64 image data in `data[].b64_json` by default.
- `gpt-image-2` processes every image input at high fidelity automatically, so `input_fidelity` must be omitted.
- The supported quality values are `auto`, `low`, `medium`, and `high`; the plugin default is `auto`.
- `gpt-image-2` does not support transparent backgrounds.

Sources:

- [GPT Image 2 model](https://developers.openai.com/api/docs/models/gpt-image-2)
- [Image generation and editing guide](https://developers.openai.com/api/docs/guides/image-generation)
- [Create image edit API reference](https://developers.openai.com/api/reference/resources/images/methods/edit)
- [API error codes](https://developers.openai.com/api/docs/guides/error-codes)

Note: on the verification date, the generated model enum in the image-edit API reference lagged the model catalog and image guide and did not list `gpt-image-2`. The model page and guide explicitly identify `gpt-image-2` as supporting `/v1/images/edits`, so this contract preserves the required model without substituting another model.

## 3. Request contract

### 3.1 Headers

| Header | Value | Rule |
|---|---|---|
| `Authorization` | `Bearer {OpenAI API Key}` | Server-side only. Never send to the browser or write to logs. |
| `Content-Type` | `multipart/form-data; boundary={random boundary}` | The HTTP client/multipart encoder must supply the boundary. |
| `Accept` | `application/json` | Non-streaming response. |

The implementation should capture the `x-request-id` response header for redacted diagnostics when present. It must not log request bodies, image bytes, base64 output, the authorization header, or result URLs.

### 3.2 Multipart fields

| Field | Cardinality | MVP value | Validation |
|---|---:|---|---|
| `model` | 1 | `gpt-image-2` | Fixed constant; not merchant-editable. |
| `prompt` | 1 | Experience-specific English prompt | Non-empty; composed by the server from an approved template and sanitized product instructions. |
| `image[]` | 2 | Part 1: user/scene image; part 2: product image | Binary file parts with safe generated filenames and detected MIME types. |
| `n` | 1 | `1` | Fixed constant. |
| `size` | 1 | `auto` | Fixed for MVP. |
| `quality` | 1 | `auto` by default | Whitelist: `auto`, `low`, `medium`, `high`. |
| `output_format` | 1 | `png` | Fixed for MVP. |
| `background` | 1 | `auto` | Fixed for MVP; do not request `transparent`. |

Do not send `input_fidelity`: the official guide says `gpt-image-2` always processes image inputs at high fidelity and does not allow that value to be changed. Do not send `mask`, `stream`, `partial_images`, arbitrary model names, or arbitrary extra parameters in MVP.

### 3.3 Wire-shape example

This is an illustrative wire contract, not a command for production logs:

```http
POST /v1/images/edits HTTP/1.1
Host: api.openai.com
Authorization: Bearer [REDACTED]
Content-Type: multipart/form-data; boundary=sea_tryon_boundary
Accept: application/json

--sea_tryon_boundary
Content-Disposition: form-data; name="model"

gpt-image-2
--sea_tryon_boundary
Content-Disposition: form-data; name="prompt"

Use image 1 as the person's photo and image 2 as the product reference. Create a photorealistic virtual try-on while preserving identity and product details.
--sea_tryon_boundary
Content-Disposition: form-data; name="image[]"; filename="subject.png"
Content-Type: image/png

[BINARY USER IMAGE]
--sea_tryon_boundary
Content-Disposition: form-data; name="image[]"; filename="product.jpg"
Content-Type: image/jpeg

[BINARY PRODUCT IMAGE]
--sea_tryon_boundary
Content-Disposition: form-data; name="n"

1
--sea_tryon_boundary
Content-Disposition: form-data; name="size"

auto
--sea_tryon_boundary
Content-Disposition: form-data; name="quality"

auto
--sea_tryon_boundary
Content-Disposition: form-data; name="output_format"

png
--sea_tryon_boundary
Content-Disposition: form-data; name="background"

auto
--sea_tryon_boundary--
```

The PHP adapter should build this request through the WordPress HTTP API. If it manually encodes multipart data, the boundary must be unpredictable per request, filenames must not contain caller-controlled header characters, and the assembled body must never be logged.

## 4. Success response contract

Expected HTTP status: `200`.

Required adapter path:

```text
body.data[0].b64_json
```

The response may also contain `created`, `background`, `output_format`, `quality`, `size`, and `usage`. The adapter must tolerate additional fields. It must not require `revised_prompt`, which the API reference documents for DALL-E 3 rather than GPT Image.

Before a job becomes `completed`, the adapter must:

1. Confirm a 2xx HTTP status and a valid JSON object.
2. Confirm `data` is a non-empty array and `data[0].b64_json` is a non-empty string.
3. Strictly base64-decode the value.
4. Enforce the plugin's decoded response-size cap before persisting it.
5. Decode/inspect the image and confirm that it is a supported image with MIME `image/png` for the MVP contract.
6. Write the bytes to the job's private temporary result store using a server-generated name.
7. Return only the plugin's authorized result handle to the browser.

It must not call `media_handle_sideload()`, `wp_insert_attachment()`, or any other Media Library registration path.

Fixture: `fixtures/openai-success.json`.

## 5. Error normalization

Provider error bodies are expected to use an `error` object. The adapter must treat `error.code` as the stable provider discriminator when available, while retaining only a sanitized provider message for diagnostics.

| HTTP/provider condition | Stable plugin code | Retry | UI category |
|---|---|---|---|
| `400` with `error.type=image_generation_user_error` | `openai_image_user_error` | No; prompt or image must change | User-correctable generation failure |
| Other `400`/`422` invalid request | `openai_invalid_request` | No | Configuration or invalid input |
| `401` | `openai_authentication_failed` | No | Merchant configuration error |
| `403` | `openai_access_denied` | No | Merchant/account/region error |
| `429` with credit/spend/usage-limit code | `openai_quota_exhausted` | No automatic retry | Merchant quota error |
| Other `429` | `openai_rate_limited` | Yes, honoring `Retry-After` | Temporary provider load |
| `500`–`599` | `openai_service_unavailable` | Yes | Temporary provider failure |
| Network connect failure | `openai_network_error` | Yes | Temporary network failure |
| Ambiguous read timeout after submission | `openai_timeout` | At most one guarded retry | Temporary/unknown completion |
| 2xx malformed JSON/base64/image | `openai_invalid_response` | At most one guarded retry | Provider response failure |

The plugin must never show the raw provider response to a shopper. Debug logging may store the stable plugin code, HTTP status, attempt number, latency, and sanitized `x-request-id`; it must not store images/base64 or secrets.

## 6. Retry contract

- Do not retry `image_generation_user_error`, moderation rejection, invalid input, authentication/access errors, or exhausted credit/spend/usage limits.
- Retry only transient `429`, `5xx`, network-connect failures, and the guarded ambiguous cases listed above.
- Honor a valid `Retry-After` header. Otherwise use bounded exponential backoff with jitter through the job queue rather than sleeping in a frontend request.
- Maximum transient attempts: initial request plus two retries. Ambiguous read timeout or malformed-success response receives at most one retry because a previous generation may already have consumed provider resources.
- Every attempt belongs to the same internal job and is protected by a processing lock. Shopper usage is atomically counted once when the initial provider request is actually dispatched; retries for the same job must not increment it again.
- Exhaustion of retries ends in a stable failed state; it must not silently switch to SeaAI because providers are mutually exclusive.

The official image guide says retries are appropriate for transient `429` and `5xx` errors but not for image-generation user errors. The official error guide also requires following `Retry-After` when it is present.

## 7. Mock fixture envelope

Fixtures use a test-harness envelope so contract tests can reproduce status, headers, and body without a live API:

```json
{
  "fixture_version": 1,
  "provider": "openai",
  "request": {
    "method": "POST",
    "path": "/v1/images/edits"
  },
  "response": {
    "status": 200,
    "headers": {},
    "body": {}
  },
  "expect": {}
}
```

`response.body` is the raw decoded JSON object the provider adapter receives. Placeholder identifiers and messages are synthetic; fixtures contain no real API key, customer image, product image, or generated customer result.

## 8. Contract test assertions

M4 contract tests should prove at minimum:

1. The request uses `POST /v1/images/edits`, fixed `gpt-image-2`, and exactly two ordered `image[]` parts.
2. `n=1`, `size=auto`, `output_format=png`, `background=auto`, and whitelisted quality are sent.
3. `input_fidelity`, `mask`, `stream`, and provider-incompatible fields are absent.
4. The success fixture decodes to a valid PNG and yields one private result without an attachment ID.
5. The image user-error fixture maps to `openai_image_user_error` and is not retried.
6. The rate-limit fixture maps to `openai_rate_limited`, reads `Retry-After`, and schedules a bounded retry.
7. The server-error fixture maps to `openai_service_unavailable` and schedules a bounded retry.
8. Missing/empty `data`, invalid base64, oversized bytes, invalid image bytes, non-JSON bodies, timeouts, `401`, `403`, and quota-specific `429` values are covered by additional data-provider cases during M4.
9. Logs and REST responses contain no `Authorization` value, multipart body, customer bytes, product bytes, or base64 result.

## 9. Deferred live verification

This contract is sufficient to pass the M0 mock-first gate. A live smoke test remains dependent on a test key whose organization/project can access `gpt-image-2`; official documentation notes that organization verification may be required. Live verification belongs to the provider integration gate and must be performed with non-customer test images and redacted logs.
