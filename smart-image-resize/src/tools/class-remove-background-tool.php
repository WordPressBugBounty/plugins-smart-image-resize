<?php
/**
 * Remove Background Tool.
 *
 * Removes the background from the original image via the remove.bg API.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Tools
 */

namespace WP_Smart_Image_Resize\Tools;

use WP_Smart_Image_Resize\Utilities\Remove_Bg_Client;

defined( 'ABSPATH' ) || exit;

/**
 * Class Remove_Background_Tool
 */
class Remove_Background_Tool implements Tool {

	/**
	 * Error from the most recent apply() call, if it failed.
	 *
	 * @var \WP_Error|null
	 */
	private $last_error = null;

	/**
	 * Call remove.bg on the source file and load the cutout into the editor.
	 *
	 * Fails open: on any error the editor is left untouched and the caller
	 * can inspect get_last_error() — it never throws.
	 *
	 * @param \WP_Smart_Image_Resize\Editor\Editor_GD|\WP_Smart_Image_Resize\Editor\Editor_Imagick $editor
	 * @param array $context {
	 *     @type array  $settings    Plugin settings.
	 *     @type string $source_path Path to the source file to send to remove.bg.
	 * }
	 * @return bool True on success, false on failure.
	 */
	public function apply( $editor, array $context ) {
		$this->last_error = null;

		$settings = $context['settings'];

		if ( empty( $settings['enable_remove_bg'] ) || empty( $settings['removebg_api_key'] ) ) {
			return false;
		}

		$source_path = ! empty( $context['source_path'] ) ? $context['source_path'] : '';

		if ( empty( $source_path ) || ! is_readable( $source_path ) ) {
			$this->last_error = new \WP_Error( 'removebg_no_source', __( 'Source image is not readable.', 'wp-smart-image-resize' ) );
			return false;
		}

		$result = Remove_Bg_Client::remove_background( $source_path, $settings['removebg_api_key'] );

		if ( is_wp_error( $result ) ) {
			$this->last_error = $result;
			return false;
		}

		$loaded = $editor->load_from_string( $result['body'], $result['mime'] );

		if ( is_wp_error( $loaded ) ) {
			$this->last_error = $loaded;
			return false;
		}

		return true;
	}

	/**
	 * @return \WP_Error|null
	 */
	public function get_last_error() {
		return $this->last_error;
	}
}
