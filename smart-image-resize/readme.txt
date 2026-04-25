=== Smart Image Resize - Make WooCommerce Images the Same Size ===
Contributors: nlemsieh
Tags: image resize,uniform images,same size,woocommerce image resize,product image resize
Requires at least: 4.0
Tested up to: 6.9
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Requires PHP: 7.0
Stable tag: 1.16.0

Make WooCommerce product images the same size and uniform without cropping. No more manual image editing and photo resizing. No configuration required.

== Description ==

[Smart Image Resize](https://sirplugin.com/) makes your store look professional with product images that are all uniform and the same size without cropping. No configuration required.
 
It's the **#1** tool for **WooCommerce product image uniformity** and is essential for stores with large catalogs or mixed-size image sources.

### Key Benefits

* **Uniform Sizing:** Automatically makes all WooCommerce product images the same size and aspect ratio, eliminating messy, inconsistent product grids.
* **No More Cropping Headaches:** Preserves the entire product within the image frame by adding a neutral background — no important parts of your product ever get cut off.
* **Whitespace Trimming:** Trims unwanted white space around the product to keep it centered and prominent before resizing.
* **Bulk Processing:** Resize and fix your entire existing catalog.
* **Performance Ready:** Compress thumbnails and generate only necessary image sizes to boost your store's loading speed.

### Perfect for:

* WooCommerce stores with mixed product image sizes
* Dropshipping or supplier-imported product images
* Large catalogs (10,000+ products) needing automated image processing
* Multivendor marketplaces where sellers upload images in different sizes (Dokan, WCFM, etc.)
* Stores migrating themes and needing standard-size WooCommerce images
 
### 🛠️ Free Features

* ✅ Automatically resize and process new WooCommerce product images on upload
* ✅ Bulk resize up to 150 existing product images
* ✅ Trim unwanted whitespace to keep products centered and clean
* ✅ Add a custom background color to match your brand
* ✅ Compress thumbnails to boost site loading speed
* ✅ Generate only necessary thumbnails and remove unused ones to save disk space
* ✅ Select specific images to resize for more control
 
### 🔥 Pro Features

* **♾ No limits** – Resize and optimize unlimited WooCommerce product images
* **✈️ PNG to JPG auto conversion** – Reduce file size, keep quality
* **🚀 WebP image support** – Serve next-gen WebP images for faster loading and better transparency support
* **🔒 Watermark protection** – Automatically add watermarks to your resized images
* **🛟 Priority support** – Get fast, dedicated support via chat or email
 
[Check out Smart Image Resize PRO!](https://sirplugin.com?utm_source=wp&utm_medium=link&utm_campaign=lite_version)


= Here's What Our Users Are Saying =

★★★★★
> "I am so impressed with this plugin. I never bother writing plugin reviews but this plugin blew my mind. Definitely upgrade." - [@buttonmode](https://wordpress.org/support/topic/best-plugin-that-solved-all-my-image-issues/)

★★★★★
> "I downloaded the free version and after 3 minutes I bought the PRO version. The plugin is EXCELLENT! For a year I didn't know what to do with WooCommerce photos, because we have 30,000 imported products with different photos." - [@prokurent](https://wordpress.org/support/topic/excellent-8052/)

★★★★★
> "I recommended this to a dev friend the day after I used it, he used it as well. (We both ended up getting the Pro version to leave it on, customers don't care to crop their images on their own tbh)." - [@jpontinen](https://wordpress.org/support/topic/did-its-job-and-saved-a-ton-of-tedious-work/)

★★★★★
> "[••]The time saving benefits are enormous and the plugin support is A+. They have a chat that helps you solve any issues immediately." - [@chickwithbob](https://wordpress.org/support/topic/brilliant-lifesaver-with-incredible-support/)

### Usage

1. In your WordPress dashboard, go to **WooCommerce > Smart Image Resize > Bulk Regenerate**.
2. Click the **Start Processing** button to begin resizing your existing images.
Feel free to adjust the settings by going to **WooCommerce > Smart Image Resize > Settings**
For more details, [see our documentation](https://docs.sirplugin.com?utm_source=wp&utm_medium=link&utm_campaign=lite_version).

**Note:** Newly uploaded product images are automatically resized — no extra steps needed.

## Explore Our Other plugins:
[HurryTimer](https://wordpress.org/plugins/hurrytimer/) – A powerful countdown timer to create urgency and drive sales
[ReThumbify](http://rethumbify.com/) – A new tool to regenerate thumbnails in the background, with pause/resume functionality, old thumbnails cleanup, and selective regeneration.

== Installation ==

1. Upload `smart-image-resize` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

 _**Note:** Make sure PHP Fileinfo extension is enabled in you server._

 == Frequently Asked Questions ==

= My product images are showing in different sizes, will the plugin make them uniform without cropping? =

Yes, that's exactly what the plugin does. It makes all your product images the same size by adding a background color around them instead of cropping. Your products stay fully visible and your store looks professional.

= Does the plugin automatically resize images on upload? =

Yes. Once activated, every new product image you upload is automatically resized to match your store's image sizes. No extra steps needed.

= Do I need to configure anything before the plugin starts working? =

No configuration needed. The plugin works immediately after activation with smart defaults. However, you can customize the background color, enable whitespace trimming, or choose which image sizes to process by going to **WooCommerce > Smart Image Resize > Settings**.

= How do I resize already-uploaded product images? =

Go to **WooCommerce > Smart Image Resize > Bulk Regenerate** and click **Start Processing**. The tool will resize all your existing product images automatically.

**Note:** If old images still appear after processing, clear your site's cache (caching plugin, CDN, and browser cache).

= My images look blurry or low quality after resizing. What should I do? =

There are two common causes:

1. **Compression is too high** — Go to **WooCommerce > Smart Image Resize > Settings**, open the **Optimization** section, and lower the compression level. A lower value means better quality.
2. **The original image is too small** — When an image is smaller than the target size, the plugin stretches it to fit, which causes blurriness. You can turn on **Disable Upscaling** under **Settings > Advanced settings** to prevent stretching — the image will be left at its original size and padding will be added around it instead. Alternatively, upload a larger version of the image.

After making any changes, run the bulk regenerate tool to reprocess your images.

= The plugin processed my images but they still look different on the frontend. Why? =

This is almost always a caching issue. After processing, clear your site's cache (caching plugin, CDN, or browser cache) and reload the page. The updated images should appear correctly.

= Images aren't being resized on upload. What could be wrong? =

A few things to check:

1. Make sure **Image Uniformity** is enabled in **Settings**.
2. Make sure the PHP `fileinfo` extension is enabled — your hosting provider can confirm this.
3. If images still aren't processing, try switching the image processor under **WooCommerce > Smart Image Resize > Help > Image Processing**. Switching between the available options can resolve compatibility issues on some servers.

= What background color is used by default, and how do I change it? =

White is the default background color. To change it, go to **WooCommerce > Smart Image Resize > Settings** and select a color under **Background Color**. Leave it empty to keep transparency for PNG images.

= The bulk tool stopped partway through. What happened? =

Your server ran out of memory while processing large images. Refresh the page and click **Start Processing** again — the tool automatically resumes where it stopped. If this keeps happening, contact your hosting provider to increase the server's memory limit.

= Can I undo changes? =

Yes. Turn off **Image Uniformity** in **Settings**, then go to **WooCommerce > Smart Image Resize > Bulk Regenerate** and run the tool. This regenerates all thumbnails using the original WordPress sizing.

= Which image sizes does the plugin process? =

By default, the plugin processes all standard WooCommerce image sizes (thumbnail, medium, woocommerce_thumbnail, woocommerce_single, etc.). You can customize which sizes to include or exclude under **Settings > Advanced settings > Image Sizes**.

= I need to change the default WooCommerce sizes, is it possible? =

Yes. Go to **WooCommerce > Smart Image Resize > Settings** to customize the default WooCommerce image dimensions.

= Will this affect non-product images? = 

No. By default, the plugin only processes product images. You can enable category images in the plugin settings if needed.

= Can I use the plugin to resize non-product images as well? =

Yes. [Here's how to enable resizing for other image types.](https://docs.sirplugin.com/faqs/general-questions#can-i-use-the-plugin-to-resize-non-product-images-and-how)

= How can I know which images have been resized? =

In your Media Library, use the filter dropdown and select **"Smart Resize: Processed"** to view only images that have been resized by the plugin.

= I get an error when I upload an image =

Make sure the PHP `fileinfo` extension is enabled on your server. Contact your hosting provider if you need help enabling it.

= Is the plugin compatible with Dokan Multivendor? =

Yes. Vendors can upload product images and the plugin will automatically resize them.

= Is the plugin compatible with WooCommerce HPOS? =

Yes. The plugin is fully compatible with WooCommerce High-Performance Order Storage (HPOS).

= Is the plugin compatible with WP CLI? =

Yes. Use the command `wp media regenerate` to bulk resize your existing images via command line.

= Still have questions? =

Check our [complete FAQ documentation](https://docs.sirplugin.com/faqs) for more answers.

= How do I get support? =

**Free version:** [Create a support ticket](https://wordpress.org/support/plugin/smart-image-resize/) on the WordPress.org forum.

**Pro version:** [Contact our priority support team](https://sirplugin.com/contact.html) for faster assistance.


== Screenshots ==

1. Before and after using the plugin.
2. Settings page.
3. Select sizes to generated.
4. Add custom background color of the new area.

== Changelog ==

= 1.16.0 =

* New: Built-in bulk regenerate tool.
* Improved: Refreshed admin UI for a better experience.

= 1.15.1 =

* Various improvements and bugfixes. 
* Declared compatibility with WooCommerce v10.6 

= 1.15.0 =

* Added support for product brands
* Various improvements and bugfixes. 

= 1.13.1 =

* Declared compatibility with WooCommerce 10.3

= 1.13.0 = 

* Enhanced the "Bulk Regenerate Images" page for better user experience.
* Various improvements and bugfixes.

= 1.12.1 = 

* Admin tweaks for better user experience.

= 1.12.0 = 

* Introduced a new filter `wp_sir_exclude_trim_sizes` that allows excluding certain image sizes from the whitespace trimming functionality.
* Added support for AVIF format.
* Admin tweaks for better user experience.
* Fixed a compatibility issue with the new version of the Phlox theme.
* Various improvements and bugfixes.

= 1.10.2 = 

* Various improvements and bugfixes.

= 1.10.0 = 

* Added support for Phlox theme.
* Added an option to prevent upscaling of small images.
* Introduced a dedicated "Help" tab featuring setup guides and troubleshooting resources.
* Addressed an issue with some thumbnail regeneration plugins not using the edited version of images modified in WordPress's built-in image editor
* Enhanced the settings page to improve user experience.
* Process image when `set_post_thumbnail` is called.
* Improved compatibility with PHP 8.3
* Various minor bugfixes and stability improvements

= 1.8.1 = 

* Declare compatibility with custom order tables for WooCommerce.

= 1.8.0 = 

* Added a new experimental setting "Cropping mode". To enable it, add the filter: `add_filter('enable_experimental_features/crop_mode', '__return_true' );`


= 1.7.7 =

* Improved compatibility with new themes and plugins
* Fixed an issue with the Trim whitespace's border size option not working properly in GD. 
* Fixed an issue in v1.7.6 causing some plugins' assets to not load properly.
* Declare compatibility with WooCommerce 6.9
* Minor bugfixes

= 1.7.6 =

* Deleted the option "Use WordPress cropping" as it seems to be causing some confusion for many users. To prevent specific sizes from being resized by the plugin use the filter `wp_sir_exclude_sizes` to return an array of size names you want to exclude.
* Fixed an issue with WebP files not deleted when the WebP feature is turned off.
* Declared compatibility with WooCommerce 6.3
* Added a work-around to fix a bug in Regenerate Thumbnails causing the latter to interfere with WPML.
* Stability improvements

= 1.7.5.3 =

* Fix a bug when background processing is trigged from the frontend.

= 1.7.5.2 =

* bugfixes

= 1.7.5 =

* Recheck and process skipped images in the background after the parent post is saved.
* Replace "Resize fit mode" option with "Use WordPress cropping".
* Fix issue with Trimming border size limited to original image size.
* Improve CMYK images handling
* Format error message in WP CLI and avoid halting execution.
* Fix an issue with CMYK profile not being converted to RGB in Imagick.
* Use another image processor as fallback when current one doesn't support WebP.
* Fix an issue with default image processor when Imagick doesn't support WebP. 
* Minor bugfixes 
* Stability improvement
* Performance improvement.

= 1.6.2 =

* Use another image processor as fallback when current one doesn't support WebP.
* Fix WebP Images not served in Ajax responses
* Fix an issue with default image processor when Imagick doesn't support WebP. 

= 1.6.1 =

* Add the ability to custom woocommerce default sizes.
* Stability improvement

= 1.6.0 =

* Add the ability to specify the resize fit mode for each size. 
* Stability improvement

= 1.5.5.1 =

* Stability improvement

= 1.5.5 =

* Fix color issue with some CMYK images.
* Fix faded images in some Imagick installs.

= 1.5.4 =

* Fix an issue with some themes not loading the correct image size.

= 1.5.3 =

* Stability improvement

= 1.5.2 =

* Fix thumbnail overwriten by WordPress when original image and thumbnail dimensions are identical
* Fix an issue with Flatsome using full size image instead of woocommerce_single for lazy load.
* Ignore sizes with 9999 dimension (unlimited height/width).
* Improve WebP availability detection.

= 1.5.1 =

* Use Imagick as default when available.
* Fix Avada not serving correct thumbnails on non-WooCommerce pages.
* Improve the user experience of the settings page. 


= 1.5.0 =

* Filter processed images in the media library toolbar
* Add filter `wp_sir_serve_webp_images`
* Improve Whitespace trimming tool  


= 1.4.10 =

* Declare compatibility with WooCommerce (v5.2)


= 1.4.9 =

* Use GD extension by default to process large images.


= 1.4.8 =

* Fixed an issue with some images in CMYK color.

= 1.4.7 =

* Fixed an issue with PNG-JPG conversion conflict
* Added support for WCFM plugin.
* Declared compatibility with WooCommerce (v5.0)
* Stability improvement


= 1.4.6 =

* Added tolerance level setting to trim away colors that differ slightly from pure white.
* Improved unwanted/old thumbnails clean up.


= 1.4.5 =

* Added compatibility with WooCommerce 4.9.x
* Stability improvement.

= 1.4.4 =

* Improved bulk-resizing using Regenerate Thumbnails plugin.
* Stability improvement.

= 1.4.3 =
* Fixed a minor issue with JPG images quality when compression is set to 0%.
* Stability improvement.

= 1.4.2.7 =
* Fixed an issue with UTF-8 encoded file names.

= 1.4.2.6 =

* Improved compatibility with WC product import tool.

= 1.4.2.5 =

* Fixed an issue when uploading non-image files occured in the previous update.


= 1.4.2.3 =

* Turned off cache busting by default.

= 1.4.2.2 =

* Fixed WebP images not loading in some non-woocommerce pages.

= 1.4.2.1 =

* Fixed trimming issue for some image profiles (Imagick).
* Added an option to specify trimmed image border.


= 1.4.2 =

* [Fixed] an issue with WebP images used in Open Graph image (og:image).
* Stability improvement

= 1.4.1 =

* Fixed a bug with WebP not installed on server.
* Fixed an issue with front-end Media Library.


= 1.4.0 =

* Added support for category images.
* Ability to decide whether to resize an image being uploaded directly from the Media Library uploader.
* Support for WooCommerce Rest API
* Developers can use the boolean parameter `_processable_image` to upload requests to automatically process images.
* Added filter `wp_sir_maybe_upscale` to prevent small images upscale.
* Process image attachment with valid parent ID.
* Fixed a tiny bug with compression only works for converted PNG-to-JPG images.
* Fixed an issue with srcset attribute caused non-adjusted images to load.
* Fixed an issue with trimmed images stretched when zoomed on the product page.
* Improved support for bulk-import products.
* Improved processing performances with Imagick.

= 1.3.9 =

* Fix compatibility issue with Dokan vendor upload interface.
* Performances improvement.

= 1.3.8 =

 * Added compatibility with WP 5.4
 * Added support for WP Smush
 * Stability improvement.

= 1.3.7 =

 * Stability improvement.


= 1.3.6 =

 * Fix a minor issue with image parent post type detection.
 * Added a new filter `wp_sir_regeneratable_post_status` to change regeneratable product status. Default: `publish`

= 1.3.5 =

 * Regenerate thumbnails speed improvement.


= 1.3.4 =

 * Stability improvement

= 1.3.3 =

 * fixed a minor issue with settings page.

= 1.3.2 =
 * Added thumbnails regeneration steps under "Regenerate Thumbnails" tab.

= 1.3.1 =
 * Fixed a minor bug in Regenerate Thumbnails tool.

= 1.3 =
 * Added a built-in tool to regenerate thumbnails.
 * woocommerce_single size is now selected by default.
 * Stability improvement.

= 1.2.4 =
 * Fix srcset images not loaded when WebP is enabled.

= 1.2.3 =
 * Set GD driver as default.
 * Stability improvement.

= 1.2.2 =
 * Prevent black background when converting transparent PNG to JPG.
 * Fixed random issue that causes WebP images fail to load.
 * Stability improvement.

= 1.2.1 =
* Added settings page link under Installed Plugins.

= 1.2.0 =
* Added Whitespace Trimming feature.
* Various improvements.

= 1.1.12 =

* Fixed crash when Fileinfo extension is disabled.

= 1.1.11 =

* Added support for Jetpack.

= 1.1.10 =

* Fixed conflict with some plugins.

= 1.1.9 =

* Prevent dynamic resize in WooCommerce.

= 1.1.8 =

* Handle WebP not installed.

= 1.1.7 =

* Fixed mbstring polyfill conflict with WP `mb_strlen` function


= 1.1.6 =
* Added polyfill for PHP mbstring extension

= 1.1.5 =
* Force square image when height is set to auto.

= 1.1.4 =
* Fixed empty sizes list

= 1.1.3 =
* Fixed empty sizes list

= 1.1.2 =

* Added settings improvements
* Added processed images notice.

= 1.1.1 =

* Added fileinfo and PHP version notices
* Improved settings page experience.

= 1.1.0 =

* Introducing Smart Image Resize Pro features
* Various improvements

= 1.0.13 =

* Fixed some images not resized correctly.

= 1.0.12 =

* Minor bugfix

= 1.0.11 =

* Errors messages now are displayed in media uploader. This will help debug occured problems while resizing.

= 1.0.10 =

* The PHP Fileinfo extension is required. Now you can see notice when it isn't enabled.

= 1.0.9 =

* Fixed bug that prevents upload of non-image files to the media library.

= 1.0.8 =

* Skip woocommerce_single resize

= 1.0.7 =

* Stability improvement

= 1.0.6 =

* Bugfix


= 1.0.5 =

* Bugfix

= 1.0.4 =

* Removed deprecated option.

= 1.0.3 =

* Small images resize improvement.

= 1.0.2 =

Improve stability

= 1.0.1 =

- Add ability to add custom color in settings.
- Fixbug for some PHP versions.

= 1.0.0 =

* Public Release

 == Upgrade Notice ==

  = 1.6.0 =

* Added the ability to use a specific resizing mode for each size.