<?php
/**
 * Watermark Tool.
 *
 * Applies a watermark overlay to the image.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Tools
 */

namespace WP_Smart_Image_Resize\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class Watermark_Tool
 */
class Watermark_Tool implements Tool {

	/**
	 * Supported watermark positions.
	 *
	 * @var array
	 */
	private $supported_positions = [
		'top-left',
		'top',
		'top-right',
		'left',
		'center',
		'right',
		'bottom-left',
		'bottom',
		'bottom-right',
	];

	/**
	 * Apply watermark to the image.
	 *
	 * @param \WP_Smart_Image_Resize\Editor\Editor_GD|\WP_Smart_Image_Resize\Editor\Editor_Imagick $editor
	 * @param array $context
	 * @return void
	 */
	public function apply( $editor, array $context ) {
		$settings = $context['settings'];

		if ( empty( $settings['enable_watermark'] ) ) {
			return;
		}

		$watermark_image = $settings['watermark_image'];

		if ( empty( $watermark_image ) ) {
			return;
		}

		$watermark_path = get_attached_file( $watermark_image );

		if ( ! $watermark_path || ! is_readable( $watermark_path ) ) {
			return;
		}

		$position = $settings['watermark_position'];

		if ( ! in_array( $position, $this->supported_positions, true ) ) {
			return;
		}

		$size_percent = (int) $settings['watermark_size'] ?: 50;
		$opacity      = (int) $settings['watermark_opacity'];
		$offset_x     = (int) $settings['watermark_offset']['x'];
		$offset_y     = (int) $settings['watermark_offset']['y'];

		if ( $position === 'center' ) {
			$offset_x = 0;
			$offset_y = 0;
		}

		// Calculate watermark dimensions based on percentage of image size.
		$image_size = $editor->get_size();
		$image_w    = $image_size['width'];
		$image_h    = $image_size['height'];

		$wm_size = wp_getimagesize( $watermark_path );
		if ( ! $wm_size ) {
			return;
		}

		$wm_orig_w = $wm_size[0];
		$wm_orig_h = $wm_size[1];

		if ( $wm_orig_w >= $wm_orig_h ) {
			$watermark_w = $image_w * $size_percent / 100;
			$watermark_w = min( $watermark_w, $image_w );
			$watermark_h = $wm_orig_h * $watermark_w / $wm_orig_w;
		} else {
			$watermark_h = $image_h * $size_percent / 100;
			$watermark_h = min( $watermark_h, $image_h );
			$watermark_w = $wm_orig_w * $watermark_h / $wm_orig_h;
		}

		// Clamp to image bounds.
		if ( $watermark_w >= $image_w ) {
			$watermark_w = $image_w;
			$watermark_h = $wm_orig_h * $watermark_w / $wm_orig_w;
		}
		if ( $watermark_h >= $image_h ) {
			$watermark_h = $image_h;
			$watermark_w = $wm_orig_w * $watermark_h / $wm_orig_h;
		}

		$editor->overlay(
			$watermark_path,
			$position,
			$opacity,
			$offset_x,
			$offset_y,
			(int) $watermark_w,
			(int) $watermark_h
		);
	}
}
