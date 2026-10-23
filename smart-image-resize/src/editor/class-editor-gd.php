<?php
/**
 * Extended GD Image Editor.
 *
 * Extends WP_Image_Editor_GD with additional image manipulation methods
 * needed by the plugin: trim, canvas, and overlay.
 *
 * @package WP_Smart_Image_Resize
 * @subpackage Editor
 */

namespace WP_Smart_Image_Resize\Editor;

defined( 'ABSPATH' ) || exit;

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';

/**
 * Class Editor_GD
 *
 * Adds trim, canvas placement, and overlay compositing to WordPress GD editor.
 */
class Editor_GD extends \WP_Image_Editor_GD {

	use Safe_Save_Trait;

	/**
	 * Get the GD image resource.
	 *
	 * @return resource|\GdImage
	 */
	public function get_image() {
		return $this->image;
	}

	/**
	 * Replace the internal GD image resource.
	 *
	 * @param resource|\GdImage $image New GD image resource.
	 */
	public function set_image( $image ) {
		$this->image = $image;
		$this->update_size();
	}

	/**
	 * Load the image as truecolor.
	 *
	 * @return true|\WP_Error
	 */
	public function load() {
		$loaded = parent::load();

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		$this->ensure_truecolor();

		return $loaded;
	}

	/**
	 * Convert palette images (GIF, 8-bit PNG) to truecolor with alpha.
	 *
	 * imagecopy() skips a palette image's transparent colour, so every copy
	 * (duplicate(), canvas placement) would turn transparent areas opaque
	 * black, and trim would compare palette indexes instead of colours.
	 */
	private function ensure_truecolor() {
		if ( ! $this->image || imageistruecolor( $this->image ) ) {
			return;
		}

		imagepalettetotruecolor( $this->image );
		imagealphablending( $this->image, false );
		imagesavealpha( $this->image, true );
	}

	/**
	 * Save the image, flattening onto an opaque background first if the
	 * destination is JPEG. JPEG has no alpha channel — without this, GD's
	 * internal placeholder color for "transparent" pixels (raw RGB 0,0,0
	 * with alpha silently discarded on encode) renders as solid black
	 * instead of a sensible background. Uses the plugin's configured
	 * Background Color when set, otherwise white.
	 *
	 * @param string|null $destfilename
	 * @param string|null $mime_type
	 * @return array|\WP_Error
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		if ( $this->image && $this->will_save_as_jpeg( $destfilename, $mime_type ) ) {
			$this->flatten_for_jpeg();
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
	 * background is a no-op (nothing shows through opaque pixels).
	 */
	private function flatten_for_jpeg() {
		$size = $this->get_size();

		$bg_color = function_exists( 'maybe_hash_hex_color' )
			? maybe_hash_hex_color( strtolower( wp_sir_get_settings()['bg_color'] ) )
			: '';

		$rgb = $bg_color ? $this->hex_to_rgb( $bg_color ) : [ 'r' => 255, 'g' => 255, 'b' => 255 ];

		$flattened = wp_imagecreatetruecolor( $size['width'], $size['height'] );
		// Explicitly force blend mode ON — see set_canvas() for why this
		// can't be left to GD's default state.
		imagealphablending( $flattened, true );
		$fill = imagecolorallocate( $flattened, $rgb['r'], $rgb['g'], $rgb['b'] );
		imagefill( $flattened, 0, 0, $fill );

		imagecopy( $flattened, $this->image, 0, 0, 0, 0, $size['width'], $size['height'] );

		imagedestroy( $this->image );
		$this->image = $flattened;
	}

