# Merchant Setup Guide

## Before enabling the feature

- Use WordPress 6.9+, WooCommerce 10.9+, and PHP 7.4+.
- Confirm WordPress can create a private temporary directory outside the public document root.
- Obtain credentials for one provider. The plugin does not switch providers automatically.
- Review the provider's terms, data-retention policy, regional requirements, and image-use restrictions.
- Update the store privacy notice before inviting customers to upload images.

## Global settings

Open **WooCommerce > Settings > Products > Virtual Try-On**.

1. Enable Virtual Try-On.
2. Select **OpenAI** or **SeaAI**.
3. Configure only the selected provider:
   - OpenAI: enter an API key with access to GPT Image 2.
   - SeaAI: the API root defaults to `https://theminitech.net/wp-json/seaai/v1`; override it only when needed, then enter its API key.
4. Choose the quality. SeaAI defaults to `low`; OpenAI uses its supported quality allowlist.
5. Decide whether logged-out visitors may generate previews. Guest generation is off by default and its limit applies only when enabled.
6. Set separate daily limits for guests and signed-in customers. WordPress administrators with `manage_options` are unlimited.
7. Set the retention period. The hard maximum is 24 hours.
8. Leave Debug Mode off unless troubleshooting. Debug logs are redacted and never contain images, credentials, cookies, private paths, or result URLs.

Blank or masked secret fields keep the existing key. Saving a new key stores the option with autoload disabled. Runtime constants and filters may override saved credentials; only the active provider is read.

## Product settings

Open a product in the WooCommerce Classic Product Editor and use **Product data > Advanced**.

1. Add a readable featured product image.
2. Enable **Virtual Try-On**.
3. Select an experience type:
   - Clothing
   - Hats
   - Shoes
   - Earrings
   - Rings
   - Necklaces
   - Bracelets
   - Nose Rings
   - Belly Button Rings
   - Hair Accessories
   - Anklets
   - Brooches & Pins
   - Lip Rings
   - Tongue Rings
   - Body Chains
   - Glasses
   - Wig
   - Furniture
   - Product placement
   - Auto
4. Add a concise product-specific instruction describing what must remain accurate. Do not add credentials or customer data.
5. Save the product and test the product page.

Variable products use the parent product configuration. Selecting a new variation invalidates the shopper's prior local input so it cannot be combined with the wrong variation.

## Storefront behavior

Eligible products display one **Virtual Try-On** button after the add-to-cart form. A dynamic `sea-tryon/virtual-try-on` block is available as a fallback for custom product templates. Inserting the manual block suppresses automatic duplicate output.

If guest generation is disabled, logged-out visitors see a login action but no upload controls. Generated results are streamed through an authenticated REST route and are never added to the Media Library.

## Staging checklist

- Test one person image and one room/scene image with the selected provider.
- Confirm the generated product identity, color, shape, and placement are acceptable.
- Test the configured daily limits.
- Test logged-in and guest access as applicable.
- Confirm other sessions cannot access a job or result.
- Confirm DELETE and TTL cleanup remove private files.
- Check WooCommerce logs for redacted error codes only.
- Verify the product page on the store's actual theme, cache, CDN, and security-plugin stack.

Do not enable production traffic until these checks pass with the store's real provider account.
