<?php

namespace WP_Smart_Image_Resize\Events;

use WP_Smart_Image_Resize\Exceptions\Invalid_Image_Meta_Exception;
use WP_Smart_Image_Resize\Image_Meta;
use WP_Smart_Image_Resize\Utilities\Backup;

class Image_Deleted extends Base_Event
{
    public function listen()
    {
        add_action( 'delete_attachment', [ $this, 'delete_webp_images' ] );
        add_action( 'delete_attachment', [ $this, 'delete_backups' ] );
    }

    /**
     * Delete WebP images.
     *
     * @param int $attachmentId
     *
     * @return void
     */
    public function delete_webp_images( $attachmentId )
    {
        try{
            $imageMeta = new Image_Meta( $attachmentId );

            foreach ( $imageMeta->getSizeNames() as $sizeName ) {
                wp_delete_file( $imageMeta->getSizeFullPath( $sizeName, 'webp' ) );
            }

        }catch(Invalid_Image_Meta_Exception $e){

        }

    }

    /**
     * Delete any backup files left in wp_sir_backups/ for this attachment.
     *
     * WordPress' own attachment deletion only removes files it knows about
     * (the attached file + registered sizes) — backups created by this
     * plugin live outside that tracking and would otherwise be orphaned
     * forever. Covers the routine backup (keyed to the current attached
     * file) and, for PNG→JPG converted or background-removed images, the
     * separately tracked pristine original.
     *
     * @param int $attachmentId
     *
     * @return void
     */
    public function delete_backups( $attachmentId )
    {
        $backup      = new Backup();
        $uploads_dir = trailingslashit( wp_get_upload_dir()['basedir'] );

        // Current scheme: delete via the stored pointers.
        $backup->delete_at( get_post_meta( $attachmentId, '_sir_backup_original', true ) );
        $backup->delete_at( get_post_meta( $attachmentId, '_sir_backup_cutout', true ) );

        // Legacy fallback: backups created before the pointer scheme were
        // stored at the plain path-derived slot of the current attached file or
        // the tracked pre-conversion / pre-bgremoval originals.
        $current_file = get_attached_file( $attachmentId );
        if ( $current_file ) {
            $backup->delete_at( $backup->legacy_relative_of( $current_file ) );
        }

        foreach ( [ '_sir_pre_conversion_file', '_sir_pre_bgremoval_file' ] as $meta_key ) {
            $relative = get_post_meta( $attachmentId, $meta_key, true );
            if ( $relative ) {
                $backup->delete_at( $backup->legacy_relative_of( $uploads_dir . $relative ) );
            }
        }
    }
}