	/**
	 * Resize the image, preserving the alpha channel and allowing upscale.
	 *
	 * Overrides WP_Image_Editor_GD::_resize(). Two differences from core:
	 *
	 * 1. Core's version creates its destination canvas via
	 *    wp_imagecreatetruecolor() without ever calling
	 *    imagealphablending(false)/imagesavealpha(true) on it before copying —
	 *    so imagecopyresampled() blends the source onto an untracked black
	 *    canvas and the alpha channel is silently discarded. For a normal
	 *    opaque photo this is invisible, but for anything with real
	 *    transparency (a remove.bg cutout, any PNG with alpha) it bakes in a
	 *    solid black background. Confirmed empirically: without these two
	 *    calls, a fully transparent source pixel comes out alpha=0 (opaque)
	 *    after resize.
	 *
	 * 2. Core computes dimensions via image_resize_dimensions(), which is a
	 *    "never enlarge" thumbnailer by design: it flatly refuses (returns
	 *    false) whenever the source is smaller than the target box in both
	 *    dimensions. That's the right default for WordPress's own generated
	 *    sizes, but the opposite of this plugin's job — a small product
	 *    photo has to be scaled UP to fill the uniform frame, the same way
	 *    Editor_Imagick::scale_image()'s bestfit=true does. Confirmed
	 *    empirically: with upscaling allowed and a source smaller than the
	 *    target in both dimensions, core's resize() silently no-ops, leaving
	 *    the product tiny in a sea of padding instead of filling the frame.
	 *    sir_resize_dimensions() below mirrors core's crop-box math exactly
	 *    but never refuses to enlarge.
	 *
	 * @param int  $max_w
	 * @param int  $max_h
	 * @param bool $crop
	 * @return resource|\GdImage|\WP_Error
	 */
	protected function _resize( $max_w, $max_h, $crop = false ) {
		$dims = $this->sir_resize_dimensions( $this->size['width'], $this->size['height'], $max_w, $max_h, $crop );

		if ( ! $dims ) {
			return new \WP_Error( 'error_getting_dimensions', __( 'Could not calculate resized image dimensions', 'wp-smart-image-resize' ) );
		}

		list( $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h ) = $dims;

		$resized = wp_imagecreatetruecolor( $dst_w, $dst_h );

		imagealphablending( $resized, false );
		imagesavealpha( $resized, true );

		imagecopyresampled( $resized, $this->image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h );

		if ( \is_gd_image( $resized ) ) {
			$this->update_size( $dst_w, $dst_h );
			return $resized;
		}

		return new \WP_Error( 'image_resize_error', __( 'Image resize failed.', 'wp-smart-image-resize' ) );
	}

	/**
	 * Same crop-box math as WordPress's image_resize_dimensions(), minus the
	 * "never enlarge" guard: this always scales to fill/fit the requested
	 * box, upscaling the source when it's smaller. See the note on _resize()
	 * above for why the plugin needs this instead of core's version.
	 *
	 * @param int        $orig_w
	 * @param int        $orig_h
	 * @param int        $dest_w
	 * @param int        $dest_h
	 * @param bool|array $crop
	 * @return array|false [dst_x, dst_y, src_x, src_y, dst_w, dst_h, src_w, src_h]
	 */
	private function sir_resize_dimensions( $orig_w, $orig_h, $dest_w, $dest_h, $crop = false ) {
		if ( $orig_w <= 0 || $orig_h <= 0 || ( $dest_w <= 0 && $dest_h <= 0 ) ) {
			return false;
		}

		if ( $crop ) {
			// Crop the largest portion matching the target aspect ratio, then
			// scale that portion to exactly dest_w x dest_h (upscaling if needed).
			$aspect_ratio = $orig_w / $orig_h;
			$new_w        = $dest_w ?: (int) round( $dest_h * $aspect_ratio );
			$new_h        = $dest_h ?: (int) round( $dest_w / $aspect_ratio );

			$crop_ratio = max( $new_w / $orig_w, $new_h / $orig_h );
			$crop_w     = round( $new_w / $crop_ratio );
			$crop_h     = round( $new_h / $crop_ratio );

			if ( ! is_array( $crop ) || count( $crop ) !== 2 ) {
				$crop = [ 'center', 'center' ];
			}

			list( $x, $y ) = $crop;

			if ( 'left' === $x ) {
				$s_x = 0;
			} elseif ( 'right' === $x ) {
				$s_x = $orig_w - $crop_w;
			} else {
				$s_x = floor( ( $orig_w - $crop_w ) / 2 );
			}

			if ( 'top' === $y ) {
				$s_y = 0;
			} elseif ( 'bottom' === $y ) {
				$s_y = $orig_h - $crop_h;
			} else {
				$s_y = floor( ( $orig_h - $crop_h ) / 2 );
			}
		} else {
			// Fit the whole original within the dest_w x dest_h bounding box,
			// preserving aspect ratio (upscaling if needed) — no source crop.
			$crop_w = $orig_w;
			$crop_h = $orig_h;
			$s_x    = 0;
			$s_y    = 0;

			$ratio = $dest_w && $dest_h
				? min( $dest_w / $orig_w, $dest_h / $orig_h )
				: ( $dest_w ? $dest_w / $orig_w : $dest_h / $orig_h );

			$new_w = $ratio * $orig_w;
			$new_h = $ratio * $orig_h;
		}

		$new_w = max( 1, (int) round( $new_w ) );
		$new_h = max( 1, (int) round( $new_h ) );

		return [ 0, 0, (int) $s_x, (int) $s_y, $new_w, $new_h, (int) $crop_w, (int) $crop_h ];
	}

