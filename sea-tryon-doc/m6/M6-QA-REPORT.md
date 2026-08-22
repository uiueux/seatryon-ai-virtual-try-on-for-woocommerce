# M6 QA Report

- Date: 2026-08-09
- Gate: G6 — Frontend experience
- Verdict: **PASS**
- Open M6 blockers: **0**

## Automated and browser evidence

| Check | Result | Evidence |
| --- | --- | --- |
| Server visibility | PASS | Global/product enablement, purchasability, readable product image, selected provider configuration, and guest-login mode are fail closed. |
| Mounting | PASS | Classic and block-compatible WooCommerce public hook paths render one non-submit button; the manual dynamic block suppresses automatic duplication. |
| Accessibility | PASS | Named modal, `aria-modal`, labels, associated helper/error text, `aria-live`, busy state, focus trap, Escape close, trigger focus restoration, and axe-core semantic checks are tested. |
| Upload UX | PASS | Type/size checks, filename, object-URL preview/revocation, consent gate, remove, and variation reset are tested. |
| Async job UX | PASS | Create, status, authenticated blob result, delete, retry, previous-result preservation, stable public errors, and abort behavior are tested. |
| Guest-disabled UX | PASS | The real logged-out product page displays the login explanation/link while the upload workflow remains hidden. |
| Responsive | PASS | At 320 px the modal remained within the viewport and the document had no horizontal overflow. Reduced-motion and RTL rules are present in the production bundle. |
| Polling/performance | PASS | Polling begins at 2 seconds, doubles to a 10-second ceiling, stops network requests in hidden tabs, and has a bounded visible-time duration. |
| Asset scope | PASS | WordPress integration confirms scripts/styles load on the eligible product only, and no provider request runs in the product-page request. |
| Theme compatibility | PASS | HTTP 200 and exactly one trigger/root under Storefront 4.6.2, Twenty Twenty-Five block theme, and a third-party Art theme. |

## Commands/results

- `npm.cmd run lint`: PASS.
- `npm.cmd run build`: PASS; source-map audit PASS.
- `npm.cmd test`: **8 tests passed**.
- Full PHPUnit on PHP 7.4 and PHP 8.3: **306 tests / 935 assertions**.
- `tests/qa/m6-frontend-integration.php`: PASS on PHP 7.4 and PHP 8.3.
- In-app Browser Playwright inspection: PASS; console warning/error log was empty.

## Later compatibility scope

The broader PHP/WordPress/WooCommerce version matrix, automated assistive-technology tools, QIT, and real provider latency belong to G7. No M6 code defect is deferred.

**Final M6 QA status: PASS.**
