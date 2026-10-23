<?php
/**
 * Pad Tool.
 *
 * Pads the original image to match the target aspect ratio, at native
 * resolution — content is never stretched or cropped by this step, only
 * surrounded with background-color padding when its shape doesn't match.
 * This is the default, general-purpose behavior of "Resize Original
 * Image": most users just want a consistent shape for their master files,
 * not to have their original photos upscaled.
 *
 * Separately, the 'wp_sir_original_enforce_min_size' filter — off by
 * default, developer-only, no UI setting — lets an image still smaller
 * than the target's absolute pixel size after ratio-padding be brought up
 * to match it exactly. That's an explicit opt-in for a narrow need — e.g.
 * a marketplace or ad feed with a minimum pixel-size requirement that
 * reads the original file — specific enough (and rare enough) that it
 * doesn't warrant a Settings checkbox most merchants would never need and
 * wouldn't know whether they do.
 *
 * The filter's return value picks how: 'pad' (recommended for a pixel-
 * minimum requirement like Google Shopping) keeps the photo at its native
 * resolution — full sharpness — and reaches the target size purely with
 * more background-color margin. Any other truthy value (true, 'upscale',
 * …) scales the photo itself up to fill the target size exactly, which
 * satisfies a frame-filling look but softens the image slightly, since
 * it's enlarging pixels that were never actually captured.
 *
 * An original is never downscaled or cropped to fit: content already at
 * or beyond the target in a dimension is left alone there either way.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Tools
 */

namespace WP_Smart_Image_Resize\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class Pad_Tool
 */
class Pad_Tool implements Tool {

	/**
	 * Apply the target aspect ratio (and, if opted in, size) to the original image.
	 *
	 * @param \WP_Smart_Image_Resize\Editor\Editor_GD|\WP_Smart_Image_Resize\Editor\Editor_Imagick $editor
	 * @param array $context Must include 'target_size' with {width, height}.
	 * @return void
	 */
	public function apply( $editor, array $context ) {
		$target_size = ! empty( $context['target_size'] ) ? $context['target_size'] : null;

		if ( empty( $target_size['width'] ) || empty( $target_size['height'] ) ) {
			return;
		}

		$target_w = (int) $target_size['width'];
		$target_h = (int) $target_size['height'];
		$settings = $context['settings'];
		$bg_color = maybe_hash_hex_color( $settings['bg_color'] ) ?: null;

		$current_size = $editor->get_size();
		$orig_w       = $current_size['width'];
		$orig_h       = $current_size['height'];

		// Calculate canvas dimensions that match the target aspect ratio
		// while fitting the original image entirely (no downscale).
		$target_ratio = $target_w / $target_h;
		$orig_ratio   = $orig_w / $orig_h;

		if ( abs( $orig_ratio - $target_ratio ) >= 0.001 ) {
			if ( $orig_ratio > $target_ratio ) {
				// Image is wider than target ratio — add height.
				$canvas_w = $orig_w;
				$canvas_h = (int) round( $orig_w / $target_ratio );
			} else {
				// Image is taller than target ratio — add width.
				$canvas_w = (int) round( $orig_h * $target_ratio );
				$canvas_h = $orig_h;
			}

			$editor->set_canvas( $canvas_w, $canvas_h, $bg_color, 'center' );

			$current_size = $editor->get_size();
			$orig_w       = $current_size['width'];
			$orig_h       = $current_size['height'];
		}

		// Opt-in only, developer filter — no UI setting. See the class
		// docblock for why this isn't the default behavior of "Resize
		// Original Image", and for what the return value means.
		$enforce_mode = apply_filters( 'wp_sir_original_enforce_min_size', false, $target_size );

		if ( ! $enforce_mode ) {
			return;
		}

		if ( $orig_w < $target_w && $orig_h < $target_h ) {
			if ( 'pad' === $enforce_mode ) {
				// Keep the photo at native resolution — no interpolation,
				// no softening — and pad straight to the exact target size.
				$editor->set_canvas( $target_w, $target_h, $bg_color, 'center' );
			} elseif ( $editor instanceof \WP_Smart_Image_Resize\Editor\Editor_Imagick ) {
				$editor->scale_image( $target_w, $target_h, true );
			} else {
				$editor->resize( $target_w, $target_h, false );
			}
		}
	}
}