	/**
	 * Crop the image, preserving the alpha channel.
	 *
	 * Overrides WP_Image_Editor_GD::crop() for the same reason as _resize()
	 * above — core's version doesn't set imagealphablending(false)/
	 * imagesavealpha(true) on the destination canvas before copying, which
	 * discards transparency. Used by the experimental fill/crop mode.
	 *
	 * @param int  $src_x
	 * @param int  $src_y
	 * @param int  $src_w
	 * @param int  $src_h
	 * @param int  $dst_w
	 * @param int  $dst_h
	 * @param bool $src_abs
	 * @return true|\WP_Error
	 */
	public function crop( $src_x, $src_y, $src_w, $src_h, $dst_w = null, $dst_h = null, $src_abs = false ) {
		if ( ! $dst_w ) {
			$dst_w = $src_w;
		}
		if ( ! $dst_h ) {
			$dst_h = $src_h;
		}

		foreach ( [ $src_w, $src_h, $dst_w, $dst_h ] as $value ) {
			if ( ! is_numeric( $value ) || (int) $value <= 0 ) {
				return new \WP_Error( 'image_crop_error', __( 'Image crop failed.', 'wp-smart-image-resize' ), $this->file );
			}
		}

		$dst = wp_imagecreatetruecolor( (int) $dst_w, (int) $dst_h );

		imagealphablending( $dst, false );
		imagesavealpha( $dst, true );

		if ( $src_abs ) {
			$src_w -= $src_x;
			$src_h -= $src_y;
		}

		if ( function_exists( 'imageantialias' ) ) {
			imageantialias( $dst, true );
		}

		imagecopyresampled( $dst, $this->image, 0, 0, (int) $src_x, (int) $src_y, (int) $dst_w, (int) $dst_h, (int) $src_w, (int) $src_h );

		if ( \is_gd_image( $dst ) ) {
			imagedestroy( $this->image );
			$this->image = $dst;
			$this->update_size();
			return true;
		}

		return new \WP_Error( 'image_crop_error', __( 'Image crop failed.', 'wp-smart-image-resize' ), $this->file );
	}

	/**
	 * Create an independent in-memory copy of this editor.
	 *
	 * Lets callers work on multiple versions of the same image (trimmed,
	 * untrimmed, per-size, etc.) without re-reading and re-decoding the
	 * source file from disk for each one.
	 *
	 * @return Editor_GD
	 */
	public function duplicate() {
		$clone = new self( $this->file );

		$size  = $this->get_size();
		$image = wp_imagecreatetruecolor( $size['width'], $size['height'] );

		imagealphablending( $image, false );
		imagesavealpha( $image, true );
		imagecopy( $image, $this->image, 0, 0, 0, 0, $size['width'], $size['height'] );

		$clone->set_image( $image );
		$clone->mime_type = $this->mime_type;
		$clone->set_quality( $this->get_quality() );

		return $clone;
	}

	/**
	 * Replace the current image with the decoded contents of a raw image
	 * byte string (e.g. an HTTP response body), without touching disk.
	 *
	 * @param string $data  Raw encoded image bytes (e.g. PNG data).
	 * @param string $mime_type Mime type of the decoded data. Default 'image/png'.
	 * @return true|\WP_Error
	 */
	public function load_from_string( $data, $mime_type = 'image/png' ) {
		$image = @imagecreatefromstring( $data );

		if ( ! \is_gd_image( $image ) ) {
			return new \WP_Error( 'image_load_error', __( 'Could not decode image data.', 'wp-smart-image-resize' ) );
		}

		if ( function_exists( 'imagealphablending' ) && function_exists( 'imagesavealpha' ) ) {
			imagealphablending( $image, false );
			imagesavealpha( $image, true );
		}

		$this->set_image( $image );
		$this->ensure_truecolor();
		$this->mime_type = $mime_type;

		return true;
	}

