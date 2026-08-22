# ADR-004: WooCommerce product-page frontend mounting

- Status: Accepted
- Date: 2026-08-08
- Milestone task: M0-06
- Decision owner: Frontend integration
- Applies to: WordPress 6.9+, WooCommerce 10.9+ and the WooCommerce 11 compatibility target

## Context

Sea Try-On must place one **Virtual Try-On** trigger near the purchase area of an eligible single-product page. The same visibility rules and markup must work with classic PHP templates, block themes, the Site Editor, WooCommerce's legacy Add to Cart Form block, and the newer Add to Cart + Options block. The extension must use public WordPress/WooCommerce APIs and must not call classes under `Automattic\WooCommerce\Internal` or classes marked `@internal`.

The repository is currently documentation-only. Project triage reports no plugin header, `block.json`, JavaScript build, or test tooling yet. This ADR therefore fixes the integration contract for M6; it does not implement the plugin.

## Evidence

The following source paths were inspected in the local WooCommerce 10.9.4 installation:

- `templates/single-product/add-to-cart/simple.php`
- `templates/single-product/add-to-cart/variable.php`
- `templates/single-product/add-to-cart/variation-add-to-cart-button.php`
- `templates/single-product/add-to-cart/grouped.php`
- `templates/single-product/add-to-cart/external.php`
- `src/Blocks/BlockTypes/AddToCartForm.php`
- `src/Blocks/BlockTypes/AddToCartWithOptions/AddToCartWithOptions.php`
- `src/Blocks/Templates/SingleProductTemplateCompatibility.php`
- `assets/client/blocks/add-to-cart-form/block.json`
- `assets/client/blocks/add-to-cart-with-options/block.json`

The evidence establishes:

1. The classic simple, variable, grouped, and external product templates call `woocommerce_after_add_to_cart_form` after their cart form.
2. `woocommerce/add-to-cart-form` renders the product-type PHP template through `do_action( 'woocommerce_' . $product->get_type() . '_add_to_cart' )`; the same classic hook therefore runs on its frontend output.
3. `woocommerce/add-to-cart-with-options` calls `woocommerce_after_add_to_cart_form` directly for purchasable products when its compatibility layer is enabled. Its source marks the action as available since WooCommerce 10.1.0.
4. Both WooCommerce add-to-cart blocks declare `apiVersion: 3` and consume `postId` context.
5. The current WooCommerce trunk still calls `woocommerce_after_add_to_cart_form`, which is positive forward-compatibility evidence for the WC 11 target, but not a substitute for testing the released WC 11 build.

Canonical references:

