<?php
/**
 * Extended Imagick Image Editor.
 *
 * Extends WP_Image_Editor_Imagick with additional image manipulation methods
 * needed by the plugin: trim, canvas, and overlay.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Editor
 */

namespace WP_Smart_Image_Resize\Editor;

use WP_Smart_Image_Resize\Utilities\Env;

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-imagick.php';

/**
 * Class Editor_Imagick
 *
 * Adds trim, canvas placement, and overlay compositing to WordPress Imagick editor.
 */
class Editor_Imagick extends \WP_Image_Editor_Imagick {

	use Safe_Save_Trait;

	/**
	 * Get the Imagick instance.
	 *
	 * @return \Imagick
	 */
	public function get_image() {
		return $this->image;
	}

	/**
	 * Replace the internal Imagick instance.
	 *
	 * @param \Imagick $image New Imagick instance.
	 */
	public function set_image( \Imagick $image ) {
		$this->image = $image;
		$this->update_size();
	}

	/**
	 * Load the image, converting CMYK (or other non-RGB colorspaces) to sRGB
	 * immediately.
	 *
	 * Previously this conversion only happened inside scale_image(), so any
	 * path that composites the image without resizing first — e.g. Pad_Tool
	 * padding the original to match a target aspect ratio via set_canvas()
	 * directly — would composite raw, un-converted CMYK pixel data onto an
	 * sRGB canvas, producing badly wrong colors. Converting once here, right
	 * after load, means every operation (scale, canvas, trim, overlay, save)
	 * always works on already-correct sRGB data regardless of call order.
	 *
	 * @return true|\WP_Error
	 */
	public function load() {
		$loaded = parent::load();

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		$this->maybe_convert_colorspace();

		return $loaded;
	}

	/**
	 * Save the image, flattening onto an opaque background first if the
	 * destination is JPEG. JPEG has no alpha channel — without this,
	 * transparent pixels (e.g. from a remove.bg cutout, or the transparent
	 * canvas fill used when no Background Color is set) would encode
	 * unpredictably instead of showing a sensible background. Uses the
	 * plugin's configured Background Color when set, otherwise white.
	 *
	 * @param string|null $destfilename
	 * @param string|null $mime_type
	 * @return array|\WP_Error
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		if ( $this->image && $this->will_save_as_jpeg( $destfilename, $mime_type ) ) {
			$flattened = $this->flatten_for_jpeg();
			if ( is_wp_error( $flattened ) ) {
				return $flattened;
			}
		}

		return $this->safe_save( $destfilename, $mime_type );
	}

	/**
	 * Whether the given save() arguments resolve to a JPEG destination.
	 *
	 * @param string|null $destfilename
	 * @param string|null $mime_type
	 * @return bool
	 */
	private function will_save_as_jpeg( $destfilename, $mime_type ) {
		if ( $mime_type ) {
			return 'image/jpeg' === $mime_type;
		}

		$ext = strtolower( pathinfo( $destfilename ?: $this->file, PATHINFO_EXTENSION ) );

		return in_array( $ext, [ 'jpg', 'jpeg' ], true );
	}

