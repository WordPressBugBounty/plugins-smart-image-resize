<?php
/**
 * WebP Tool.
 *
 * Generates a WebP copy of the image.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Tools
 */

namespace WP_Smart_Image_Resize\Tools;

use WP_Smart_Image_Resize\Editor\Editor_Factory;
use WP_Smart_Image_Resize\Utilities\Env;

defined( 'ABSPATH' ) || exit;

/**
 * Class WebP_Tool
 */
class WebP_Tool implements Tool {

	/**
	 * Apply WebP conversion — saves a .webp copy next to the source file.
	 *
	 * @param \WP_Smart_Image_Resize\Editor\Editor_GD|\WP_Smart_Image_Resize\Editor\Editor_Imagick $editor
	 * @param array $context
	 * @return void
	 */
	public function apply( $editor, array $context ) {
		$settings = $context['settings'];

		if ( empty( $settings['enable_webp'] ) ) {
			return;
		}

		// Skip if already a WebP image.
		$source_path = ! empty( $context['source_path'] ) ? $context['source_path'] : '';
		if ( $source_path && wp_check_filetype( $source_path )['type'] === 'image/webp' ) {
			return;
		}

		$webp_path = ! empty( $context['webp_path'] ) ? $context['webp_path'] : '';
		if ( empty( $webp_path ) ) {
			return;
		}

		// Check if the current editor/driver supports WebP.
		$webp_processor = Env::get_webp_image_processor();

		if ( ! $webp_processor ) {
			return;
		}

		// Try saving directly with the current editor.
		@unlink( $webp_path );

		$result = $editor->save( $webp_path, 'image/webp' );

		if ( is_wp_error( $result ) ) {
			// Current driver doesn't support WebP — try the fallback driver.
			$this->save_with_fallback( $context, $webp_path );
		}
	}

	/**
	 * Try saving WebP using the alternate image processor.
	 *
	 * @param array  $context
	 * @param string $webp_path Output path.
	 */
	private function save_with_fallback( $context, $webp_path ) {
		$source_path = ! empty( $context['source_path'] ) ? $context['source_path'] : '';

		if ( empty( $source_path ) || ! is_readable( $source_path ) ) {
			return;
		}

		// Determine fallback driver.
		$current_driver  = Env::active_image_processor();
		$fallback_driver = ( $current_driver === 'imagick' ) ? 'gd' : 'imagick';

		if ( $fallback_driver === 'imagick' && ! Env::imagick_supports_webp() ) {
			return;
		}

		if ( $fallback_driver === 'gd' && ! Env::gd_supports_webp() ) {
			return;
		}

		// Create editor with fallback driver.
		if ( $fallback_driver === 'imagick' ) {
			$fallback_editor = new \WP_Smart_Image_Resize\Editor\Editor_Imagick( $source_path );
		} else {
			$fallback_editor = new \WP_Smart_Image_Resize\Editor\Editor_GD( $source_path );
		}

		$loaded = $fallback_editor->load();

		if ( is_wp_error( $loaded ) ) {
			return;
		}

		$fallback_editor->save( $webp_path, 'image/webp' );
	}
}