	/**
	 * Trim whitespace/background from image edges.
	 *
	 * Scans border pixels and crops to content area.
	 * Uses color distance comparison with configurable tolerance.
	 *
	 * @param int $tolerance Color difference tolerance (0-100). Default 3.
	 * @param int $feather   Padding to add back after trim in pixels. Default 0.
	 * @return true|\WP_Error
	 */
	public function trim( $tolerance = 3, $feather = 0 ) {
		$size   = $this->get_size();
		$width  = $size['width'];
		$height = $size['height'];

		if ( ! $width || ! $height ) {
			return new \WP_Error( 'image_trim_error', __( 'Image has no dimensions.', 'wp-smart-image-resize' ) );
		}

		$color_tolerance = round( $tolerance * 2.55 );
		$alpha_tolerance = round( $tolerance * 1.27 );

		// detect_trim_bounds() below scans every pixel it searches (needed for
		// correctness — see its docblock), which makes its worst case, an
		// image with no trimmable border at all, expensive: confirmed
		// empirically at roughly 1.5 microseconds per pixel, so a plain photo
		// with content touching every edge would take ~9s at 3000x2000 and
		// well over a minute at a phone/DSLR-sized 7000x5000 upload — a real
		// risk of timing out a normal upload or stalling a bulk run.
		//
		// Above a modest size, detect the boundary on a small downscaled
		// proxy instead — bounding detection cost regardless of the original
		// image's size — then crop the full-resolution image directly to the
		// result with a safety margin. crop() is a single fast GD operation,
		// not a per-pixel PHP loop, so this keeps the expensive part cheap.
		// The proxy can only locate the boundary within a few full-resolution
		// pixels; that's fine for this feature (trim removes whitespace, it
		// doesn't have to be pixel-exact) and the margin is applied outward,
		// biased toward keeping a little extra background rather than ever
		// clipping the product.
		$proxy_max = 400;

		if ( max( $width, $height ) > 900 ) {
			$scale   = $proxy_max / max( $width, $height );
			$proxy_w = max( 1, (int) round( $width * $scale ) );
			$proxy_h = max( 1, (int) round( $height * $scale ) );

			$proxy = wp_imagecreatetruecolor( $proxy_w, $proxy_h );
			imagealphablending( $proxy, false );
			imagesavealpha( $proxy, true );
			imagecopyresampled( $proxy, $this->image, 0, 0, 0, 0, $proxy_w, $proxy_h, $width, $height );

			list( $px1, $py1, $px2, $py2 ) = $this->detect_trim_bounds( $proxy, $proxy_w, $proxy_h, $color_tolerance, $alpha_tolerance );
			imagedestroy( $proxy );

			// Map back to full-resolution coordinates, expanded by a margin
			// that covers both the proxy's pixel granularity and downscale
			// resampling blur near edges.
			$margin = (int) ceil( 1 / $scale ) + 2;

			$top_x    = max( 0, (int) floor( $px1 / $scale ) - $margin );
			$top_y    = max( 0, (int) floor( $py1 / $scale ) - $margin );
			$bottom_x = min( $width, (int) ceil( $px2 / $scale ) + $margin );
			$bottom_y = min( $height, (int) ceil( $py2 / $scale ) + $margin );
		} else {
			list( $top_x, $top_y, $bottom_x, $bottom_y ) = $this->detect_trim_bounds( $this->image, $width, $height, $color_tolerance, $alpha_tolerance );
		}

		$new_width  = $bottom_x - $top_x;
		$new_height = $bottom_y - $top_y;

		// Nothing to trim.
		if ( $new_width === $width && $new_height === $height ) {
			return true;
		}

		// Crop to the detected content bounds.
		$result = $this->crop( $top_x, $top_y, $new_width, $new_height );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Apply feather padding AFTER crop (matches original Trim_Filter behavior).
		if ( $feather > 0 ) {
			$current_size = $this->get_size();
			$canvas_w     = $current_size['width'] + $feather;
			$canvas_h     = $current_size['height'] + $feather;

			$canvas = wp_imagecreatetruecolor( $canvas_w, $canvas_h );
			imagefill( $canvas, 0, 0, 0x7fff0000 );
			imagecopy(
				$canvas,
				$this->image,
				(int) ( $feather / 2 ),
				(int) ( $feather / 2 ),
				0,
				0,
				$current_size['width'],
				$current_size['height']
			);

			$this->image = $canvas;
			$this->update_size();
		}

		return true;
	}

