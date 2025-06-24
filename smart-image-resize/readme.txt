=== Smart Image Resize - Make WooCommerce Images the Same Size ===
Contributors: nlemsieh
Tags: woocommerce, product images, image resize, image cropping, uniform images, auto resize, resize product images, smart image resize, webp, png to jpg, product image alignment, ecommerce images
Requires at least: 4.0
Tested up to: 6.8
License: GPLv3
License URI: http://www.gnu.org/licenses/gpl.html
Requires PHP: 5.6
Stable tag: 1.13.0

Automatically make WooCommerce product images the same size, aligned, and consistent — no manual editing needed.

== Description ==

[Smart Image Resize](https://sirplugin.com/) automatically resizes and aligns your product images the moment they're uploaded — no manual cropping or editing required.

Whether you or your clients/vendors upload product photos, this plugin automatically ensures all WooCommerce product images share the same size and perfect alignment, creating a clean, professional, and consistent storefront.

No more uneven product grids, inconsistent image sizes, distracting whitespace, or important parts of your images getting cut off. Smart Image Resize handles it automatically as images are uploaded, saving you time and effort.

### 💡 Key Benefits

- Automatically resize product images to uniform size without cropping or cutting off important parts
- Trim whitespace and center images for consistent presentation
- Maintain consistent image size and alignment across your store
- No manual editing or cropping required
- Simplifies theme switching with standard image sizes
- Processes images on upload and during imports.

### Perfect for:

- WooCommerce stores with mixed image sizes  
- Dropshipping or supplier-imported images
- Large catalogs (10,000+ products) needing automation
- Multivendor marketplaces where sellers upload images in different sizes (Dokan, WCFM, etc.)
- Stores migrating themes and needing standard-size images

### 🛠️ Free Features

- ✅ Automatically resize up to 150 product images  
- ✅ Remove unwanted white space to keep products centered and clean  
- ✅ Add background color to match your brand  
- ✅ Compress thumbnails to boost site loading speed  
- ✅ Generate only necessary thumbnails and remove unused ones  
- ✅ Select specific images to resize for more control

### 🔥 Pro Features

- **♾ No limits** – resize unlimited product images.
-  **✈️ PNG to JPG auto conversion** – reduce size, keep quality.
- **🚀 WebP image support** – load faster, retain transparency
- **🔒Watermark protection** – keep your images safe and branded
- **🛟 Priority support** - get fast help via chat or email

[Upgrade to Smart Image Resize PRO!](https://sirplugin.com?utm_source=wp&utm_medium=link&utm_campaign=lite_version)


### Usage

Smart Image Resize automatically resizes new product images on upload. To resize already uploaded images, follow these steps:

1. In your WordPress dashboard, go to **WooCommerce > Smart Image Resize > Bulk Regenerate Images**.
2. Click "Install Regenerate Thumbnails" button.
3. Go to **Tools > Regenerate Thumbnails**.
4. Click "Regenerate Thumbnails For All Attachments" button.

Feel free to adjust the settings by going to **WooCommerce > Smart Image Resize**

For more details, [see our documentation](https://sirplugin.com/guide.html?utm_source=wp&utm_medium=link&utm_campaign=lite_version).

## Explore Our Other plugins:
[HurryTimer](https://wordpress.org/plugins/hurrytimer/) – A powerful countdown timer to create urgency and drive sales
[ReThumbify](http://rethumbify.com/) – A new tool to regenerate thumbnails in the background, with pause/resume functionality, old thumbnails cleanup, and selective regeneration.

== Installation ==

1. Upload `smart-image-resize` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress

 _**Note:** Make sure PHP Fileinfo extension is enabled in you server._

 == Frequently Asked Questions ==

= My product images are showing in different sizes, will the plugin make them uniform without cropping? =

Indeed, addressing this issue is the primary purpose of our plugin.

= Does the plugin automatically resize images on upload? =

Yes. 

= How do I resize already-uploaded product images? =

To resize existing images, follow these steps:

1. Install [Regenerate Thumbnails plugin](https://wordpress.org/plugins/regenerate-thumbnails).
2. Navigate to **Tools > Regenerate Thumbnails**.
3. Click on the **Regenerate Thumbnails For All Attachments** button.

**NOTE** If old images are still appearing, be sure to clear your cache.

= Will this affect non-product images? = 

By default, the plugin only resizes product images. However, you have the option to enable resizing for category images in the plugin settings.

= I need to change the default WooCommerce sizes, is it possible? =

Yes. To change the default WooCommerce sizes, navigate to WooCommerce → Smart Image Resize → Settings in your WordPress dashboard.

= Is the plugin compatible with Dokan Multivendor? =

Yes.

= Can I undo changes? =

Absolutely yes! to revert changes, deactivate the plugin and run the Regenerate Thumbnails tool.

= How can I know which images have been resized? =

To view resized images, apply the filter "Smart Resize: Processed" in your Media Library.

= Is the plugin compatible with WooCommerce HPOS? =

Yes, the plugin is fully compatible with WooCommerce HPOS (High-Performance Order Storage).

= Is the plugin compatible with WP CLI? =

Yes. You can use the command `wp media regenerate` to resize your existing images.

= Can I use the plugin to resize non-product images as well? =

Yes, you can use the plugin to resize non-product images as well. [Here's how.](https://docs.sirplugin.com/faqs/general-questions#can-i-use-the-plugin-to-resize-non-product-images-and-how)

= I get an error when I upload an image =

If you encounter an error when uploading an image, ensure that the PHP `fileinfo` extension is enabled on your server.

= Still have questions? =

If you can't find the answer to your question, we may have posted it in [our FAQ](https://docs.sirplugin.com/faqs)

= How do I get support? =

If you have trouble with the plugin, [create a ticket](https://wordpress.org/support/plugin/smart-image-resize/) on the support forum and we'll make sure to look into it. 

If you are a pro user, [contact the support](https://sirplugin.com/contact.html) on the official plugin site.


== Screenshots ==

1. Before and after using the plugin.
2. Settings page.
3. Select sizes to generated.
4. Add custom background color of the new area.

== Changelog ==

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