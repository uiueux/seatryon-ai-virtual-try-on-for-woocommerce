# M6 Gate Report

- Date: 2026-08-09
- Milestone: M6 — Product page and shopper experience
- Gate: **G6 PASS**

## Delivered

- Server-rendered `Virtual Try-On` button with fail-closed product/provider visibility rules.
- Automatic public WooCommerce hook placement plus a dynamic `sea-tryon/virtual-try-on` block fallback without duplicate rendering.
- One form-independent modal per product page with focus management, Escape handling, focus trapping, labels, live status, associated errors, and native controls.
- Person-mode and furniture/product-scene upload guidance, JPEG/PNG/WebP client preflight, preview, filename, consent, removal, and variation invalidation.
- Asynchronous REST create/poll/result/delete client for logged-in and guest sessions.
- Two-second polling start with bounded exponential backoff, five-minute visible-time budget, and network polling paused while the page is hidden.
- Private authenticated result fetch, local blob display/download, retry, delete, and Try Again behavior that keeps the previous result until replacement succeeds.
- Responsive/RTL/reduced-motion styling and conditional asset loading only on eligible single-product requests.
- Guest-disabled mode remains discoverable but exposes only a login action, never the upload workflow.

## Verification

- Jest: 8/8 frontend behavior/accessibility tests passed, including real REST-shaped create/result flow, polling visibility/backoff, and axe-core semantic checks.
- JS and CSS lint passed; production build succeeded with no source maps.
- PHP 7.4/8.3 frontend integration passed with WordPress 7.0.2 and WooCommerce 10.9.4.
- In-app browser E2E passed on the real local product page: one trigger, login-only guest-disabled state, Escape/focus restoration, 320 px containment, and zero console warnings/errors.
- Real HTTP theme checks rendered exactly one trigger and one modal under Storefront 4.6.2, Twenty Twenty-Five, and the installed Art theme.
- Product prompt, API keys, private paths, and provider URLs were absent from page configuration/markup.

Detailed evidence: [M6-QA-REPORT.md](M6-QA-REPORT.md).

**Decision:** G6 passes. The implementation enters M7 release-readiness testing.