	/**
	 * Place the current image on a new canvas with background color.
	 *
	 * @param int         $width    Canvas width.
	 * @param int         $height   Canvas height.
	 * @param string|null $bg_color Hex color (e.g. '#ffffff') or null for transparent.
	 * @param string      $position Image position on canvas: center, top, bottom, left, right.
	 * @return true|\WP_Error
	 */
	public function set_canvas( $width, $height, $bg_color = null, $position = 'center' ) {
		$current_size = $this->get_size();

		$canvas = wp_imagecreatetruecolor( $width, $height );

		// Set background.
		if ( $bg_color ) {
			// Explicitly force blend mode ON before filling/copying — GD's
			// default alphablending state for a freshly created truecolor
			// image is inconsistent across PHP/libgd builds, and imagecopy()
			// only alpha-composites the source onto this fill when blending
			// is genuinely enabled. Without this, a source with its own
			// transparency (e.g. a remove.bg cutout) gets raw-copied instead
			// of blended, leaving its transparent area transparent instead
			// of showing this background color.
			imagealphablending( $canvas, true );
			$rgb = $this->hex_to_rgb( $bg_color );
			$bg  = imagecolorallocate( $canvas, $rgb['r'], $rgb['g'], $rgb['b'] );
			imagefill( $canvas, 0, 0, $bg );
		} else {
			// Transparent background.
			imagesavealpha( $canvas, true );
			imagefill( $canvas, 0, 0, imagecolorallocatealpha( $canvas, 0, 0, 0, 127 ) );
		}

		// Calculate position.
		list( $dst_x, $dst_y ) = $this->calculate_position(
			$position,
			$width,
			$height,
			$current_size['width'],
			$current_size['height']
		);

		imagecopy(
			$canvas,
			$this->image,
			$dst_x,
			$dst_y,
			0,
			0,
			$current_size['width'],
			$current_size['height']
		);

		$this->image = $canvas;
		$this->update_size();

		return true;
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
		// Always use GD for overlay since this IS a GD editor.
		$overlay_editor = new Editor_GD( $overlay_path );
		$loaded = $overlay_editor->load();

		if ( is_wp_error( $loaded ) ) {
			return $loaded;
		}

		// Resize overlay if dimensions are specified.
		if ( $overlay_w > 0 && $overlay_h > 0 ) {
			$overlay_editor->resize( $overlay_w, $overlay_h, true );
		}

		$overlay_image = $overlay_editor->get_image();

		$overlay_size = [
			'width'  => imagesx( $overlay_image ),
			'height' => imagesy( $overlay_image ),
		];

		$current_size = $this->get_size();

		list( $dst_x, $dst_y ) = $this->calculate_position(
			$position,
			$current_size['width'],
			$current_size['height'],
			$overlay_size['width'],
			$overlay_size['height']
		);

		$dst_x += $offset_x;
		$dst_y += $offset_y;

		// Apply with opacity.
		if ( $opacity < 100 ) {
			// imagecopymerge doesn't support alpha, so we handle it manually.
			$pct = (int) $opacity;
			imagecopymerge(
				$this->image,
				$overlay_image,
				$dst_x,
				$dst_y,
				0,
				0,
				$overlay_size['width'],
				$overlay_size['height'],
				$pct
			);
		} else {
			imagecopy(
				$this->image,
				$overlay_image,
				$dst_x,
				$dst_y,
				0,
				0,
				$overlay_size['width'],
				$overlay_size['height']
			);
		}

		return true;
	}

