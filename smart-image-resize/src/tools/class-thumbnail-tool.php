<?php
/**
 * Thumbnail Tool.
 *
 * Resizes image to target dimensions and places on a canvas (pad mode).
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Tools
 */

namespace WP_Smart_Image_Resize\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class Thumbnail_Tool
 */
class Thumbnail_Tool implements Tool {

	/**
	 * Apply thumbnail generation: resize then pad onto canvas.
	 *
	 * @param \WP_Smart_Image_Resize\Editor\Editor_GD|\WP_Smart_Image_Resize\Editor\Editor_Imagick $editor
	 * @param array $context
	 * @return void
	 */
	public function apply( $editor, array $context ) {
		$size_data = $context['size_data'];
		$size_name = $context['size_name'];
		$settings  = $context['settings'];

		$target_w = (int) $size_data['width'];
		$target_h = (int) $size_data['height'];

		if ( ! $target_w || ! $target_h ) {
			return;
		}

		// Determine upscale behavior.
		$disable_upscale = ! empty( $settings['disable_upscale'] );
		$allow_upscale   = (bool) apply_filters( 'wp_sir_maybe_upscale', ! $disable_upscale, $size_name, $size_data );

		// Fill/crop mode: crop to fill the frame instead of padding.
		if ( isset( $settings['crop_mode'] ) && $settings['crop_mode'] === 'fill' ) {
			$this->apply_fill_mode( $editor, $target_w, $target_h, $allow_upscale, $settings );
			return;
		}

		// Standard pad mode: resize preserving aspect ratio, then pad to canvas.
		$current_size = $editor->get_size();
		$orig_w       = $current_size['width'];
		$orig_h       = $current_size['height'];

		// Determine resize dimensions (fit within target, preserve aspect ratio).
		$resize_w = $allow_upscale ? $target_w : min( $orig_w, $target_w );
		$resize_h = $allow_upscale ? $target_h : min( $orig_h, $target_h );

		// For Imagick: use scaleImage to preserve ICC/color profiles.
		// WP's resize() uses thumbnail_image() which strips them.
		if ( $editor instanceof \WP_Smart_Image_Resize\Editor\Editor_Imagick ) {
			// Experimental filter for pre-resize manipulation.
			$resized_image = apply_filters( 'wp_sir_pre_resize_image__experimental', null, $editor->get_image(), $size_data );
			if ( $resized_image instanceof \Imagick ) {
				$editor->set_image( $resized_image );
			} else {
				$editor->scale_image( $resize_w, $resize_h, true );
			}
		} else {
			// GD: use WP's resize (preserves aspect ratio when crop is false).
			$editor->resize( $resize_w, $resize_h, false );
		}

		// Place the resized image on a canvas of the exact target dimensions.
		$position = strtolower( apply_filters( 'wp_sir_canvas_position', 'center' ) );
		$bg_color = maybe_hash_hex_color( $settings['bg_color'] ) ?: null;

		$editor->set_canvas( $target_w, $target_h, $bg_color, $position );
	}

	/**
	 * Apply fill/crop mode: crop to fill the target dimensions.
	 *
	 * @param object $editor
	 * @param int    $target_w
	 * @param int    $target_h
	 * @param bool   $allow_upscale
	 * @param array  $settings
	 */
	private function apply_fill_mode( $editor, $target_w, $target_h, $allow_upscale, $settings ) {
		$current_size = $editor->get_size();
		$orig_w       = $current_size['width'];
		$orig_h       = $current_size['height'];

		// Calculate crop dimensions to fill the target aspect ratio.
		$target_ratio = $target_w / $target_h;
		$orig_ratio   = $orig_w / $orig_h;

		if ( $orig_ratio > $target_ratio ) {
			// Image is wider — crop width.
			$crop_h = $orig_h;
			$crop_w = (int) round( $orig_h * $target_ratio );
			$crop_x = (int) round( ( $orig_w - $crop_w ) / 2 );
			$crop_y = 0;
		} else {
			// Image is taller — crop height.
			$crop_w = $orig_w;
			$crop_h = (int) round( $orig_w / $target_ratio );
			$crop_x = 0;
			$crop_y = (int) round( ( $orig_h - $crop_h ) / 2 );
		}

		$editor->crop( $crop_x, $crop_y, $crop_w, $crop_h, $target_w, $target_h );

		// If upscale is not allowed and the result is smaller, pad with canvas.
		if ( ! $allow_upscale ) {
			$result_size = $editor->get_size();
			if ( $result_size['width'] < $target_w || $result_size['height'] < $target_h ) {
				$bg_color = maybe_hash_hex_color( $settings['bg_color'] ) ?: null;
				$position = strtolower( apply_filters( 'wp_sir_canvas_position', 'center' ) );
				$editor->set_canvas( $target_w, $target_h, $bg_color, $position );
			}
		}
	}
}
