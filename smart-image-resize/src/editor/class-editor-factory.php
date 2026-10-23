<?php
/**
 * Editor Factory.
 *
 * Creates the appropriate image editor instance based on server capabilities
 * and plugin settings.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Editor
 */

namespace WP_Smart_Image_Resize\Editor;

use WP_Smart_Image_Resize\Utilities\Env;

defined( 'ABSPATH' ) || exit;

/**
 * Class Editor_Factory
 */
class Editor_Factory {

	/**
	 * Create an editor instance for the given file.
	 *
	 * Respects the wp_sir_driver filter and server capabilities.
	 *
	 * @param string $file Path to image file.
	 * @return Editor_GD|Editor_Imagick|\WP_Error
	 */
	public static function create( $file ) {
		wp_raise_memory_limit( 'image' );

		$driver = Env::active_image_processor();

		// Imagick may list a format it can't write (e.g. AVIF on ImageMagick 6);
		// hand such files to GD when GD can handle them.
		if ( $driver === 'imagick' ) {
			$mime_type = wp_check_filetype( $file )['type'];

			if ( $mime_type
				&& ! Env::imagick_can_write( $mime_type )
				&& Env::gd_loaded()
				&& \WP_Image_Editor_GD::supports_mime_type( $mime_type )
			) {
				$driver = 'gd';
			}
		}

		if ( $driver === 'imagick' ) {
			$editor = new Editor_Imagick( $file );
		} else {
			$editor = new Editor_GD( $file );
		}

		$loaded = $editor->load();

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		// Apply the EXIF orientation (phone photos) the way WordPress does on
		// upload; we may be reading the un-rotated original.
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}

		return $editor;
	}
}
