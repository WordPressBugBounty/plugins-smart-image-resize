<?php
/**
 * Trim Tool.
 *
 * Removes whitespace/background from image edges.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Tools
 */

namespace WP_Smart_Image_Resize\Tools;

defined( 'ABSPATH' ) || exit;

/**
 * Class Trim_Tool
 */
class Trim_Tool implements Tool {

	/**
	 * Apply whitespace trimming to the image.
	 *
	 * @param \WP_Smart_Image_Resize\Editor\Editor_GD|\WP_Smart_Image_Resize\Editor\Editor_Imagick $editor
	 * @param array $context
	 * @return void
	 */
	public function apply( $editor, array $context ) {
		$settings = $context['settings'];

		if ( empty( $settings['enable_trim'] ) ) {
			return;
		}

		$feather   = (int) apply_filters( 'wp_sir_trim_feather', (int) $settings['trim_feather'] );
		$tolerance = (int) apply_filters( 'wp_sir_trim_tolerance', (int) $settings['trim_tolerance'] );

		$editor->trim( $tolerance, $feather );
	}
}
