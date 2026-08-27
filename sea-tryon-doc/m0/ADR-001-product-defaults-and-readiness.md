# ADR-001: MVP Defaults and Provider Credential Readiness

- Status: Accepted for MVP
- Date: 2026-08-08
- Decision owner: Product owner
- Implementation milestone: M1-M6
- Final review deadline: before G7 release-candidate QA

## Context

The requirements contained a small set of product defaults that were intentionally left open. M0 must remove implementation ambiguity without requiring production credentials to be stored in this repository.

## Decision

The following defaults are frozen for the first implementation:

| Area | MVP decision |
| --- | --- |
| Guest access | Disabled by default. A merchant must explicitly allow anonymous generation. |
| Administrator quota | Users with the `manage_options` capability are unlimited. |
| Logged-in quota | 3 dispatched generations per non-administrator user per site-local calendar day. |
| Guest quota | 3 dispatched generations per anonymous session per site-local calendar day. |
| Quota charge point | Charge only after a request is actually dispatched to the selected provider. |
| Provider | Exactly one active provider: OpenAI or Third-party API (SeaAI). |
| OpenAI model | `gpt-image-2`; Image Edit with the user image and product image. |
| OpenAI defaults | `n=1`, `size=auto`, `quality=low`, `background=auto`, `output_format=png`. |
| SeaAI model | `universal_x`. |
| SeaAI defaults | `n=1`, `quality=low`, `background=auto`, `output_format=png`, target resolution `auto`. |
| Result count | One generated result per request. |
| Delivery | Preview and owner-authorized download; never create a WordPress attachment or Media Library item. |
| Local retention | Maximum 24 hours, with eager deletion after delivery when no longer needed and on failure/cancellation. |
| Product default | Virtual Try-On disabled per product until the merchant enables it. |
| Experience type | `Auto`, with explicit Clothing, Hats, Shoes, Earrings, Rings, Necklaces, Bracelets, Nose Rings, Belly Button Rings, Hair Accessories, Anklets, Brooches & Pins, Lip Rings, Tongue Rings, Body Chains, Glasses, Wig, Furniture and Product Placement modes. |
| User-facing language | English source strings, fully translatable through WordPress i18n. |
| Compatibility baseline | WordPress 6.9+, WooCommerce 10.9+ (including WC 11.x), PHP 7.4+. |

These values remain merchant-configurable where the requirements expose a setting. Changing a default later must not overwrite an existing merchant's saved value.

## Credential readiness

No OpenAI or SeaAI secret is present in the workspace environment as of 2026-08-08. M0 therefore uses documented contracts and deterministic mock fixtures; it does not make a paid or authenticated provider call.

| Provider | Required test material | Owner | Required by | Current state |
| --- | --- | --- | --- | --- |
| OpenAI | Restricted development API key with access to `gpt-image-2` | Product owner | Before M4 real-provider acceptance | Not supplied |
| SeaAI | Development gateway base URL and API key | Product owner / SeaAI owner | Before M4 real-provider acceptance | Not supplied |

Credentials must be delivered through environment variables, WordPress constants, or an approved secret channel. They must never be added to fixtures, documentation, source control, logs, screenshots, or support exports.

The absence of credentials does not block the mock-first G0 architecture gate. It does block claiming real-provider connectivity and the G4 provider acceptance gate.

## Consequences

- M1-M3 can proceed against stable defaults and provider interfaces.
- Provider adapters must accept injected HTTP clients so the M0 fixtures can be replayed without network access.
- Settings validation must enforce provider mutual exclusivity.
- Release QA may revise a default only through a new ADR and migration-safe behavior.
