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

= Privacy =

When generation is enabled, a customer's uploaded image and the selected product image are sent to the image provider configured by the store owner. The plugin does not send customer names, email addresses, billing details, shipping details, or order information.

Input images, generated images, and temporary job data are stored outside the WordPress Media Library and are scheduled for deletion within the configured retention period. The default maximum retention is 24 hours. Store owners are responsible for reviewing the selected provider's terms and privacy policy and for updating their site's privacy notice.

= Compatibility =

* WordPress 7.0 or newer.
* WooCommerce 10.9 or newer.
* PHP 7.4 or newer.
* Product settings use the WooCommerce Classic Product Editor.
* The storefront integration is designed for classic templates, block themes, and Site Editor single-product templates.

Local validation passed with WooCommerce 10.9.4, Storefront 4.6.2, Twenty Twenty-Five, and a third-party classic theme. WooCommerce 11.x and QIT remain release-gate items.

== Source Code and Build Instructions ==

The complete human-readable source code and build tools for this plugin are publicly maintained at:

https://github.com/uiueux/woo-tryon

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

= Which image providers are supported? =

The WordPress AI Client and the SeaAI Universal X gateway are supported. For WordPress AI, configure a site connector that supports image editing under Settings > Connectors. Only one provider is active at a time.

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
