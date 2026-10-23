<?php

namespace WP_Smart_Image_Resize;

use Exception;
use WP_Smart_Image_Resize\Exceptions\Invalid_Image_Meta_Exception;
use WP_Smart_Image_Resize\Editor\Editor_Factory;
use WP_Smart_Image_Resize\Tools\Trim_Tool;
use WP_Smart_Image_Resize\Tools\Thumbnail_Tool;
use WP_Smart_Image_Resize\Tools\Watermark_Tool;
use WP_Smart_Image_Resize\Tools\WebP_Tool;
use WP_Smart_Image_Resize\Tools\Pad_Tool;
use WP_Smart_Image_Resize\Tools\Remove_Background_Tool;
use WP_Smart_Image_Resize\Utilities\Backup;
use WP_Smart_Image_Resize\Utilities\File;

/*
 * Class WP_Smart_Image_Resize\Image_Editor
 *
 * @package WP_Smart_Image_Resize\Inc
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
         * Whether it's safe to perform slow, blocking operations (like the
         * remove.bg API call) in the current execution context. True inside
         * Bulk Resize batches, the async background-processing job, and
         * WP-CLI. False (the default) during a live upload HTTP request,
         * where such calls would delay the response the merchant is waiting on.
         *
         * @var bool
         */
        public static $allow_blocking_calls = false;


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
            add_filter('wp_generate_attachment_metadata', [$this, 'processImage'], 9, 2);

            add_filter('wp_update_attachment_metadata', [$this, 'recheck_subsizes'], 10, 2);

            // Force 1:1 size for single product thumbnail.
            add_filter('woocommerce_get_image_size_single', [$this, 'forceSquareWooCommerceSingle']);

            // Force woocommerce single on single product page.
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
         * @param string|array $size
         *
         * @return array
         */
        public function forceSquareWooCommerceSingle($size) {
            if (!$this->isProcessableSize(['woocommerce_single', 'shop_single'])) {
                return $size;
            }

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

        /**
         * Re-process when image is edited in WP media editor.
         *
         * @param array $metadata
         * @param int   $image_id
         *
         * @return array
         */
        function recheck_subsizes($metadata, $image_id) {
            $stored_meta = wp_get_attachment_metadata($image_id);

            if (isset($metadata['file']) && isset($stored_meta['file'])) {
                $new_basename = wp_basename($metadata['file']);
                $old_basename = wp_basename($stored_meta['file']);

                if (($new_basename !== $old_basename && preg_match('/-e\d{13}\.[^.]+$/', $new_basename)) || preg_match('/-e\d{13}\.[^.]+$/', $old_basename)) {
                    $new_metadata = $this->processImage($metadata, $image_id);
                    if (is_array($new_metadata) && !empty($new_metadata)) {
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
                $imageMeta = new Image_Meta($imageId, $metadata);

                // Check whether the current image is processable.
                if (!$this->isProcessable($imageId, $imageMeta)) {
                    return $metadata;
                }

                // ------------------------------------------------------------------
                // If this image was previously PNG→JPG converted, restore the
                // pristine original before reprocessing so a fresh run starts
                // from a clean source.
                //
                // NOTE: This handles ONLY the PNG→JPG conversion feature
                // (_sir_pre_conversion_file). Background removal is handled
                // entirely by maybeRemoveBackground() below, which reuses the
                // saved "-nobg" cutout backup instead of restoring — so we must
                // NOT touch _sir_pre_bgremoval_file / _sir_bg_removed here, or
                // we'd destroy the cutout backup and force a new API call.
                // ------------------------------------------------------------------
                $backup = new Backup();
                $current_file  = $imageMeta->getOriginalFullPath();
                $uploads_base  = trailingslashit(wp_get_upload_dir()['basedir']);

                $pre_conversion_relative = get_post_meta($imageId, '_sir_pre_conversion_file', true);

                if ($pre_conversion_relative) {
                    $pre_original_file = $uploads_base . $pre_conversion_relative;

                    // Resolve the backup pointer for the pristine original.
                    // Prefer the stored pointer; fall back to the legacy
                    // path-derived slot for backups from older versions.
                    $original_ptr = get_post_meta($imageId, '_sir_backup_original', true);
                    if (! $original_ptr) {
                        $original_ptr = $backup->legacy_relative_of($pre_original_file);
                    }

                    // Only restore when there is a DISTINCT pristine backup to
                    // restore FROM — i.e. the baseline path differs from the
                    // current working file.
                    if (
                        get_post_meta($imageId, '_processed_at', true)
                        && $pre_original_file !== $current_file
                        && $backup->exists_at($original_ptr)
                    ) {
                        $this->cleanup_old_files($imageId, $metadata);

                        // Remove the current (converted) working file before
                        // restoring the true original in its place.
                        if (file_exists($current_file)) {
                            @unlink($current_file);
                        }

                        $backup->restore_at($original_ptr, $pre_original_file);

                        // Repoint the attachment back to the true original and
                        // clear the conversion bookkeeping so the run below
                        // starts fresh from the pristine source.
                        update_attached_file($imageId, $pre_conversion_relative);
                        // Repoint the in-memory metadata too: Image_Meta prefers
                        // it over the DB path, and it still names the converted
                        // JPG we just deleted.
                        $metadata['file'] = $pre_conversion_relative;
                        $imageMeta = new Image_Meta($imageId, $metadata);
                        delete_post_meta($imageId, '_sir_pre_conversion_file');
                        delete_post_meta($imageId, '_sir_backup_original');
                    }
                }


                // ------------------------------------------------------------------
                // Load image using WP Image Editor (handles EXIF rotation on load).
                // Loaded once — every other working copy needed below is duplicated
                // in memory instead of re-reading/re-decoding the file from disk.
                // ------------------------------------------------------------------
                $original_path = $imageMeta->getOriginalFullPath();
                $editor = Editor_Factory::create($original_path);

                if (is_wp_error($editor)) {
                    throw new Exception($editor->get_error_message());
                }

                @set_time_limit(0);

                // Detect MIME type from the file.
                $mime_type = wp_check_filetype($original_path)['type'] ?: 'image/jpeg';
                $imageMeta->setMimeType($mime_type);


                // ------------------------------------------------------------------
                // Trimming: happens first so each thumbnail is measured from content.
                // ------------------------------------------------------------------
                $exclude_trim_sizes = (array) apply_filters('wp_sir_exclude_trim_sizes', [], $imageId);

                // Apply trim to get the trimmed source for thumbnails.
                $trim_tool = new Trim_Tool();
                $trim_context = ['settings' => $settings, 'image_id' => $imageId];

                // We need the trimmed version for thumbnails, and the untrimmed
                // version for saving the original (when process_original is off).
                $trimmed_editor = $editor->duplicate();
                $trim_tool->apply($trimmed_editor, $trim_context);

                // Store trimmed dimensions in meta.
                $trimmed_size = $trimmed_editor->get_size();
                $imageMeta->setMetaItem('_trimmed_width', $trimmed_size['width']);
                $imageMeta->setMetaItem('_trimmed_height', $trimmed_size['height']);


                // ------------------------------------------------------------------
                // Pad the original to target aspect ratio AFTER trimming.
                // Only affects saved original file, NOT thumbnails.
                // ------------------------------------------------------------------
                if (!empty($settings['process_original'])) {
                    $pad_target_size = _wp_sir_get_original_pad_target_size();
                    $pad_tool = new Pad_Tool();
                    $pad_tool->apply($trimmed_editor, [
                        'settings'    => $settings,
                        'target_size' => $pad_target_size,
                        'image_id'    => $imageId,
                    ]);

                    $padded_size = $trimmed_editor->get_size();
                    $imageMeta->setMetaItem('width', $padded_size['width']);
                    $imageMeta->setMetaItem('height', $padded_size['height']);
                    $imageMeta->setSizeData('full', [
                        'file'      => wp_basename($original_path),
                        'width'     => $padded_size['width'],
                        'height'    => $padded_size['height'],
                        'mime-type' => $mime_type,
                    ]);
                }


                // ------------------------------------------------------------------
                // Determine whether tools should be applied to the original.
                // ------------------------------------------------------------------
                $apply_tools_to_original = (bool) apply_filters(
                    'wp_sir_experimental/apply_tools_to_original',
                    true,
                    $imageId,
                    $settings
                );

                $has_compression = !empty($settings['jpg_quality']) && (int) $settings['jpg_quality'] > 0;
                $has_watermark   = !empty($settings['enable_watermark']);
                $has_jpg_convert = !empty($settings['jpg_convert']);
                $has_webp        = !empty($settings['enable_webp']);

                $tools_active    = $apply_tools_to_original && ($has_compression || $has_watermark || $has_jpg_convert);
                // WebP alone never re-saves the original (see the WebP-only branch below).
                $modify_original = !empty($settings['process_original']) || $tools_active;


                // ------------------------------------------------------------------
                // Save the original image to disk (when modifications are needed).
                // ------------------------------------------------------------------
                if ($modify_original) {
                    // Backup the original before destructive changes, unless a
                    // usable backup already exists. Store the resolved pointer so
                    // restore can find it even when the backup name was uniquified.
                    $backup       = new Backup();
                    $original_ptr = get_post_meta($imageId, '_sir_backup_original', true);
                    $has_backup   = $original_ptr
                        ? $backup->exists_at($original_ptr)
                        : $backup->exists_at($backup->legacy_relative_of($original_path));

                    if (! $has_backup) {
                        try {
                            $resolved = $backup->create($original_path);
                            update_post_meta($imageId, '_sir_backup_original', $resolved);
                        } catch (Exception $e) {
                            error_log(sprintf(
                                'Smart Image Resize: original backup failed for attachment %d (%s): %s',
                                $imageId,
                                $original_path,
                                $e->getMessage()
                            ));
                        }
                    }

                    // Use the padded/trimmed editor if process_original is on,
                    // otherwise duplicate the untrimmed master copy in memory.
                    if (!empty($settings['process_original'])) {
                        $original_editor = $trimmed_editor;
                    } else {
                        $original_editor = $editor->duplicate();
                    }

                    // Compression quality.
                    $quality = ($apply_tools_to_original && $has_compression)
                        ? 100 - (int) $settings['jpg_quality']
                        : 100;

                    $original_editor->set_quality($quality);




                    
                    $original_saved = $original_editor->save($original_path);
                    

                    // The original on disk is untouched when the save failed; stop here.
                    if (is_wp_error($original_saved)) {
                        throw new Exception($original_saved->get_error_message());
                    }


                    // Update metadata dimensions.
                    $saved_size = $original_editor->get_size();
                    $imageMeta->setMetaItem('width', $saved_size['width']);
                    $imageMeta->setMetaItem('height', $saved_size['height']);

                }


                $imageMeta->setBackup();
                $imageMeta->clearSizes();
                $sameSizes = [];
                $excluded_sizes = [];
                $processed_at = time();

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


                    // Duplicate an in-memory copy for this thumbnail instead of
                    // re-reading the source file from disk. Sizes excluded from
                    // trim start from the untrimmed master; the rest start from
                    // the already-trimmed copy (trim doesn't need recomputing).
                    $thumb_editor = in_array($sizeName, $exclude_trim_sizes)
                        ? $editor->duplicate()
                        : $trimmed_editor->duplicate();

                    // Apply thumbnail resize + canvas pad.
                    $thumbnail_tool = new Thumbnail_Tool();
                    $thumbnail_tool->apply($thumb_editor, [
                        'settings'  => $settings,
                        'size_name' => $sizeName,
                        'size_data' => $sizeData,
                        'image_id'  => $imageId,
                    ]);



                    $thumb_path = $this->generateThumbPath($original_path, $sizeData, $sizeName, $imageId);

                    @unlink($thumb_path);

                    $quality = 100 - (int) $settings['jpg_quality'];
                    $thumb_editor->set_quality($quality);

                    // Determine MIME for save (handles PNG→JPG conversion).
                    $save_mime = null;
                    if (!empty($settings['jpg_convert'])) {
                        $source_ext = strtolower(pathinfo($original_path, PATHINFO_EXTENSION));
                        if ($source_ext === 'png') {
                            $save_mime = 'image/jpeg';
                        }
                    }

                    $saved = $thumb_editor->save($thumb_path, $save_mime);

                    if (is_wp_error($saved) || empty($saved)) {
                        continue;
                    }

                    $imageMeta->setSizeData($sizeName, [
                        'width'         => $saved['width'],
                        'height'        => $saved['height'],
                        'file'          => wp_basename($saved['path']),
                        'mime-type'     => $saved['mime-type'],
                        '_processed_at' => $processed_at,
                        '_processed_by' => WP_SIR_VERSION,
                    ]);

                    $sameSizes[$sizeHash] = $sizeName;


                }

                $imageMeta->markSizesRegenerated($processed_at);

                $new_meta = $imageMeta->toArray();
                $new_meta['sizes'] = array_merge($excluded_sizes, $new_meta['sizes']);

                $this->deleteOrphanThumbnails($imageId, $metadata, $new_meta);

                
                Process_Tracker::record($imageId);
                

                /**
                 * Fires after an image has been fully processed (resized, padded,
                 * thumbnails generated). Used to surface in-admin feedback.
                 *
                 * @param int   $imageId  Attachment ID.
                 * @param array $new_meta Freshly-generated attachment metadata.
                 */
                do_action( 'wp_sir_image_processed', $imageId, $new_meta );

                return $new_meta;

            } catch (Invalid_Image_Meta_Exception $e) {
                return $metadata;
            } catch (Exception $e) {

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
         *
         * @param int   $imageId
         * @param array $metadata Current attachment metadata.
         */
        private function cleanup_old_files($imageId, $metadata) {
            if (empty($metadata) || empty($metadata['file'])) {
                return;
            }

            $uploads_dir = trailingslashit(wp_get_upload_dir()['basedir']);
            $image_dir   = $uploads_dir . trailingslashit(dirname($metadata['file']));
            $original    = wp_basename($metadata['file']);

            $files_to_delete = [];
            if (!empty($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size_data) {
                    if (!empty($size_data['file']) && $size_data['file'] !== $original) {
                        $files_to_delete[] = $size_data['file'];
                    }
                }
            }

            $old_meta = get_post_meta($imageId, '_old_image_meta', true);
            if (is_array($old_meta) && !empty($old_meta['sizes'])) {
                foreach ($old_meta['sizes'] as $size_data) {
                    if (!empty($size_data['file']) && $size_data['file'] !== $original) {
                        $files_to_delete[] = $size_data['file'];
                    }
                }
            }

            $files_to_delete = array_unique($files_to_delete);

            foreach ($files_to_delete as $file) {
                @unlink($image_dir . $file);
                @unlink($image_dir . pathinfo($file, PATHINFO_FILENAME) . '.webp');
            }

            $original_webp = $image_dir . pathinfo($original, PATHINFO_FILENAME) . '.webp';
            if (wp_basename($original_webp) !== $original) {
                @unlink($original_webp);
            }
        }


        /**
         * Delete orphan thumbnails from previous runs.
         *
         * @param int   $imageId
         * @param array $oldMeta
         * @param array $newMeta
         */
        private function deleteOrphanThumbnails($imageId, $oldMeta, $newMeta) {
            $oldFileNames = [];

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

                if (!wp_sir_get_settings()['enable_webp']) {
                    $webp_file = File::mb_pathinfo($file, PATHINFO_FILENAME) . '.webp';
                    if ($webp_file !== wp_basename($file) && $webp_file !== wp_basename($oldMeta['file'])) {
                        @unlink($imageDirPath . $webp_file);
                    }
                }

                if ($file === wp_basename($oldMeta['file'])) {
                    continue;
                }

                if (!in_array($file, $newFileNames)) {
                    @unlink($imageDirPath . $file);
                    $webp = $imageDirPath . File::mb_pathinfo($file, PATHINFO_FILENAME) . '.webp';
                    @unlink($webp);
                }
            }

            if (!wp_sir_get_settings()['enable_webp']) {
                $webp_file = File::mb_pathinfo($oldMeta['file'], PATHINFO_FILENAME) . '.webp';
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
         * Generate thumbnail file path.
         *
         * @param string $sourcePath
         * @param array  $size
         * @param string $sizeName
         * @param int    $imageId
         *
         * @return string
         */
        public function generateThumbPath($sourcePath, $size, $sizeName, $imageId) {

            $sourceInfo = File::mb_pathinfo($sourcePath);

            $alreadyInJPG = in_array($sourceInfo['extension'], ['jpg', 'jpeg']);
            $isPNGToJPGEnabled = wp_sir_get_settings()['jpg_convert'];

            if ($isPNGToJPGEnabled && !$alreadyInJPG) {
                $extension = $sourceInfo['extension'] . '.jpg';
            } else {
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
