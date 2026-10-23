<?php
/**
 * Tool Interface.
 *
 * Contract for all image processing tools.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Tools
 */

namespace WP_Smart_Image_Resize\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Interface Tool
 *
 * Each tool performs a single image manipulation operation.
 */
interface Tool {

	/**
	 * Apply the tool's operation to the editor.
	 *
	 * @param \WP_Smart_Image_Resize\Editor\Editor_GD|\WP_Smart_Image_Resize\Editor\Editor_Imagick $editor The image editor instance.
	 * @param array $context {
	 *     Context data for the operation.
	 *
	 *     @type array  $settings  Plugin settings.
	 *     @type string $size_name Current size name (for thumbnails).
	 *     @type array  $size_data Current size dimensions {width, height}.
	 *     @type int    $image_id  Attachment ID.
	 * }
	 * @return void
	 */
	public function apply( $editor, array $context );
}
