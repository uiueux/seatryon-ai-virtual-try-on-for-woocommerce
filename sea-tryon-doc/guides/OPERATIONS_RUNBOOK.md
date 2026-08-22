# Operations, Rollback, and Support Runbook

## Health checks

- Confirm WooCommerce is active and at least version 10.9.
- Confirm the selected provider has a key and, for SeaAI, a valid HTTPS API root ending in `/wp-json/seaai/v1`.
- Confirm the private temporary root is writable and outside the trusted document root.
- Confirm Action Scheduler is initialized and the `sea-tryon` group has a recurring cleanup action.
- Keep Debug Mode off during normal operation.

## Common customer-facing failures

| Error class | Operator action |
| --- | --- |
| Invalid image | Ask for a clear JPEG, PNG, or WebP under the configured limit. |
| Authentication/session | Ask the shopper to reload, sign in when required, and retry. |
| Daily limit | Review guest/customer limit settings; do not manually bypass ownership. |
| Provider authorization/billing | Verify only the selected provider credential and account status. |
| Provider throttling/temporary outage | Review redacted WooCommerce logs and wait for the bounded queue retry. |
| Job remains processing | It may represent an ambiguous paid dispatch. Do not replay it manually; allow TTL cleanup and create a new job only after confirming provider state. |
| Storage notice | Correct filesystem permissions or move the WordPress temp directory outside the public web root. |

## Safe rollback

1. Disable Virtual Try-On in WooCommerce settings to stop new shopper jobs.
2. Allow active jobs to finish or delete them through their owning sessions.
3. Deactivate the plugin. Deactivation unschedules plugin actions and removes temporary jobs/files.
4. Replace the plugin directory with the previously verified package, then reactivate.
5. Recheck settings, private storage, Action Scheduler, and one staging product.

Do not copy private job directories between releases. Never restore stale guest-session, replay, lock, quota, or job options from a database backup without a privacy review.

## Uninstall

Uninstall from the WordPress Plugins screen when permanent removal is intended. The guarded uninstall routine removes plugin settings, product metadata, indexed jobs, idempotency/replay/quota/lock options, scheduled actions, and private temporary scopes. Take a database backup first if business policy requires retaining configuration, but do not retain customer images longer than disclosed.

## Escalation bundle

Provide support with plugin/WP/Woo/PHP versions, selected provider name, approximate UTC time, anonymous job ID, safe plugin error code, and redacted WooCommerce log lines. Never send API keys, Authorization headers, cookies, uploaded images, generated images, private filesystem paths, provider result URLs, or raw provider request/response bodies.
