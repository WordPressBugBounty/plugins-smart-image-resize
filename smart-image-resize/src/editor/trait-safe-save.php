<?php
/**
 * Safe Save Trait.
 *
 * Shared by the GD and Imagick editors so a failed encode can never replace
 * an existing file (e.g. the original upload) with a broken or empty one.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Editor
 */

namespace WP_Smart_Image_Resize\Editor;

defined( 'ABSPATH' ) || exit;

trait Safe_Save_Trait {

	/**
	 * Whether the plugin has explicitly requested a quality (see set_quality()
	 * override below).
	 *
	 * @var bool
	 */
	private $sir_quality_locked = false;

	/**
	 * Preserve an explicitly-requested quality across WordPress core's own
	 * internal reset.
	 *
	 * WP_Image_Editor::get_output_format() — called from every save(), and
	 * hit on every PNG→JPG conversion (this plugin's own feature) — detects
	 * the mime type is changing and calls set_quality() with NO arguments,
	 * "to set the quality for the new type if not already set". The code
	 * doesn't actually check whether a quality was already set, though: it
	 * unconditionally overwrites $this->quality with the mime type's
	 * hardcoded default (82 for JPEG), discarding whatever the plugin
	 * explicitly configured before calling save(). Confirmed empirically:
	 * the JPEG Quality setting silently has no effect on any image that
	 * goes through PNG→JPG conversion, on both drivers — invisible in a
	 * quick look (82% still looks fine), only the file size is wrong. Once
	 * the plugin has explicitly set a quality, ignore later no-argument
	 * reset attempts and keep it.
	 *
	 * @param int|null $quality
	 * @param array    $dims
	 * @return true|\WP_Error
	 */
	public function set_quality( $quality = null, $dims = [] ) {
		if ( null === $quality && $this->sir_quality_locked ) {
			return true;
		}

		$result = parent::set_quality( $quality, $dims );

		if ( null !== $quality && ! is_wp_error( $result ) ) {
			$this->sir_quality_locked = true;
		}

		return $result;
	}

	/**
	 * Save through the parent editor, rejecting empty output.
	 *
	 * An existing destination is only replaced once the new file has been
	 * written in full next to it.
	 *
	 * @param string|null $destfilename
	 * @param string|null $mime_type
	 * @return array|\WP_Error
	 */
	protected function safe_save( $destfilename, $mime_type ) {
		if ( ! $destfilename || ! file_exists( $destfilename ) ) {
			$saved = parent::save( $destfilename, $mime_type );

			if ( ! is_wp_error( $saved ) && ! $this->is_non_empty_file( $saved ) ) {
				@unlink( $saved['path'] );
				return $this->empty_save_error( $destfilename );
			}

			return $saved;
		}

		$info      = pathinfo( $destfilename );
		$dest_stem = $info['filename'];
		$tmp_stem  = $dest_stem . '.sir-tmp-' . wp_generate_password( 8, false );
		$ext       = isset( $info['extension'] ) ? '.' . $info['extension'] : '';

		$saved = parent::save( $info['dirname'] . '/' . $tmp_stem . $ext, $mime_type );

		if ( is_wp_error( $saved ) ) {
			return $saved;
		}

		if ( ! $this->is_non_empty_file( $saved ) ) {
			@unlink( $saved['path'] );
			return $this->empty_save_error( $destfilename );
		}

		// The parent may adjust the extension to match the output format.
		$final_path = $info['dirname'] . '/' . str_replace( $tmp_stem, $dest_stem, wp_basename( $saved['path'] ) );

		if ( ! @rename( $saved['path'], $final_path ) ) {
			@unlink( $saved['path'] );
			return new \WP_Error( 'image_save_error', __( 'Image file could not be moved into place.', 'wp-smart-image-resize' ), $final_path );
		}

		$saved['path'] = $final_path;
		$saved['file'] = wp_basename( $final_path );

		return $saved;
	}

	/**
	 * @param array|\WP_Error $saved
	 * @return bool
	 */
	private function is_non_empty_file( $saved ) {
		clearstatcache();

		return ! empty( $saved['path'] ) && file_exists( $saved['path'] ) && filesize( $saved['path'] ) > 0;
	}

	/**
	 * @param string|null $destfilename
	 * @return \WP_Error
	 */
	private function empty_save_error( $destfilename ) {
		return new \WP_Error(
			'image_save_error',
			__( 'Image editor produced an empty file.', 'wp-smart-image-resize' ),
			$destfilename
		);
	}
}
