<?php
namespace WP_Smart_Image_Resize\Plugin_Support;

/*
|--------------------------------------------------------------------------
| Regenerate Thumbnails compatibility (kept for sites that still have it).
|
| The plugin no longer depends on Regenerate Thumbnails for bulk processing.
| The built-in Bulk Processor (Bulk_Processor.php) handles that natively.
|
| We keep this file so that if a user still has RT installed, it continues
| to work correctly with Smart Image Resize.
|--------------------------------------------------------------------------
*/

// Force RT to regenerate ALL thumbnails (not just missing ones) so our
// filters are applied to every image when RT is used manually.
add_filter('regenerate_thumbnails_options_onlymissingthumbnails', '__return_false');
