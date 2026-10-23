=== Smart Image Resize for WooCommerce ===
Contributors: nlemsieh
Tags: product image resize, woocommerce product images, image resize without crop, resize product photos, woocommerce images
Requires at least: 4.0
Tested up to: 7.1
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Requires PHP: 7.0
Stable tag: 1.17.1

Make all WooCommerce product images the same size without cropping. Automatic, no configuration required.

== Description ==

Your product images are all different sizes. Your shop looks messy. You've been manually editing photos in Photoshop or Canva, or just living with the ugly grid.

**[Smart Image Resize](https://sirplugin.com/)** fixes this in one click. It makes every product image the same size — without cutting off any part of your product.

No Photoshop. No manual work. No cropping.

### How it works (30 seconds to set up)

1. Install and activate — it starts working immediately
2. Upload a product image — it's automatically resized to match your store's dimensions
3. Extra space? Filled with a clean background color (white by default)

That's it. Every image comes out the same size, perfectly centered, nothing cut off.

### Who is this for?

* **Dropshippers** — your suppliers send you images in 50 different sizes. This makes them all uniform automatically.
* **Multi-vendor stores** — vendors upload whatever they want. The plugin fixes it behind the scenes.
* **Agencies** — install it on client stores so they can't mess up their product grid no matter what they upload.
* **Any WooCommerce store** — if your product grid looks uneven, this is the fix.

### Already have hundreds or thousands of images uploaded?

No problem. The built-in bulk tool processes your existing catalog. Hit one button and it fixes everything you've already uploaded.

### What you get (Free)

* Process up to 150 images
* Automatic resizing on upload
* Bulk resize existing images
* Whitespace trimming (removes empty space around products)
* Protection against stretching small images
* Works immediately — no configuration needed

### What you get with Pro

* **Unlimited images** — no processing cap, no matter how big your catalog
* **Smaller file sizes** — converts PNG to JPG and generates WebP automatically
* **Watermarking** — add your logo to protect your photos
* **Priority support** — get help fast via chat or email

[See Pro features →](https://sirplugin.com?utm_source=wp&utm_medium=link&utm_campaign=readme)

### Works with any WooCommerce setup

Compatible with popular themes like Flatsome, Avada, Astra, OceanWP, and more. Works alongside multivendor plugins (Dokan, WCFM, WC Vendors), image optimization plugins (Smush, ShortPixel, Imagify, EWWW), and page builders. If it runs WooCommerce, it works.

### Real reviews from store owners

★★★★★
> "After 3 minutes I bought the PRO version. We have 30,000 imported products with different photos." — [@prokurent](https://wordpress.org/support/topic/excellent-8052/)

★★★★★
> "Customers don't care to crop their images on their own. This saved a ton of tedious work." — [@jpontinen](https://wordpress.org/support/topic/did-its-job-and-saved-a-ton-of-tedious-work/)

★★★★★
> "The time saving benefits are enormous and support is A+." — [@chickwithbob](https://wordpress.org/support/topic/brilliant-lifesaver-with-incredible-support/)


### Explore Our Other plugins:
* Sell to businesses? [CrewCommerce](https://crewcommercepro.com/?utm_source=wordpress.org&utm_medium=readme&utm_campaign=smart-image-resize) adds company accounts to WooCommerce: team logins, monthly spending limits, and approval from the customer's own manager before an order is paid.
* [HurryTimer](https://hurrytimer.com) – Countdown timers for WooCommerce. Create real urgency on product pages and increase sales.
* [Conditional Add to Cart](https://condicart.com) – Show, hide, or replace the Add to Cart button based on user role, stock, schedule, or custom rules.
* [ReThumbify](https://rethumbify.com) – Regenerate thumbnails in the background with features like pause/resume, selective regeneration, cleanup of old or unused thumbnails, and more.

== Installation ==

1. Upload the `smart-image-resize` folder to `/wp-content/plugins/`
2. Activate the plugin through the Plugins menu
3. Go to **WooCommerce > Smart Image Resize** — it works immediately with smart defaults

**Requirement:** PHP `fileinfo` extension must be enabled (most hosts have this on by default).

== Frequently Asked Questions ==

= Do I need to configure anything? =

No. Install it, activate it, done. It starts working on your next image upload. If you want to tweak settings later, they're at WooCommerce > Smart Image Resize > Settings.

= Will it cut off parts of my product photos? =

Never. This plugin does NOT crop. It fits your image inside the target size and fills any leftover space with a background color (white by default). Your whole product stays visible.

= I already have hundreds of product images. Can it fix those too? =

Yes. Go to WooCommerce > Smart Image Resize > Bulk Resize, click Start, and it processes everything you've already uploaded. No need to re-upload anything.

= My images look blurry. What's wrong? =

This usually means one of two things:

1. **Your original image is too small** — the plugin had to stretch it. Fix: turn on "Disable Upscaling" in Settings > Advanced. Small images will keep their original size instead of being stretched.
2. **Compression is too aggressive** — Fix: go to Settings > Optimization and lower the compression level.

After fixing either of these, run the bulk resize tool again to reprocess.

= I ran the plugin but my images still look the same on my site. =

You're seeing cached (old) versions. You need to clear:

1. Your browser cache (Ctrl+Shift+R or Cmd+Shift+R)
2. Any caching plugin you use (WP Rocket, LiteSpeed, W3 Total Cache, etc.)
3. Your CDN cache if you use one (Cloudflare, etc.)

Once caches are cleared, you'll see the updated images.

= Some images look fixed but others don't. Why? =

Your theme might be showing the full-size original image instead of the resized version. Fix: go to Settings > Advanced and turn on "Resize Original Image." Then run the bulk tool again.

= The bulk tool stopped or froze. Is something broken? =

No. Your server ran out of memory on a large image. Just refresh the page and click Start again — it picks up where it left off. If it keeps happening, ask your hosting provider to increase PHP memory (512MB minimum recommended).

= Can I undo everything and go back to my original images? =

Yes. Go to the Help tab and use the bulk-restore tool. It reverts all images to their original state. Nothing is permanently destroyed.

= Does it touch my blog images, logos, or banners? =

No. It only processes WooCommerce product images. Your other images are left alone. (You can optionally enable it for category images in settings.)

= I use Dokan / WCFM / another multivendor plugin. Will it work? =

Yes. Images uploaded by vendors are processed the same way. No extra setup needed.

= Can I use it outside WooCommerce? =

Yes. [See our documentation](https://docs.sirplugin.com/faqs/general-questions#can-i-use-the-plugin-to-resize-non-product-images-and-how) for instructions.

= Where do I get help if something isn't working? =

* **Free users:** [WordPress.org support forum](https://wordpress.org/support/plugin/smart-image-resize/) — we respond to every thread.
* **Pro users:** [Priority support](https://sirplugin.com/support) — faster response via chat or email.

= What happens if I deactivate or uninstall the plugin? =

Your resized images stay resized — they're real files on your server, not temporary. In most cases everything continues to look fine. However, some themes load images in a special way that requires the plugin to be active. If images look off after deactivating, just reactivate and they'll be fine again. The plugin is lightweight and uses zero resources when no images are being uploaded.

== Screenshots ==

1. Before and after — messy product grid turned into clean, uniform images.
2. Settings — simple options, no learning curve.
3. Bulk resize — fix your entire catalog with one click.
4. Bulk resize in progress — sit back while it works.
5. Whitespace trimming — removes empty space around your product.


== Changelog ==

= 1.17.1 =

* Security hardening.

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

= 1.17.1 =
Security hardening.

= 1.17.0 =
New: Process original images, bulk-restore, and refreshed admin UI.