- [WooCommerce hook reference](https://woocommerce.github.io/code-reference/hooks/hooks.html)
- [WooCommerce template structure and hook guidance](https://developer.woocommerce.com/docs/theming/theme-development/template-structure/)
- [WooCommerce block reference](https://developer.woocommerce.com/docs/block-development/reference/block-references/)
- [Current WooCommerce AddToCartWithOptions source](https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/src/Blocks/BlockTypes/AddToCartWithOptions/AddToCartWithOptions.php)
- [WordPress block metadata reference](https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/)
- [WordPress `hooked_block_types` reference](https://developer.wordpress.org/reference/hooks/hooked_block_types/)

## Decision

### 1. One shared server-side renderer

M6 shall implement one PHP renderer for the trigger. Both the automatic WooCommerce hook adapter and the dynamic block adapter call this renderer; neither adapter owns product eligibility logic.

The renderer contract must:

- accept the current product and a small rendering context;
- enforce all product-page visibility rules from `REQUIREMENTS.md` before returning markup;
- return an empty string outside an eligible, purchasable single-product context;
- output a native `<button type="button">`, never an implicit submit button;
- use a product-ID-scoped request guard so the same product is rendered no more than once;
- expose the final visibility/label/classes through plugin-owned filters, without letting a display filter bypass server-side authorization or generation limits;
- escape all attributes/text at the point of output.

Only the trigger belongs at the WooCommerce hook location. The dialog/modal shell must be emitted once outside the cart form, for example from a dedicated footer/root mount. This matters because Add to Cart + Options captures compatibility-hook output and places it inside its generated `<form>`.

### 2. Default automatic mounting uses a public WooCommerce action

The default adapter shall register the shared trigger renderer on:

```php
add_action( 'woocommerce_after_add_to_cart_form', $callback, 20 );
```

`woocommerce_after_add_to_cart_form` is selected instead of `woocommerce_after_add_to_cart_button` because the classic templates place it outside the cart form and it gives a cleaner default layout. Add to Cart + Options currently captures both locations inside its own generated form, so the trigger's explicit `type="button"` remains mandatory.

The adapter must not hook `woocommerce_single_product_summary` as a second automatic placement. WooCommerce's block-template compatibility class injects that summary hook relative to selected blocks, so combining it with the add-to-cart hook can move the trigger ahead of the expected purchase location or render it twice.

The default hook covers:

| Product page implementation | How the trigger is reached | Decision |
| --- | --- | --- |
| Classic PHP single-product template | Template calls `woocommerce_after_add_to_cart_form` | Supported automatically |
| `woocommerce/legacy-template` on a block theme | Legacy template renders the classic product templates | Supported automatically |
| `woocommerce/add-to-cart-form` | Block invokes the product-type template action, which loads the classic template | Supported automatically |
| `woocommerce/add-to-cart-with-options` | Block calls the action directly since WC 10.1 | Supported automatically while compatibility is enabled |

The implementation may offer a merchant setting for placement before or after the form, but only through an allowlist of known public WooCommerce hooks. The default remains `woocommerce_after_add_to_cart_form` at priority 20. Arbitrary hook names from untrusted input are not accepted.

### 3. Approve a dynamic block as the supported fallback

M6 shall register `sea-tryon/virtual-try-on` even though normal product pages mount automatically. It is the supported fallback for a Site Editor template or theme override that removes the standard WooCommerce action, and it lets a merchant choose an exact block position.

The block contract is:

- metadata name: `sea-tryon/virtual-try-on`;
- `apiVersion: 3` and a current `$schema` entry in `block.json`;
- server registration with `register_block_type_from_metadata()` on `init`;
- dynamic rendering through `render`/`render.php` (or a registered `render_callback` if build constraints require it);
- `usesContext: [ "postId" ]`, with server-side validation that the context resolves to a `WC_Product`;
- `get_block_wrapper_attributes()` for its frontend wrapper;
- no saved product state and no generated image data in block attributes;
- a minimal editor placeholder/preview; frontend eligibility remains authoritative on the server;
- `supports.multiple: false` as an editor aid, while request-level PHP deduplication remains the final guarantee.

When the resolved single-product template already contains `sea-tryon/virtual-try-on`, the automatic WooCommerce hook adapter must be suppressed for that request before either location renders. Merely letting the second renderer return empty is insufficient: if the automatic hook occurs first, it would defeat the merchant's chosen block position. M6 must recursively inspect the resolved template blocks (including reusable patterns/template parts where applicable), then set the automatic-mount decision once for the request.

### 4. Do not use Block Hooks for default injection in MVP

WordPress's Block Hooks API (`blockHooks` metadata or the public `hooked_block_types` filter) can insert a dynamic block before or after both WooCommerce add-to-cart block names. It is not the default M6 mechanism because:

- `blockHooks` metadata is unconditional and can affect patterns or non-product contexts that reuse an anchor;
- using Block Hooks and the WooCommerce compatibility action together creates a duplicate-placement problem;
- merchant-modified templates record and expose hooked-block insertion/removal state, increasing migration and support surface;
- the existing WooCommerce action already covers the supported standard templates on the frontend.

`hooked_block_types` remains an approved future enhancement for a conditional, block-native auto-placement mode. If introduced, it must restrict context to `single-product`/`single-product-*` templates, target only `woocommerce/add-to-cart-form` and `woocommerce/add-to-cart-with-options`, and suppress the WooCommerce action adapter before rendering. It must not target `woocommerce/product-button`, because that block is also used in product loops and archives.

### 5. Extension and asset-loading contract

M6 should introduce plugin-owned public extension points with stable names. The implementation names must be finalized with the code, but their responsibilities are fixed here:

- filter whether automatic mounting is enabled for the current product;
- filter the allowlisted automatic position before hooks are registered;
- filter final button label and non-security presentation classes;
- action after the trigger has rendered, for non-sensitive adjacent markup.

Frontend scripts/styles load only when the request is an eligible single-product page and either automatic mounting is active or the fallback block is present. The block editor receives only editor assets; frontend behavior belongs in `viewScript`/`viewScriptModule` and frontend styles in `viewStyle`/`style` as supported by the chosen build target.

## Rejected alternatives

### Only `woocommerce_single_product_summary`

Rejected as the default because the precise placement in blockified templates depends on WooCommerce's compatibility mapping and may differ from the classic priority sequence.

### Only `woocommerce_after_add_to_cart_button`

Rejected as the default because it is inside the classic cart form. It remains a possible allowlisted merchant position, and any renderer used there must keep `type="button"` and must not output a nested form.

### Editing or replacing WooCommerce templates

Rejected because theme overrides are maintenance-heavy and plugin/core updates may replace source templates. Public hooks and a standalone dynamic block are the upgrade-safe extension points.

### Calling WooCommerce block-template compatibility classes

Rejected. The inspected compatibility classes are marked `@internal`; they are evidence about current behavior, not callable extension APIs.

## Risks and mitigations

| Risk | Impact | Mitigation / test |
| --- | --- | --- |
| A theme override omits `woocommerce_after_add_to_cart_form` | Automatic trigger is absent | Document and test the dynamic block fallback; flag outdated template overrides in diagnostics where practical |
| Another extension sets `woocommerce_disable_compatibility_layer` to true | Add to Cart + Options does not call legacy compatibility actions | Dynamic block fallback; optional future conditional Block Hooks adapter |
| Add to Cart + Options places captured hook markup inside its `<form>` | Trigger may submit the cart or invalid markup may be nested | Render only `<button type="button">`; mount modal shell elsewhere; never render another `<form>` |
| Site Editor canvas does not reproduce frontend PHP action output | Merchant may not see the automatically mounted trigger while editing | Dynamic block has an editor placeholder; document that automatic-hook verification is frontend E2E |
| Manual fallback block and automatic hook both exist | Duplicate trigger or manual position ignored | Detect the block in the resolved template before rendering and disable automatic mounting; retain product-scoped request dedupe |
| A custom template contains no supported add-to-cart block/action | No automatic anchor exists | Merchant inserts `sea-tryon/virtual-try-on` in the desired product context |
| WooCommerce 11 changes block internals | Regression despite current trunk evidence | Depend only on public hooks; run the matrix below on the released WC 11 build/RC before declaring compatibility |
| Trigger accidentally renders in a product loop | Extra buttons and incorrect product context | Require `is_product()` plus resolved `WC_Product`; do not anchor to `woocommerce/product-button` |

## M6 verification matrix

Each case must assert exactly one trigger, correct product ID/context, no nested form, no console/PHP error, and no trigger on archive pages.

| Theme/template | Products | Expected path |
| --- | --- | --- |
| Storefront/classic template | Simple, variable, grouped, external | `woocommerce_after_add_to_cart_form` |
| Current Woo block theme using `woocommerce/legacy-template` | Simple, variable | Classic action inside legacy template |
| Blockified Single Product with `woocommerce/add-to-cart-form` | Simple, variable | Product-type template action |
| Blockified Single Product with `woocommerce/add-to-cart-with-options` | Simple, variable, grouped, external | Compatibility action since WC 10.1 |
| Site Editor template with manually placed `sea-tryon/virtual-try-on` | Simple, variable | Dynamic block; automatic adapter suppressed |
| Add to Cart + Options with compatibility layer disabled | Simple | No automatic hook; dynamic block fallback succeeds |
| Theme override missing the selected WooCommerce action | Simple | No automatic hook; dynamic block fallback succeeds |
| Product archive/Product Collection | Any | No trigger |

Run this matrix on WooCommerce 10.9.x and the latest available WooCommerce 11 release/RC. Verify the variable-product trigger can observe the selected variation without depending on private WooCommerce JavaScript APIs.

## Gate result

M0-06 passes. A public, repeatable automatic frontend mounting path exists for standard classic and block product pages, and the dynamic block fallback is approved for non-standard templates or disabled compatibility hooks. Implementation and E2E verification remain M6 work.
