# SeaTryon – AI Virtual Try-On for WooCommerce

SeaTryon – AI Virtual Try-On for WooCommerce lets shoppers combine an uploaded person or room image with a WooCommerce product image. It supports clothing, jewelry, glasses, wigs, furniture, and general product-placement previews.

## Project status

Version `1.1.0` uses the provider-agnostic WordPress AI Client. The automated implementation gates pass; live connector and final compatibility evidence should still be completed in staging before broad deployment.

## Compatibility baseline

- WordPress 7.0 or newer
- WooCommerce 10.9 or newer
- PHP 7.4 or newer
- Node.js 20 or newer for asset development
- Current and previous major releases of Chrome, Edge, Firefox, and Safari for the eventual storefront UI

The extension uses the WooCommerce Classic Product Editor for product-level settings. The storefront integration supports classic templates, block themes, and Site Editor single-product templates through a public WooCommerce hook with a dynamic block fallback. Local validation passed with WooCommerce 10.9.4, Storefront 4.6.2, Twenty Twenty-Five, and a third-party classic theme.

## Merchant setup

1. Open **WooCommerce > Settings > Products > Virtual Try-On**.
2. Enable the feature and select exactly one provider.
3. Configure an image-editing AI provider under WordPress **Settings > Connectors**, or enter a SeaAI base URL/API key. Connector credentials are owned by WordPress and are not stored by this plugin.
4. Configure guest access, daily guest/customer limits, retention, and optional debug logging.
5. Edit a product in the Classic Product Editor, open **Advanced**, enable Virtual Try-On, choose the experience type, and add a product-specific instruction.
6. Confirm the product has a readable featured image, then test the storefront flow before enabling it for customers.

See [Merchant Setup](sea-tryon-doc/guides/MERCHANT_SETUP.md), [Privacy and Data Flow](sea-tryon-doc/guides/PRIVACY_DATA_FLOW.md), and the [Operations Runbook](sea-tryon-doc/guides/OPERATIONS_RUNBOOK.md).

## Development setup

Install PHP and JavaScript dependencies:

```sh
composer install
npm ci
```

Run the quality checks:

```sh
composer lint
composer phpstan
composer test
npm run lint:js
npm run lint:css
npm run build
```

On Windows PowerShell, use `npm.cmd` in place of `npm` when the PowerShell execution policy blocks `npm.ps1`.

## Release package

The release archive is assembled from an explicit deny list in `.distignore` and audited before it is accepted:

```sh
bash bin/build-zip.sh 1.1.0
```

The command requires Bash, `rsync`, and `zip`. It writes `dist/sea-tryon-<version>.zip`. Internal documents, tests, fixtures, development dependencies, source maps, and likely credential files are excluded. The CI release workflow repeats the audit against the finished ZIP.

## Privacy model

The browser communicates only with the site's WordPress REST API. Provider keys stay server-side. Uploaded and generated images use private temporary storage, do not enter the WordPress Media Library, and default to a maximum retention of 24 hours. The plugin does not transmit customer identity, order, billing, or shipping data to an image provider.

## License

Licensed under the GNU General Public License v2.0 or later. See [LICENSE](LICENSE).