	/**
	 * Find the content bounding box within a GD image by scanning inward
	 * from each edge, comparing against the top-left corner's color.
	 *
	 * Each of the four scans below covers the FULL width/height it searches
	 * — a product entirely within one half of the frame (very common:
	 * off-center supplier photos, a small item in a corner) has a boundary
	 * past the midpoint, so stopping there would miss it. Once a boundary is
	 * found, later scans use it to narrow their own search (content can't be
	 * above top_y or left of top_x), which is a safe optimization, not a
	 * hard cap. Imagick's native trimImage() has no such limit; this keeps
	 * GD's result matching it. That correctness requirement is exactly what
	 * makes this expensive on a large, hard-to-trim image — see trim()'s
	 * docblock for how callers avoid running this against a full-size image.
	 *
	 * @param resource|\GdImage $image
	 * @param int $width
	 * @param int $height
	 * @param int $color_tolerance Color channel tolerance (0-255).
	 * @param int $alpha_tolerance Alpha channel tolerance (0-127).
	 * @return array [top_x, top_y, bottom_x, bottom_y]
	 */
	private function detect_trim_bounds( $image, $width, $height, $color_tolerance, $alpha_tolerance ) {
		// Pick base color from top-left pixel.
		$base_color = $this->get_pixel_color( $image, 0, 0 );

		$top_y    = 0;
		$bottom_y = $height;
		$top_x    = 0;
		$bottom_x = $width;

		// Scan from top.
		for ( $y = 0; $y < $height; $y++ ) {
			for ( $x = 0; $x < $width; $x++ ) {
				if ( $this->color_differs( $base_color, $this->get_pixel_color( $image, $x, $y ), $color_tolerance, $alpha_tolerance ) ) {
					$top_y = $y;
					break 2;
				}
			}
		}

		// Scan from left.
		for ( $x = 0; $x < $width; $x++ ) {
			for ( $y = $top_y; $y < $height; $y++ ) {
				if ( $this->color_differs( $base_color, $this->get_pixel_color( $image, $x, $y ), $color_tolerance, $alpha_tolerance ) ) {
					$top_x = $x;
					break 2;
				}
			}
		}

		// Scan from bottom (content can't be above top_y).
		for ( $y = ( $height - 1 ); $y >= $top_y; $y-- ) {
			for ( $x = $top_x; $x < $width; $x++ ) {
				if ( $this->color_differs( $base_color, $this->get_pixel_color( $image, $x, $y ), $color_tolerance, $alpha_tolerance ) ) {
					$bottom_y = min( $height, $y + 1 );
					break 2;
				}
			}
		}

		// Scan from right (content can't be left of top_x).
		for ( $x = ( $width - 1 ); $x >= $top_x; $x-- ) {
			for ( $y = $top_y; $y < $bottom_y; $y++ ) {
				if ( $this->color_differs( $base_color, $this->get_pixel_color( $image, $x, $y ), $color_tolerance, $alpha_tolerance ) ) {
					$bottom_x = min( $width, $x + 1 );
					break 2;
				}
			}
		}

		return [ $top_x, $top_y, $bottom_x, $bottom_y ];
	}

	/**
	 * Get RGBA values of a pixel.
	 *
	 * @param resource|\GdImage $image
	 * @param int $x X coordinate.
	 * @param int $y Y coordinate.
	 * @return array {r, g, b, a}
	 */
	private function get_pixel_color( $image, $x, $y ) {
		$rgba = imagecolorat( $image, $x, $y );

		return [
			'r' => ( $rgba >> 16 ) & 0xFF,
			'g' => ( $rgba >> 8 ) & 0xFF,
			'b' => $rgba & 0xFF,
			'a' => ( $rgba >> 24 ) & 0x7F,
		];
	}

	/**
	 * Check if two colors differ beyond tolerance.
	 *
	 * @param array $color1          {r, g, b, a}
	 * @param array $color2          {r, g, b, a}
	 * @param int   $color_tolerance Color channel tolerance (0-255).
	 * @param int   $alpha_tolerance Alpha channel tolerance (0-127).
	 * @return bool
	 */
	private function color_differs( $color1, $color2, $color_tolerance, $alpha_tolerance ) {
		return (
			abs( $color1['r'] - $color2['r'] ) > $color_tolerance ||
			abs( $color1['g'] - $color2['g'] ) > $color_tolerance ||
			abs( $color1['b'] - $color2['b'] ) > $color_tolerance ||
			abs( $color1['a'] - $color2['a'] ) > $alpha_tolerance
		);
	}

	/**
	 * Calculate x,y position for placing a child element within a container.
	 *
	 * @param string $position   Position keyword.
	 * @param int    $container_w Container width.
	 * @param int    $container_h Container height.
	 * @param int    $child_w     Child width.
	 * @param int    $child_h     Child height.
	 * @return array [x, y]
	 */
	private function calculate_position( $position, $container_w, $container_h, $child_w, $child_h ) {
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

	/**
	 * Convert hex color to RGB array.
	 *
	 * @param string $hex Hex color string.
	 * @return array {r, g, b}
	 */
	private function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );

		if ( strlen( $hex ) === 3 ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		return [
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		];
	}
}
