# Privacy and Data Flow

## Data flow

1. The shopper selects a local JPEG, PNG, or WebP image and explicitly consents.
2. The browser sends the image to the store's `sea-tryon/v1` WordPress REST endpoint.
3. The server validates and normalizes the upload, then writes it to private temporary storage outside the trusted public document root.
4. An Action Scheduler job sends exactly two images to the selected provider: the shopper image first and the product image second.
5. The validated generated image is stored in the same private job scope.
6. The browser retrieves it through an ownership-checked REST stream and displays a local blob URL.
7. DELETE, expiry, privacy erasure, deactivation, or uninstall removes the job files and metadata.

## Data sent to the selected provider

- The shopper's uploaded person or scene image.
- The current product image.
- A controlled English experience prompt plus the merchant's product-specific instruction.

The plugin does not intentionally send customer name, email, user ID, order history, billing address, shipping address, cookies, session tokens, or the store's API keys as prompt metadata.

## Local data

- Private input and output image files, retained for the configured period up to 24 hours.
- Short-lived job records containing one-way owner identifiers, product/variation identifiers, status, timestamps, provider choice, private storage references, and safe error metadata.
- Separate daily quota counters for guests and signed-in users.
- High-entropy HttpOnly guest session cookies and short-lived action tokens.
- Optional redacted WooCommerce logs when Debug Mode is enabled.

Customer uploads and generated results are not WordPress attachments and do not enter the Media Library.

## Access and deletion

Job status, results, and deletion require the same signed-in identity or guest session that created the job. Unknown and unauthorized job IDs use the same non-enumerating response.

The plugin registers WordPress personal-data exporter and eraser hooks for signed-in users. Guests can delete their current jobs through the authenticated guest session; remaining temporary data expires automatically. Deactivation and uninstall run site-scoped cleanup.

## Suggested privacy-policy language

> When you use Virtual Try-On, the image you select and the relevant product image are sent to the image-generation provider selected by this store to create a preview. The store temporarily processes the upload, generated image, and technical job information for this purpose. These images are not added to the WordPress Media Library and are scheduled for deletion within the store's configured retention period, which is no longer than 24 hours. AI previews may be inaccurate and do not guarantee fit, size, color, or appearance.

Store operators must name the selected provider, link its privacy policy, state the applicable legal basis, and adjust this language for their jurisdiction.