	/**
	 * Flatten the image onto an opaque background in place. Safe to call
	 * unconditionally: flattening an already fully-opaque image onto a
	 * background is a no-op.
	 *
	 * @return true|\WP_Error
	 */
	private function flatten_for_jpeg() {
		try {
			$bg_color = function_exists( 'maybe_hash_hex_color' )
				? maybe_hash_hex_color( strtolower( wp_sir_get_settings()['bg_color'] ) )
				: '';

			$pixel = new \ImagickPixel( $bg_color ?: 'white' );

			$image_size = $this->image->getImageGeometry();

			$canvas = new \Imagick();
			$canvas->newImage( $image_size['width'], $image_size['height'], $pixel );
			$canvas->setImageFormat( 'jpeg' );

			$canvas->compositeImage( $this->image, \Imagick::COMPOSITE_OVER, 0, 0 );

			$this->image->clear();
			$this->image = $canvas;

			// set_quality() was already called against the old (now-cleared)
			// Imagick object, which applied the compression settings directly
			// to it via setImageCompressionQuality()/setImageCompression() —
			// those calls don't carry over to this new canvas object, so
			// every flattened JPEG silently fell back to Imagick's default
			// quality regardless of the plugin's setting. Re-apply directly
			// (mirroring WP_Image_Editor_Imagick::set_quality()'s own JPEG
			// branch) rather than re-dispatching through set_quality(),
			// which keys off $this->mime_type — still the pre-conversion
			// type here (e.g. 'image/png' mid PNG→JPG), so it wouldn't take
			// the JPEG branch. We're only ever here because we ARE about to
			// save as JPEG (that's what will_save_as_jpeg() gates on).
			$quality = $this->get_quality();
			$canvas->setImageCompressionQuality( $quality );
			$canvas->setCompressionQuality( $quality );
			$canvas->setImageCompression( \Imagick::COMPRESSION_JPEG );

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'image_flatten_error', $e->getMessage() );
		}
	}

	/**
	 * Create an independent in-memory copy of this editor.
	 *
	 * Lets callers work on multiple versions of the same image (trimmed,
	 * untrimmed, per-size, etc.) without re-reading and re-decoding the
	 * source file from disk for each one.
	 *
	 * @return Editor_Imagick
	 */
	public function duplicate() {
		$clone = new self( $this->file );

		$clone->set_image( clone $this->image );
		$clone->mime_type = $this->mime_type;
		$clone->set_quality( $this->get_quality() );

		return $clone;
	}

	/**
	 * Replace the current image with the decoded contents of a raw image
	 * byte string (e.g. an HTTP response body), without touching disk.
	 *
	 * @param string $data      Raw encoded image bytes (e.g. PNG data).
	 * @param string $mime_type Mime type of the decoded data. Default 'image/png'.
	 * @return true|\WP_Error
	 */
	public function load_from_string( $data, $mime_type = 'image/png' ) {
		try {
			$image = new \Imagick();
			$image->readImageBlob( $data );

			$this->set_image( $image );
			$this->mime_type = $mime_type;

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'image_load_error', $e->getMessage() );
		}
	}

	/**
	 * Trim whitespace/background from image edges.
	 *
	 * Uses Imagick::trimImage() for fast native trimming.
	 *
	 * @param int $tolerance Color difference tolerance (0-100). Default 3.
	 * @param int $feather   Padding to add back after trim in pixels. Default 0.
	 * @return true|\WP_Error
	 */
	public function trim( $tolerance = 3, $feather = 0 ) {
		try {
			$this->maybe_convert_colorspace();

			// Imagick tolerance uses a different scale: 0-65535.
			// Convert from our 0-100 scale.
			$imagick_tolerance = 65850 / 100 * $tolerance;

			$this->image->trimImage( $imagick_tolerance );
			$this->image->setImagePage( 0, 0, 0, 0 );

			// Apply feather padding if specified.
			if ( $feather > 0 ) {
				$bg_color = maybe_hash_hex_color( strtolower( wp_sir_get_settings()['bg_color'] ) );

				// Handle background color for Imagick 6.x compat.
				if ( empty( $bg_color ) && is_callable( [ $this->image, 'getImageAlphaChannel' ] ) && ! $this->image->getImageAlphaChannel() ) {
					if ( version_compare( Env::getImagickVersion(), '7', '<' ) ) {
						$bg_color = 'white';
					}
				}

				$pixel = ! empty( $bg_color ) ? new \ImagickPixel( $bg_color ) : new \ImagickPixel( 'none' );

				$image_size = $this->image->getImageGeometry();
				$canvas_w   = $image_size['width'] + $feather;
				$canvas_h   = $image_size['height'] + $feather;

				// Build a fresh canvas pre-filled with the background and composite
				// the trimmed content onto it (see set_canvas() docblock below for
				// why extentImage()+mergeImageLayers(FLATTEN) isn't used here: it
				// only fills the newly added margin, not any pre-existing transparent
				// pixels within the trimmed content's own bounds — e.g. the corners
				// around a round product silhouette on a remove.bg cutout).
				$canvas = new \Imagick();
				$canvas->newImage( $canvas_w, $canvas_h, $pixel );
				$canvas->setImageFormat( 'png' );

				$dst_x = (int) max( 0, ( $canvas_w - $image_size['width'] ) / 2 );
				$dst_y = (int) max( 0, ( $canvas_h - $image_size['height'] ) / 2 );

				$canvas->compositeImage( $this->image, \Imagick::COMPOSITE_OVER, $dst_x, $dst_y );

				$this->image->clear();
				$this->image = $canvas;
			}

			$this->update_size();

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'image_trim_error', $e->getMessage() );
		}
	}

	/**
	 * Resize using scaleImage to preserve ICC/color profiles.
	 *
	 * WP_Image_Editor_Imagick::resize() uses thumbnail_image() which strips profiles.
	 * This method uses Imagick::scaleImage() which preserves them.
	 *
	 * For CMYK images, converts to sRGB first using the embedded ICC profile
	 * (if available) to ensure accurate color output for web.
	 *
	 * @param int  $max_w Maximum width. Use 0 for no limit.
	 * @param int  $max_h Maximum height. Use 0 for no limit.
	 * @param bool $best_fit Preserve aspect ratio (fit within dimensions).
	 * @return true|\WP_Error
	 */
	public function scale_image( $max_w, $max_h, $best_fit = true ) {
		try {
			$this->maybe_convert_colorspace();
			$this->image->scaleImage( $max_w, $max_h, $best_fit );
			$this->update_size();
			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'image_scale_error', $e->getMessage() );
		}
	}

	/**
	 * Convert CMYK (or other non-RGB colorspaces) to sRGB.
	 *
	 * Uses the embedded ICC profile for accurate conversion when available.
	 * Falls back to direct colorspace transform otherwise.
	 *
	 * @return void
	 */
	private function maybe_convert_colorspace() {
		$colorspace = $this->image->getImageColorspace();

		// Already RGB/sRGB — nothing to do.
		if ( in_array( $colorspace, [ \Imagick::COLORSPACE_RGB, \Imagick::COLORSPACE_SRGB ], true ) ) {
			return;
		}

		// Try profile-aware conversion first (most accurate for CMYK).
		$profiles = $this->image->getImageProfiles( 'icc', true );

		if ( ! empty( $profiles['icc'] ) ) {
			// Image has an embedded ICC profile — use it for conversion.
			$srgb_profile_path = $this->get_srgb_profile_path();
			$srgb_profile      = $srgb_profile_path ? file_get_contents( $srgb_profile_path ) : false;

			if ( $srgb_profile ) {
				$this->image->profileImage( 'icc', $srgb_profile );
			}
		}

		// Transform to sRGB colorspace.
		$this->image->transformImageColorspace( \Imagick::COLORSPACE_SRGB );
	}

	/**
	 * Get path to sRGB ICC profile.
	 *
	 * Checks common system locations. Falls back to empty string if not found
	 * (Imagick will still do colorspace transform, just without profile).
	 *
	 * @return string
	 */
	private function get_srgb_profile_path() {
		$paths = [
			'/usr/share/color/icc/colord/sRGB.icc',
			'/usr/share/color/icc/sRGB.icc',
			'/usr/share/ghostscript/icc/srgb.icc',
			'/usr/local/share/color/icc/sRGB.icc',
		];

		foreach ( $paths as $path ) {
			if ( file_exists( $path ) ) {
				return $path;
			}
		}

		// No system profile found — return empty, conversion will
		// still work via transformImageColorspace but less precise.
		return '';
	}

	/**
	 * Place the current image on a new canvas with background color.
	 *
	 * Builds a fresh canvas pre-filled with the target background and
	 * composites the current image onto it with COMPOSITE_OVER — the same
	 * proven approach overlay() already uses below. An earlier version used
	 * extentImage() + mergeImageLayers(FLATTEN), but FLATTEN is a layer/frame
	 * operation: for a single-frame image (e.g. a remove.bg cutout with no
	 * layers to merge) it does not reliably composite transparent pixels
	 * onto the background fill, so a solid $bg_color could silently leave
	 * the source's own transparent areas transparent instead of filled.
	 *
	 * @param int         $width    Canvas width.
	 * @param int         $height   Canvas height.
	 * @param string|null $bg_color Hex color (e.g. '#ffffff') or null for transparent.
	 * @param string      $position Image position on canvas: center, top, bottom, left, right.
	 * @return true|\WP_Error
	 */
	public function set_canvas( $width, $height, $bg_color = null, $position = 'center' ) {
		try {
			$image_size = $this->image->getImageGeometry();

			// Determine background color.
			if ( $bg_color ) {
				$pixel = new \ImagickPixel( $bg_color );
			} else {
				// Handle transparency based on Imagick version.
				if ( version_compare( Env::getImagickVersion(), '7', '<' ) ) {
					if ( is_callable( [ $this->image, 'getImageAlphaChannel' ] ) && ! $this->image->getImageAlphaChannel() ) {
						$pixel = new \ImagickPixel( 'white' );
					} else {
						$pixel = new \ImagickPixel( 'none' );
					}
				} else {
					$pixel = new \ImagickPixel( 'none' );
				}
			}

			$canvas = new \Imagick();
			$canvas->newImage( $width, $height, $pixel );
			$canvas->setImageFormat( 'png' );

			list( $dst_x, $dst_y ) = $this->calculate_composite_position(
				$position,
				$width,
				$height,
				$image_size['width'],
				$image_size['height']
			);

			$canvas->compositeImage( $this->image, \Imagick::COMPOSITE_OVER, $dst_x, $dst_y );

			$this->image->clear();
			$this->image = $canvas;

			$this->update_size();

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'image_canvas_error', $e->getMessage() );
		}
	}

	/**
	 * Overlay another image on top of the current image.
	 *
	 * @param string $overlay_path Path to overlay image file.
	 * @param string $position     Position: center, top-left, top, top-right, left, right, bottom-left, bottom, bottom-right.
	 * @param int    $opacity      Opacity 0-100.
	 * @param int    $offset_x     Horizontal offset in pixels.
	 * @param int    $offset_y     Vertical offset in pixels.
	 * @param int    $overlay_w    Desired overlay width (0 = original).
	 * @param int    $overlay_h    Desired overlay height (0 = original).
	 * @return true|\WP_Error
	 */
	public function overlay( $overlay_path, $position = 'center', $opacity = 100, $offset_x = 0, $offset_y = 0, $overlay_w = 0, $overlay_h = 0 ) {
		try {
			$overlay = new \Imagick( $overlay_path );

			// Resize overlay if dimensions are specified.
			if ( $overlay_w > 0 && $overlay_h > 0 ) {
				$overlay->scaleImage( $overlay_w, $overlay_h, true );
			}

			// Apply opacity.
			if ( $opacity < 100 ) {
				// A source with no alpha channel of its own (a plain JPG, or a
				// PNG saved without one) has nothing for CHANNEL_ALPHA to
				// multiply — confirmed empirically: evaluateImage() below is a
				// silent no-op without this, so opacity has zero effect on
				// such a watermark file. GD's overlay() doesn't share this
				// requirement (imagecopymerge() handles opacity regardless of
				// source alpha), so make Imagick match it explicitly.
				if ( ! $overlay->getImageAlphaChannel() ) {
					$overlay->setImageAlphaChannel( \Imagick::ALPHACHANNEL_OPAQUE );
				}

				$overlay->evaluateImage(
					\Imagick::EVALUATE_MULTIPLY,
					$opacity / 100,
					\Imagick::CHANNEL_ALPHA
				);
			}

			$overlay_size = $overlay->getImageGeometry();
			$image_size   = $this->image->getImageGeometry();

			list( $dst_x, $dst_y ) = $this->calculate_composite_position(
				$position,
				$image_size['width'],
				$image_size['height'],
				$overlay_size['width'],
				$overlay_size['height']
			);

			$dst_x += $offset_x;
			$dst_y += $offset_y;

			$this->image->compositeImage(
				$overlay,
				\Imagick::COMPOSITE_OVER,
				$dst_x,
				$dst_y
			);

			$overlay->clear();
			$overlay->destroy();

			return true;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'image_overlay_error', $e->getMessage() );
		}
	}

	/**
	 * Calculate x,y position for compositing an overlay onto the image.
	 *
	 * @param string $position    Position keyword.
	 * @param int    $container_w Container width.
	 * @param int    $container_h Container height.
	 * @param int    $child_w     Child width.
	 * @param int    $child_h     Child height.
	 * @return array [x, y]
	 */
	private function calculate_composite_position( $position, $container_w, $container_h, $child_w, $child_h ) {
		$x = 0;
		$y = 0;

		switch ( $position ) {
			case 'center':
				$x = (int) ( ( $container_w - $child_w ) / 2 );
				$y = (int) ( ( $container_h - $child_h ) / 2 );
				break;
			case 'top':
				$x = (int) ( ( $container_w - $child_w ) / 2 );
				$y = 0;
				break;
			case 'top-left':
				$x = 0;
				$y = 0;
				break;
			case 'top-right':
				$x = $container_w - $child_w;
				$y = 0;
				break;
			case 'bottom':
				$x = (int) ( ( $container_w - $child_w ) / 2 );
				$y = $container_h - $child_h;
				break;
			case 'bottom-left':
				$x = 0;
				$y = $container_h - $child_h;
				break;
			case 'bottom-right':
				$x = $container_w - $child_w;
				$y = $container_h - $child_h;
				break;
			case 'left':
				$x = 0;
				$y = (int) ( ( $container_h - $child_h ) / 2 );
				break;
			case 'right':
				$x = $container_w - $child_w;
				$y = (int) ( ( $container_h - $child_h ) / 2 );
				break;
		}

		return [ max( 0, $x ), max( 0, $y ) ];
	}
}
