=== SeaTryon – AI Virtual Try-On for WooCommerce ===
Tags: woocommerce, virtual try-on, ai image, product visualization
Requires at least: 7.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Create AI-powered virtual try-on and product-placement previews from a customer's image and the current WooCommerce product image.

== Description ==

SeaTryon – AI Virtual Try-On for WooCommerce adds a Virtual Try-On experience to eligible single-product pages. Customers can upload a personal photo or a room image and generate one preview that combines it with the current product.

The plugin supports clothing, jewelry, glasses, wigs, furniture, and general product placement. Merchants choose either the WordPress AI Client or the SeaAI Universal X gateway. WordPress AI credentials are managed centrally under Settings > Connectors and are never stored by this plugin.

Version 1.1.0 uses the provider-agnostic WordPress AI Client introduced in WordPress 7.0. Validate the selected connector and complete generation flow in a staging environment before enabling it for customers.

You can choose a demo on the [demo website](https://seatheme.net/sea-tryon/) to try it out, with 3 free trials without logging in. 

== Video Guide ==

https://youtu.be/WDDXnp4ejRo

= Privacy =

When generation is enabled, a customer's uploaded image and the selected product image are sent to the image provider configured by the store owner. The plugin does not send customer names, email addresses, billing details, shipping details, or order information.

Input images, generated images, and temporary job data are stored outside the WordPress Media Library and are scheduled for deletion within the configured retention period. The default maximum retention is 24 hours. Store owners are responsible for reviewing the selected provider's terms and privacy policy and for updating their site's privacy notice.

== External services ==

SeaTryon relies on an external image-generation service for each virtual try-on or product-placement preview. The store owner must configure either a WordPress AI Client connector or the SeaAI Universal X gateway. No image is transmitted until Virtual Try-On is enabled and a shopper submits a generation request after selecting an image.

=== WordPress AI Client and the selected connector ===

WordPress AI is currently not recommended for use, because I tested Openai and found that it does not support the latest Image2 model or multi-image mode, which means it does not support the try-on feature. I do not currently have the conditions to test Gemini. If you wish to use Gemini and would like my assistance, please contact me via seatheme.net@gmail.com

The plugin passes the shopper's uploaded person or room image, the current WooCommerce product image, and the generation prompt (including the merchant's product-specific instruction) to the provider selected under WordPress Settings > Connectors. WordPress core manages the connector credentials; this plugin does not store or expose them. The selected connector may transmit this data to its own AI service. If the connector uses OpenAI, review the [OpenAI Services Agreement](https://openai.com/policies/services-agreement/) and [OpenAI Privacy Policy](https://openai.com/policies/privacy-policy/). If another connector is selected, the store owner must review that provider's current terms and privacy policy before enabling generation.

The plugin does not intentionally send customer names, email addresses, user IDs, orders, billing or shipping details, cookies, session tokens, or API keys as prompt data. The provider may receive technical request metadata required to operate its service.

=== SeaAI Universal X gateway ===

When SeaAI is selected, the plugin sends the same two images and the generation prompt to the configured SeaAI API root. The default gateway is `https://theminitech.net/wp-json/seaai/v1`; the store owner may configure another HTTPS SeaAI gateway. The images are uploaded for the generation request, and the generated result is downloaded from that gateway. The configured SeaAI API key is sent in an authorization header when the store owner tests the connection and when a generation request runs. Review TheMiniTech's [Terms of Service](https://theminitech.net/terms-of-service-en/) and [Privacy Policy](https://theminitech.com/privacy-policy/) before using this gateway.

External providers control their own processing, retention, hosting locations, and policies. Store owners are responsible for obtaining any required consent, reviewing the linked provider terms and privacy policy, and updating their site's privacy notice.

= Compatibility =

* WordPress 7.0 or newer.
* WooCommerce 10.9 or newer.
* PHP 7.4 or newer.
* Product settings use the WooCommerce Classic Product Editor.
* The storefront integration is designed for classic templates, block themes, and Site Editor single-product templates.

Local validation passed with WooCommerce 10.9.4, Storefront 4.6.2, Twenty Twenty-Five, and a third-party classic theme. WooCommerce 11.x and QIT remain release-gate items.

== Source Code and Build Instructions ==

The complete human-readable source code and build tools for this plugin are publicly maintained at:

https://github.com/uiueux/seatryon-ai-virtual-try-on-for-woocommerce

The shipped JavaScript bundles are generated from `assets/src/frontend.js`, `assets/src/admin.js`, and `blocks/virtual-try-on/virtual-try-on-editor.js`. The shipped stylesheets are generated from `assets/src/frontend.scss` and `assets/src/admin.scss`. The public repository also contains the block source, tests, build scripts, and package lock file used to reproduce the release assets.

Build tools and requirements:

* Node.js 20.19 or newer and earlier than 25.
* npm 10 or newer.
* `@wordpress/scripts` 34.0.0, installed from the locked `package-lock.json` dependency tree.

To regenerate the compiled JavaScript and CSS from the public source repository, run from its root directory:

    npm ci
    npm run build

The production build is written to `assets/build/`. Source files and build tools are intentionally kept in the public repository; the WordPress release package contains only the runtime files and compiled assets needed for installation.

== Installation ==

1. Upload the plugin directory to `/wp-content/plugins/`, or install the release ZIP from Plugins > Add New > Upload Plugin.
2. Activate WooCommerce.
3. Activate SeaTryon – AI Virtual Try-On for WooCommerce.
4. Configure the provider, guest access, daily limits, retention, and debug mode under WooCommerce > Settings > Products > Virtual Try-On.
5. Edit an eligible product, open Product data > Advanced, enable Virtual Try-On, choose an experience type, and save a product-specific instruction.
6. Test the complete generation flow on staging before enabling it for customers.

== Frequently Asked Questions ==

= Does the plugin save generated images to the Media Library? =

No. Uploaded and generated images use private temporary storage and are not created as WordPress attachments.

= Can guests use Virtual Try-On? =

Guest generation is disabled by default. The store owner can enable it and set separate daily limits for guests and signed-in customers. WordPress administrators are not limited.

= Which image providers are supported? What is the cost per generation? =

The WordPress AI Client and the SeaAI Universal X gateway are supported. For WordPress AI, configure a site connector that supports image editing under Settings > Connectors. Only one provider is active at a time. (WordPress AI is currently not recommended for use, because I tested OPENAI and found that it does not support the latest Image 2 model or multi-image mode, which means it does not support the try-on feature. I do not currently have the conditions to test Gemini. If you wish to use Gemini and would like my assistance, please contact me seatheme.net@gmail.com). The cost per generation is 30 credits for SeaAI Universal X, which is equivalent to 0.05 USD (based on the $9 for 5,000 credits package). Please note that this price is for reference only and is not fixed; it may fluctuate based on cost calculations.

== Changelog ==

= 1.1.0 - 2026-08-19 =
* Migrated image editing from a direct provider API to the WordPress AI Client.
* Moved provider credentials to the site-level Settings > Connectors screen.
* Removed the plugin-level OpenAI key and quality settings.

= 1.0.0 - 2026-08-10 =
* Added the first public SeaTryon release.
* Fixed public REST image processing when the WordPress file API was not preloaded.
* Added safe local-loopback SeaAI URL support and WooCommerce HPOS compatibility declaration.

= 0.1.0 - 2026-08-09 =
* Added the initial plugin and quality-tooling foundation.
* Added release metadata and reproducible package auditing.
* Added provider adapters, private asynchronous jobs, guest and customer quotas, authenticated result delivery, privacy cleanup, WooCommerce administration, product controls, and the accessible storefront experience.
* Declared compatibility with WooCommerce High-Performance Order Storage.
