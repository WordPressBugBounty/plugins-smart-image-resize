=== Smart Image Resize for WooCommerce ===
Contributors: nlemsieh
Tags: woocommerce images, product image resize, uniform images, same size images, image optimization
Requires at least: 4.0
Tested up to: 7.0
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Requires PHP: 7.0
Stable tag: 1.17.0

Make all WooCommerce product images the same size without cropping. Automatic, no configuration required.

== Description ==

**[Smart Image Resize](https://sirplugin.com/)** fixes inconsistent product image sizes across your WooCommerce store. Instead of cropping and cutting off parts of your product, it resizes images to uniform dimensions and fills the remaining space with a clean background.

The result: a professional, grid-aligned product catalog — without any manual image editing.

### The Problem

WooCommerce stores with products from different suppliers, dropshipping feeds, or multiple vendors end up with a messy grid of mismatched image sizes. Some are tall, some are wide, some are tiny. It looks unprofessional and hurts conversions.

### The Solution

Smart Image Resize automatically processes every product image you upload:

1. Trims excess whitespace around the product
2. Resizes it to fit your store's image dimensions
3. Adds a neutral background to fill remaining space (no cropping)

Your entire catalog looks uniform with zero manual work.

### Free Features

* Automatically resize new product images on upload
* Bulk resize up to 150 existing product images
* Trim whitespace to keep products centered
* Disable upscaling for small images

### Pro Features

* **Unlimited images** — no processing cap
* **PNG → JPG conversion** — dramatically smaller file sizes
* **WebP generation** — next-gen format for faster loading
* **Watermarking** — protect your product photos with your logo
* **Priority support** — fast help via chat or email

[Upgrade to Pro →](https://sirplugin.com?utm_source=wp&utm_medium=link&utm_campaign=readme)

### Works With

* WooCommerce 3.0+
* Dokan, WCFM, and other multivendor plugins
* WooCommerce HPOS (High-Performance Order Storage)
* WP CLI (`wp media regenerate`)
* Jetpack, WP Smush, and other image optimization plugins
* All major themes (Flatsome, Avada, Astra, Phlox, etc.)

### What Users Say

★★★★★
> "I downloaded the free version and after 3 minutes I bought the PRO version. The plugin is EXCELLENT! We have 30,000 imported products with different photos." — [@prokurent](https://wordpress.org/support/topic/excellent-8052/)

★★★★★
> "I recommended this to a dev friend the day after I used it. Customers don't care to crop their images on their own." — [@jpontinen](https://wordpress.org/support/topic/did-its-job-and-saved-a-ton-of-tedious-work/)

★★★★★
> "The time saving benefits are enormous and the plugin support is A+." — [@chickwithbob](https://wordpress.org/support/topic/brilliant-lifesaver-with-incredible-support/)

== Installation ==

1. Upload the `smart-image-resize` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to **WooCommerce > Smart Image Resize** — it works immediately with smart defaults

**Requirement:** PHP `fileinfo` extension must be enabled (most hosts have this on by default).

== Frequently Asked Questions ==

= Does the plugin work without any configuration? =

Yes. It works immediately after activation. New product images are automatically resized on upload. You can customize settings at **WooCommerce > Smart Image Resize > Settings** if needed.

= Will it crop my images? =

No. The plugin never crops. It resizes the image to fit the target dimensions and adds a background color to fill any remaining space. Your entire product stays visible.

= How do I resize images I already uploaded? =

Go to **WooCommerce > Smart Image Resize > Bulk Resize** and click **Start Processing**. It will process your existing catalog automatically.

= My images look blurry after resizing. =

Two common causes:

1. **Compression too high** — Lower the compression level in Settings > Optimization.
2. **Source image too small** — Enable "Disable Upscaling" in Settings > Advanced to prevent stretching small images.

After changing settings, run the bulk resize tool again.

= Images still look wrong after processing. =

Two things to try:

1. **Clear your caches** — browser cache, caching plugin, and CDN. Old thumbnails are cached and need to be purged.
2. **Enable "Resize Original Image"** — Some themes display the full-size original image instead of thumbnails. Go to Settings > Advanced settings and turn on "Resize Original Image" so the original gets the same uniform treatment. Then run the bulk resize tool again.

= The bulk tool stopped midway. What happened? =

Your server ran out of memory processing a large image. Refresh and click Start again — it resumes where it left off. If it keeps happening, ask your host to increase the PHP memory limit.

= Can I undo changes? =

Yes. Go to the **Help** tab and use the bulk-restore tool to revert your images back to their originals.

= Does it affect non-product images? =

No. Only product images are processed by default. You can enable category and brand images in settings.

= Can I use it for non-WooCommerce images? =

Yes. [See our documentation](https://docs.sirplugin.com/faqs/general-questions#can-i-use-the-plugin-to-resize-non-product-images-and-how) for how to enable this.

= Is it compatible with multivendor plugins? =

Yes. Works with Dokan, WCFM, and other multivendor setups. Vendor-uploaded images are processed automatically.

= How do I get support? =

* **Free:** [WordPress.org support forum](https://wordpress.org/support/plugin/smart-image-resize/)
* **Pro:** [Priority support](https://sirplugin.com/support)

== Screenshots ==

1. Before and after — uniform product images without cropping.
2. Settings page — simple, clean interface.
3. Bulk resize tool — process your entire catalog.

== Changelog ==

= 1.17.0 =

* Added support for processing original/full-size images.
* Added bulk-restore capability to revert image changes.
* Refreshed admin UI for a cleaner experience.

= 1.16.0 =

* New: Built-in bulk resize tool (no third-party plugin needed).
* Improved: Redesigned admin interface.

= 1.15.1 =

* Various improvements and bugfixes.
* Compatibility with WooCommerce 10.6.

= 1.15.0 =

* Added support for product brand images.
* Various improvements and bugfixes.

= 1.13.1 =

* Compatibility with WooCommerce 10.3.

= 1.13.0 =

* Enhanced bulk resize page UX.
* Various improvements and bugfixes.

= 1.12.1 =

* Admin tweaks for better user experience.

= 1.12.0 =

* New filter `wp_sir_exclude_trim_sizes` to exclude sizes from trimming.
* Added AVIF format support.
* Fixed compatibility with Phlox theme.
* Various improvements and bugfixes.

= 1.10.2 =

* Various improvements and bugfixes.

= 1.10.0 =

* Added support for Phlox theme.
* Added option to prevent upscaling small images.
* Introduced dedicated Help tab.
* Improved compatibility with PHP 8.3.
* Various bugfixes and stability improvements.

= 1.8.1 =

* Compatibility with WooCommerce HPOS.

= 1.8.0 =

* New experimental "Cropping mode" setting.

= 1.7.7 =

* Improved theme and plugin compatibility.
* Fixed trim whitespace border size in GD.
* Compatibility with WooCommerce 6.9.

= 1.7.6 =

* Removed confusing "Use WordPress cropping" option.
* Fixed WebP files not deleted when feature disabled.
* Compatibility with WooCommerce 6.3.

= 1.7.5 =

* Background reprocessing of skipped images.
* Improved CMYK image handling.
* Performance and stability improvements.

= 1.6.0 =

* Per-size resize fit mode.
* Stability improvements.

= 1.5.0 =

* Filter processed images in Media Library.
* Improved whitespace trimming.

= 1.4.0 =

* Category image support.
* WooCommerce REST API support.
* Improved bulk-import processing.
* Performance improvements with Imagick.

== Upgrade Notice ==

= 1.17.0 =
New: Process original images, bulk-restore, and refreshed admin UI.
