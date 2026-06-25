<?php

namespace WP_Smart_Image_Resize;

use Exception;
use WP_Smart_Image_Resize\Exceptions\Invalid_Image_Meta_Exception;
use WP_Smart_Image_Resize\Image_Filters\Thumbnail_Filter;
use WP_Smart_Image_Resize\Image_Filters\Trim_Filter;
use WP_Smart_Image_Resize\Image_Filters\CreateWebP_Filter;
use WP_Smart_Image_Resize\Image_Filters\Watermark_Filter;
use WP_Smart_Image_Resize\Image_Filters\Pad_Original_Filter;
use WP_Smart_Image_Resize\Utilities\Backup;
use WP_Smart_Image_Resize\Utilities\File;

/*
 * Class WP_Smart_Image_Resize\Image_Editor
 *
 * @package WP_Smart_Image_Resize\Inc
 * 
 */

defined('ABSPATH') || exit;

if (!class_exists('\WP_Smart_Image_Resize\Image_Editor')) :

    final class Image_Editor {
        use Processable_Trait;
        use Runtime_Config_Trait;

        /**
         * @var \WP_Smart_Image_Resize\Image_Editor
         */
        protected static $instance = null;


        /**
         * @return \WP_Smart_Image_Resize\Image_Editor
         */
        public static function get_instance() {
            if (is_null(static::$instance)) {
                static::$instance = new self;
            }

            return static::$instance;
        }

        /**
         * Register hooks.
         */
        public function run() {

            // A low priority < 10 to let plugins optimize thumbnails.
            add_filter('wp_generate_attachment_metadata', [$this, 'processImage'],9, 2);

            add_filter('wp_update_attachment_metadata', [$this, 'recheck_subsizes'], 10, 2);
            // Force 1:1 size for single product thumbnail.
            // @see  force_square_woocommerce_single()
            add_filter('woocommerce_get_image_size_single', [$this, 'forceSquareWooCommerceSingle']);

            // Force woocommerce single on single product page.
            // @see force_woocommerce_single()
            add_filter('woocommerce_gallery_image_size', [$this, 'forceWooCommerceSingle'], PHP_INT_MAX);

        }

       

        /**
         * Determine whether the given size is selected.
         *
         * @param array $sizes
         *
         * @return bool
         */
        public function isProcessableSize($sizes) {
            if (!is_array($sizes)) {
                $sizes = (array)$sizes;
            }

            $selected_sizes = apply_filters('wp_sir_sizes', wp_sir_get_settings()['sizes']);

            return count(array_intersect($sizes, $selected_sizes)) === count($sizes);
        }

        /**
         * Use 1:1 for single size when selected.
         *
         * @param string|array
         *
         * @return array
         */


        public function forceSquareWooCommerceSingle($size) {
            if (!$this->isProcessableSize(['woocommerce_single', 'shop_single'])) {
                return $size;
            }

            // If height is not set, make it square.
            if ($size['width'] && !$size['height']) {
                $size['height'] = $size['width'];
            }

            return $size;
        }

        /**
         * Force woocommerce_single size on single product page.
         *
         * @hook woocommerce_gallery_image_size
         *
         * @param string $size
         *
         * @return string
         */
        public function forceWooCommerceSingle($size) {
            if (!apply_filters('wp_sir_force_woocommerce_single', true)) {
                return $size;
            }

            if ($this->isProcessableSize(['woocommerce_single', 'shop_single'])) {
                return 'woocommerce_single';
            }

            return $size;
        }

        function recheck_subsizes($metadata, $image_id) {
            $stored_meta = wp_get_attachment_metadata($image_id);

            // Check if the full size image has been edited
            if (isset($metadata['file']) && isset($stored_meta['file'])) {
                $new_basename = wp_basename($metadata['file']);
                $old_basename = wp_basename($stored_meta['file']);
                
                // Check if basenames are different and new file ends with -e followed by 13 digits
                if (($new_basename !== $old_basename && preg_match('/-e\d{13}\.[^.]+$/', $new_basename)) || preg_match('/-e\d{13}\.[^.]+$/', $old_basename)) {
                    $new_metadata = $this->processImage($metadata, $image_id);
                    if(is_array($new_metadata) && !empty($new_metadata)) {
                        return $new_metadata;
                    }
                }
            }

            return $metadata;
        }

        /**
         * Proceed image editing and thumbnails generation.
         *
         * @param array $metadata
         * @param int $imageId
         *
         * @return array
         */

        public function processImage($metadata, $imageId) {


            try {

                // Get global settings.
                $settings = wp_sir_get_settings();

                // Bail if resizing is disabled.
                if (!$settings['enable']) {
                    return $metadata;
                }

                
                if (Process_Tracker::has_reached_limit()) {
                    return $metadata;
                }
                

                // Wrap the current image metadata.
                // This allows manupilating metadata easier.
                // TODO: Remove in v2.0.
                $imageMeta = new Image_Meta($imageId, $metadata);

                // Check whether the current image is processable.
                // By default, the plugin does only process specified images.
                if (!$this->isProcessable($imageId, $imageMeta)) {
                    return $metadata;
                }

                // ------------------------------------------------------------------
                // If this image was previously processed, restore the original from
                // backup before re-processing. This prevents compression stacking,
                // double watermarks, and trimming artifacts from accumulating.
                // Also cleans up old WebP and converted files from the previous run.
                // ------------------------------------------------------------------
                $backup = new Backup();
                $original_file = $imageMeta->getOriginalFullPath();

                // If PNG→JPG conversion was previously applied, _wp_attached_file
                // points to the .jpg. The backup was created from the original .png.
                // Check for the stored pre-conversion path to find the correct backup.
                $pre_conversion_relative = get_post_meta( $imageId, '_sir_pre_conversion_file', true );
                if ( $pre_conversion_relative ) {
                    $uploads_dir         = trailingslashit( wp_get_upload_dir()['basedir'] );
                    $pre_conversion_file = $uploads_dir . $pre_conversion_relative;
                    if ( $backup->exists( $pre_conversion_file ) ) {
                        $original_file = $pre_conversion_file;
                    }
                }

                if ( get_post_meta( $imageId, '_processed_at', true ) && $backup->exists( $original_file ) ) {
                    // Clean up old thumbnails (including WebP and PNG→JPG variants).
                    $this->cleanup_old_files( $imageId, $metadata );

                    // If PNG→JPG was previously applied, delete the .jpg file before
                    // restoring the .png from backup.
                    if ( $pre_conversion_relative ) {
                        $current_jpg = get_attached_file( $imageId );
                        if ( $current_jpg !== $original_file && file_exists( $current_jpg ) ) {
                            @unlink( $current_jpg );
                        }
                    }

                    // Restore the original file from backup (copies backup over current).
                    // Note: Backup::restore() deletes the backup after copying.
                    // We re-create it later during processing, so this is fine.
                    $backup->restore( $original_file );

                    // If PNG→JPG was previously applied, reset the attached file to .png.
                    if ( $pre_conversion_relative ) {
                        update_attached_file( $imageId, $pre_conversion_relative );
                        // Re-read imageMeta with the corrected path.
                        $imageMeta = new Image_Meta( $imageId, $metadata );
                        delete_post_meta( $imageId, '_sir_pre_conversion_file' );
                    }
                }

                // TODO: Use WP_Image_Editor class instead.
                $imageManager = new Image_Manager();

                // Let's try to load the given image to memory,
                $image = $imageManager->make($imageMeta->getOriginalFullPath());

                // Correct image orientation according to Exif data
                try {
                    $image->orientate();
                } catch (\Exception $e) {
                    // Do nothing.
                }

                @set_time_limit(0);

                $imageMeta->setMimeType($image->mime());

                // ------------------------------------------------------------------
                // ------------------------------------------------------------------
                // The original is NOT padded before trimming — trimming happens
                // first so each image is measured from its actual content.
                // Padding is applied after trim to achieve the target aspect ratio
                // for the ORIGINAL FILE ONLY. Thumbnails use the trimmed image.
                // ------------------------------------------------------------------
                
                // Keep a reference to the pre-trim image for:
                // 1. Saving the original when process_original is off (don't trim the file on disk)
                // 2. Generating thumbnails excluded from trimming
                $_pretrim_image = clone $image;
                
                $exclude_trim_sizes = (array)apply_filters('wp_sir_exclude_trim_sizes', [], $imageId);
                $_untrimmed_image = ! empty( $exclude_trim_sizes ) ? $_pretrim_image : null;
                
                $image->filter(new Trim_Filter($imageMeta));

                // $image is now the trimmed version — used for ALL thumbnail generation.

                // ------------------------------------------------------------------
                // Pad the original image to the target aspect ratio AFTER trimming.
                // This only affects the saved original file, NOT thumbnails.
                // Controlled by the `process_original` setting (uniformity features).
                // ------------------------------------------------------------------
                $padded_original = null;
                if ( ! empty( $settings['process_original'] ) ) {
                    $original_path      = $imageMeta->getOriginalFullPath();
                    $pad_target_size    = _wp_sir_get_original_pad_target_size();
                    $pad_filter         = new Pad_Original_Filter( $pad_target_size );
                    $padded_original    = $pad_filter->applyFilter( clone $image );

                    $pad_w = $padded_original->getWidth();
                    $pad_h = $padded_original->getHeight();

                    $imageMeta->setMetaItem( 'width',  $pad_w );
                    $imageMeta->setMetaItem( 'height', $pad_h );
                    $imageMeta->setSizeData( 'full', [
                        'file'      => wp_basename( $original_path ),
                        'width'     => $pad_w,
                        'height'    => $pad_h,
                        'mime-type' => $image->mime(),
                    ] );
                }

                // ------------------------------------------------------------------
                // Determine whether tools (watermark, PNG→JPG, WebP, compression)
                // should be applied to the original/full-size image.
                //
                // By default this is ON — users expect these features to apply to
                // all images. Developers can disable via experimental filter:
                //   add_filter('wp_sir_experimental/apply_tools_to_original', '__return_false');
                // ------------------------------------------------------------------
                $apply_tools_to_original = (bool) apply_filters(
                    'wp_sir_experimental/apply_tools_to_original',
                    true,
                    $imageId,
                    $settings
                );

                $has_compression  = ! empty( $settings['jpg_quality'] ) && (int) $settings['jpg_quality'] > 0;
                $has_watermark    = ! empty( $settings['enable_watermark'] );
                $has_jpg_convert  = ! empty( $settings['jpg_convert'] );
                $has_webp         = ! empty( $settings['enable_webp'] );

                // Tools that modify the original file on disk.
                $tools_active = $apply_tools_to_original && ( $has_compression || $has_watermark || $has_jpg_convert );

                // ------------------------------------------------------------------
                // Save the original image to disk.
                //
                // The original is modified when:
                //   - process_original is ON (uniformity: padding/trimming saved to disk)
                //   - OR tools are active (watermark, compression, PNG→JPG)
                //   - OR WebP is enabled (need to save original to generate .webp copy)
                //
                // Backup is created before any destructive change.
                // ------------------------------------------------------------------
                $original_path    = $imageMeta->getOriginalFullPath();
                $modify_original  = ! empty( $settings['process_original'] ) || $tools_active || $has_webp;

                if ( $modify_original ) {
                    // Use padded version if uniformity padding is on, otherwise use the
                    // pre-trim image (original dimensions preserved on disk).
                    $original_to_save = $padded_original
                        ? clone $padded_original
                        : clone $_pretrim_image;

                    // Backup the original before any destructive changes.
                    $backup = new Backup();
                    if ( ! $backup->exists( $original_path ) ) {
                        $backup->create( $original_path );
                    }

                    // Compression quality: only apply extra compression if tools are
                    // enabled for the original. Otherwise save at full quality (100).
                    $quality = ( $apply_tools_to_original && $has_compression )
                        ? 100 - (int) $settings['jpg_quality']
                        : 100;



                    
                    $original_to_save->save( $original_path, $quality );
                    

                    // Update metadata dimensions.
                    $imageMeta->setMetaItem( 'width', $original_to_save->getWidth() );
                    $imageMeta->setMetaItem( 'height', $original_to_save->getHeight() );


                    $original_to_save->destroy();
                }

                if ( $padded_original ) {
                    $padded_original->destroy();
                }

                $_pretrim_image->destroy();

                $imageMeta->setBackup();

                $imageMeta->clearSizes();
                $sameSizes = [];

                $excluded_sizes = [];

                $processed_at  = time();

                $excluded_size_names = _wp_sir_get_excluded_sizes();
                $sizes = _wp_sir_get_sizes_to_generate();
                foreach ($sizes as $sizeName => $sizeData) {

                    @set_time_limit(0);

                    if (in_array($sizeName, $excluded_size_names)) {
                        if (!empty($metadata['sizes'][$sizeName])) {
                            $excluded_sizes[$sizeName] = $metadata['sizes'][$sizeName];
                            $excluded_sizes[$sizeName]['_processed_by'] = WP_SIR_VERSION;
                            $excluded_sizes[$sizeName]['_processed_at'] = $processed_at;
                        }

                        continue;
                    }
                    // Ignore duplicated sizes.
                    $sizeHash = $sizeData['width'] . '|' . $sizeData['height'];

                    if (isset($sameSizes[$sizeHash])) {
                        $imageMeta->setSizeData($sizeName, $imageMeta->getSizeData($sameSizes[$sizeHash]));
                        continue;
                    }

                    if(in_array($sizeName, $exclude_trim_sizes) && ! is_null($_untrimmed_image)){
                        $thumb_object = clone $_untrimmed_image;
                    }else{
                        $thumb_object = clone $image;
                    }
                    $thumb_object = $thumb_object->filter(new Thumbnail_Filter($sizeName, $sizeData));


                    $thumb_path = $this->generateThumbPath($image->basePath(), $sizeData, $sizeName, $imageId);

                    @unlink($thumb_path);

                    $quality = 100 - (int)$settings['jpg_quality'];
                    
                    $thumb_object->save($thumb_path, $quality);

                    $imageMeta->setSizeData($sizeName, [
                        'width'     => $thumb_object->getWidth(),
                        'height'    => $thumb_object->getHeight(),
                        'file'      => $thumb_object->basename,
                        'mime-type' => $thumb_object->mime(),
                        '_processed_at' => $processed_at,
                        '_processed_by' => WP_SIR_VERSION,
                    ]);

                    $sameSizes[$sizeHash] = $sizeName;


                    $thumb_object->destroy();
                }

                $image->destroy();

                $imageMeta->markSizesRegenerated($processed_at);

                $new_meta = $imageMeta->toArray();

                $new_meta['sizes'] = array_merge($excluded_sizes, $new_meta['sizes']);

                $this->deleteOrphanThumbnails($imageId, $metadata, $new_meta);
                
                
                Process_Tracker::record($imageId);
                
                return $new_meta;
            } catch (Invalid_Image_Meta_Exception $e) {
                return $metadata;
            } catch (Exception $e) {

                // Log full error details server-side for debugging
                error_log(sprintf(
                    'Smart Image Resize Error: %s (ID: %d, File: %s)',
                    $e->getMessage(),
                    $imageId,
                    !empty($metadata['file']) ? $metadata['file'] : 'unknown'
                ));

                if (defined('WP_CLI') && WP_CLI) {
                    $msg = sprintf('Smart Image Resize: Image processing failed for ID: %d', $imageId);
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        $msg .= ' - ' . $e->getMessage();
                    }
                    \WP_CLI::warning($msg);
                }

                return $metadata;
            }
        }

        /**
         * Clean up all generated files from a previous processing run.
         * Called before re-processing to start fresh from the original.
         *
         * @param int   $imageId
         * @param array $metadata Current attachment metadata.
         */
        private function cleanup_old_files( $imageId, $metadata ) {
            if ( empty( $metadata ) || empty( $metadata['file'] ) ) {
                return;
            }

            $uploads_dir = trailingslashit( wp_get_upload_dir()['basedir'] );
            $image_dir   = $uploads_dir . trailingslashit( dirname( $metadata['file'] ) );
            $original    = wp_basename( $metadata['file'] );

            // Collect thumbnail filenames.
            $files_to_delete = [];
            if ( ! empty( $metadata['sizes'] ) ) {
                foreach ( $metadata['sizes'] as $size_data ) {
                    if ( ! empty( $size_data['file'] ) && $size_data['file'] !== $original ) {
                        $files_to_delete[] = $size_data['file'];
                    }
                }
            }

            // Also check _old_image_meta for previously orphaned thumbnails.
            $old_meta = get_post_meta( $imageId, '_old_image_meta', true );
            if ( is_array( $old_meta ) && ! empty( $old_meta['sizes'] ) ) {
                foreach ( $old_meta['sizes'] as $size_data ) {
                    if ( ! empty( $size_data['file'] ) && $size_data['file'] !== $original ) {
                        $files_to_delete[] = $size_data['file'];
                    }
                }
            }

            $files_to_delete = array_unique( $files_to_delete );

            foreach ( $files_to_delete as $file ) {
                // Delete thumbnail.
                @unlink( $image_dir . $file );
                // Delete its WebP variant.
                @unlink( $image_dir . pathinfo( $file, PATHINFO_FILENAME ) . '.webp' );
            }

            // Delete full-size WebP.
            $original_webp = $image_dir . pathinfo( $original, PATHINFO_FILENAME ) . '.webp';
            if ( wp_basename( $original_webp ) !== $original ) {
                @unlink( $original_webp );
            }
        }

        private function deleteOrphanThumbnails($imageId, $oldMeta, $newMeta) {
            // Old file names to delete.
            $oldFileNames = [];
            // Since we prevent WP from generating any additional size via 
            // the filter `intermediate_image_sizes_advanced`, when a third-party triggers
            // the plugin the `$oldMeta[sizes]` won't contains unselected sizes
            // to manage this, we temporary store previously generated sizes
            //  in the `_old_image_meta` via the filter `wp_update_attachment_metadata`
            // to retreive them later here when running a clean up.

            $orphanMeta = get_post_meta($imageId, '_old_image_meta', true);
            if (is_array($orphanMeta) && !empty($orphanMeta) && !empty($orphanMeta['sizes'])) {
                foreach ($orphanMeta['sizes'] as $orphanSize) {
                    $oldFileNames[] = $orphanSize['file'];
                }
            }

            if (!empty($oldMeta['sizes'])) {
                foreach ($oldMeta['sizes'] as $oldSize) {
                    $oldFileNames[] = $oldSize['file'];
                }
            }

            $oldFileNames = array_unique($oldFileNames);

            if (empty($oldFileNames)) {
                return;
            }

            $newFileNames = array_map(function ($size) {
                return $size['file'];
            }, $newMeta['sizes']);

            $uploadsPath  = wp_get_upload_dir()['basedir'];
            $imageDirPath = trailingslashit($uploadsPath) . trailingslashit(dirname($oldMeta['file']));

            foreach ($oldFileNames as $file) {

                // Clean up WebP size if the "WebP Images" feature is disabled.
                if (!wp_sir_get_settings()['enable_webp']) {

                    $webp_file = File::mb_pathinfo($file, PATHINFO_FILENAME) . '.webp';

                    // Check if the WebP file is not the same as the size or original file.
                    if ($webp_file !== wp_basename($file) && $webp_file !== wp_basename($oldMeta['file'])) {
                        @unlink($imageDirPath . $webp_file);
                    }
                }

                // Prevent accidently deleting original image.
                if ($file === wp_basename($oldMeta['file'])) {
                    continue;
                }

                if (!in_array($file, $newFileNames)) {

                    // Delete old thumbnails, including JPG-converted images as well.
                    @unlink($imageDirPath . $file);

                    // Delete old WebP images if present.
                    $webp = $imageDirPath . File::mb_pathinfo($file, PATHINFO_FILENAME) . '.webp';
                    @unlink($webp);
                }
            }

            // Clean up full size WebP file if "WebP images" feature has been disabled.
            if (!wp_sir_get_settings()['enable_webp']) {
                $webp_file = File::mb_pathinfo($oldMeta['file'], PATHINFO_FILENAME) . '.webp';

                // Only delete if original file is not the same as the WebP file
                // to prevent accidently deleting the original file.
                if ($webp_file !== wp_basename($oldMeta['file'])) {
                    @unlink($imageDirPath . $webp_file);
                }
            }
        }

        /**
         * Return sizes to resize.
         *
         * @return array
         */


        private function getSizesToGenerate() {
            $sizeNames = apply_filters('wp_sir_sizes', wp_sir_get_settings()['sizes']);

            $sizes = [];

            foreach ($sizeNames as $sizeName) {

                // TODO: Use `wp_sir_get_additional_sizes` directly.
                $size = wp_sir_get_size_dimensions($sizeName);

                if (!empty($size) && !empty($size['width']) && !empty($size['height'])) {
                    $sizes[$sizeName] = $size;
                }
            }

            if (!apply_filters('wp_sir_enable_hd_sizes', false)) {
                unset($sizes['2048x2048']);
                unset($sizes['1536x1536']);
            }

            return apply_filters('wp_sir_sizes', $sizes);
        }


        /**
         * @param string $sourcePath
         * @param array $size
         * @param string $sizeName
         * @param int $imageId
         *
         * @return string
         */

        public function generateThumbPath(
            $sourcePath,
            $size,
            $sizeName,
            $imageId
        ) {

            $sourceInfo = File::mb_pathinfo($sourcePath);

            $alreadyInJPG = in_array($sourceInfo['extension'], ['jpg', 'jpeg']);
            $isPNGToJPGEnabled = wp_sir_get_settings()['jpg_convert'];

            if ($isPNGToJPGEnabled && !$alreadyInJPG) {
                // To avoid conflict with existing original image/thumbnails 
                // under the same path and file name we preserve 
                // the original extension in the filename, i.e. 'chair-500x500.png.jpg'
                $extension = $sourceInfo['extension'] . '.jpg';
            } else {
                // No conversion needed.
                $extension = $sourceInfo['extension'];
            }

            $basename = sprintf(
                '%s-%dx%d.%s',
                $sourceInfo['filename'],
                $size['width'],
                $size['height'],
                $extension
            );

            $path = trailingslashit($sourceInfo['dirname']) . $basename;

            return apply_filters('wp_sir_thumbnail_save_path', $path, $sizeName, $imageId);
        }
    }


endif;
