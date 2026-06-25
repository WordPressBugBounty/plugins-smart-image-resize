<?php

namespace WP_Smart_Image_Resize\Image_Filters;

use Intervention\Image\Filters\FilterInterface;
use Intervention\Image\Image;
use WP_Smart_Image_Resize\Image_Manager;

/**
 * Pads the original image to match a target aspect ratio without
 * downscaling or upscaling. The image dimensions only grow (or stay
 * the same) — quality is never sacrificed.
 *
 * Example: a 1200×900 original with a 1:1 target becomes 1200×1200,
 * with 150 px of background-color padding added top and bottom.
 *
 * The target aspect ratio is resolved automatically from the selected
 * sizes using the following priority:
 *   1. woocommerce_single (or shop_single)
 *   2. large
 *   3. first selected size that has both width and height defined
 *
 * If no usable size can be found the filter is a no-op.
 */
class Pad_Original_Filter implements FilterInterface
{
    /** @var array|null {width, height} of the target size, or null if undetermined */
    protected $target_size;

    /**
     * @param array|null $target_size  {width: int, height: int}
     */
    public function __construct( $target_size )
    {
        $this->target_size = $target_size;
    }

    // -------------------------------------------------------------------------

    public function applyFilter( Image $image )
    {
        // Nothing to do if we couldn't determine a target ratio.
        if ( empty( $this->target_size['width'] ) || empty( $this->target_size['height'] ) ) {
            return $image;
        }

        $target_w = (int) $this->target_size['width'];
        $target_h = (int) $this->target_size['height'];

        $orig_w = $image->getWidth();
        $orig_h = $image->getHeight();

        // Calculate what canvas dimensions are needed so that the original
        // fits entirely within a frame that has the target aspect ratio.
        // We always expand, never shrink.
        $target_ratio = $target_w / $target_h;
        $orig_ratio   = $orig_w / $orig_h;

        if ( abs( $orig_ratio - $target_ratio ) < 0.001 ) {
            // Already the right ratio — nothing to do.
            return $image;
        }

        if ( $orig_ratio > $target_ratio ) {
            // Image is wider than the target ratio → add height.
            $canvas_w = $orig_w;
            $canvas_h = (int) round( $orig_w / $target_ratio );
        } else {
            // Image is taller than the target ratio → add width.
            $canvas_w = (int) round( $orig_h * $target_ratio );
            $canvas_h = $orig_h;
        }

        $bg_color = maybe_hash_hex_color( wp_sir_get_settings()['bg_color'] ) ?: null;

        // Build canvas and place the original centred on it.
        $image_manager = new Image_Manager();
        $canvas        = $image_manager->canvas( $canvas_w, $canvas_h, $bg_color, $image );

        return $canvas->insert( $image, 'center' );
    }
}
