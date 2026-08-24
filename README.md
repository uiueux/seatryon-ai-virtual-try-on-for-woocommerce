# SeaTryon – AI Virtual Try-On for WooCommerce

SeaTryon – AI Virtual Try-On for WooCommerce lets shoppers combine an uploaded person or room image with a WooCommerce product image. It supports clothing, jewelry, glasses, wigs, furniture, and general product-placement previews. You can choose a demo on the [demo website](https://seatheme.net/sea-tryon/) to try it out, with 3 free trials without logging in. 

https://youtu.be/WDDXnp4ejRo

## Free WordPress plugin

SeaTryon is a free WordPress plugin for WooCommerce. It requires a WordPress site with WooCommerce installed and activated.

- **Try it online:** [SeaTryon demo](https://seatheme.net/sea-tryon/) (no login required; visitors can try it up to three times).
- **Installation and configuration guide:** [SeaTryon documentation](https://seatheme.net/sea-tryon/seatryon-docs.html).

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

## External services

SeaTryon relies on an external image-generation service for every preview. Generation occurs only after the merchant enables Virtual Try-On and a shopper submits an image and a request.

- **WordPress AI Client and the selected connector:** The plugin sends the shopper's uploaded person or room image, the current WooCommerce product image, and the prompt (including the merchant's product instruction) to the provider configured in WordPress **Settings > Connectors**. WordPress core owns the connector credentials. If that connector uses OpenAI, see the [OpenAI Services Agreement](https://openai.com/policies/services-agreement/) and [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy/). Other connectors have their own terms and privacy policies, which the site owner must review.
- **SeaAI Universal X gateway:** When selected, the plugin uploads the same two images and prompt to the configured SeaAI API root, then downloads the generated result. The default is `https://theminitech.net/wp-json/seaai/v1`. The configured SeaAI API key is sent only as an authorization credential during connection tests and generation. See TheMiniTech's [Terms of Service](https://theminitech.net/zh/terms-of-service/) and [Privacy Policy](https://theminitech.com/privacy-policy/).

The plugin does not intentionally send customer names, email addresses, user IDs, orders, billing or shipping details, cookies, session tokens, or API keys as prompt data. Store owners must obtain required consent, review provider retention and processing terms, and update their site's privacy notice.

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

The command requires Bash, `rsync`, and `zip`. It writes `dist/seatryon-ai-virtual-try-on-for-woocommerce-<version>.zip`. Internal documents, tests, fixtures, development dependencies, source maps, and likely credential files are excluded. The CI release workflow repeats the audit against the finished ZIP.

## Privacy model

The browser communicates only with the site's WordPress REST API. Provider keys stay server-side. Uploaded and generated images use private temporary storage, do not enter the WordPress Media Library, and default to a maximum retention of 24 hours. The plugin does not transmit customer identity, order, billing, or shipping data to an image provider.

## License

Licensed under the GNU General Public License v2.0 or later. See [LICENSE](LICENSE).
