<?php

namespace WP_Smart_Image_Resize\Utilities;

use Exception;

/**
 * Backup store for pristine originals and background-removal cutouts.
 *
 * Backups live in a hidden, access-protected folder (wp_sir_backups/) that
 * mirrors the uploads path structure. Backup filenames are made unique with a
 * numeric counter (photo.jpg, photo-1.jpg, photo-2.jpg …) so two attachments
 * that resolve to the same uploads filename never collide — this happens for
 * background removal, which moves the original out of uploads, freeing its name
 * for the next upload to reuse.
 *
 * Because the resolved backup name can differ from the source path, callers
 * store the returned relative path in post meta (a "pointer") and pass it back
 * to restore_at()/exists_at()/delete_at(). Pre-existing routine backups created
 * before this scheme have no pointer; callers fall back to legacy_relative_of()
 * (the plain path-derived slot) for those.
 */
class Backup
{
    const BACKUP_DIR = 'wp_sir_backups';

    // ---------------------------------------------------------------------
    // Creating backups
    // ---------------------------------------------------------------------

    /**
     * Copy $source_file into the backup store under a unique slot.
     *
     * @param string      $source_file      Absolute path of the file to back up.
     * @param string|null $desired_relative  Optional uploads-relative path to
     *                                       base the backup slot on (e.g. a
     *                                       "-nobg" cutout name). Defaults to
     *                                       the source file's own relative path.
     * @return string The resolved uploads-relative backup path (store this in
     *                meta). Caller uses it later with restore_at/exists_at/delete_at.
     * @throws Exception on failure.
     */
    public function create( $source_file, $desired_relative = null )
    {
        if ( ! file_exists( $source_file ) ) {
            throw new Exception( "File not found: $source_file" );
        }

        $base_relative = $desired_relative !== null
            ? ltrim( $desired_relative, '/' )
            : $this->getRelativePath( $source_file );

        $relative   = $this->uniqueRelative( $base_relative );
        $backup_abs = $this->absFromRelative( $relative );

        if ( ! wp_mkdir_p( dirname( $backup_abs ) ) ) {
            throw new Exception( "Cannot create the backup folder, check the uploads folder permissions." );
        }

        $this->protect_directory();

        if ( ! copy( $source_file, $backup_abs ) ) {
            throw new Exception( "Cannot create the backup, check your error_log file for debugging." );
        }

        return $relative;
    }

    /**
     * Desired backup-relative slot for a background-removal cutout.
     *
     * The cutout is always transparent PNG. The slot keeps the full original
     * basename and appends "-nobg.png" (e.g. photo.jpg -> photo.jpg-nobg.png),
     * so it never collides with the original's own slot. The counter in
     * create() then guarantees uniqueness across attachments.
     *
     * @param string $original_path Absolute path of the true original.
     * @return string uploads-relative desired slot.
     */
    public function nobg_desired( $original_path )
    {
        $relative = $this->getRelativePath( $original_path );
        $info     = File::mb_pathinfo( $relative );
        $dir      = isset( $info['dirname'] ) && $info['dirname'] !== '.' ? trailingslashit( $info['dirname'] ) : '';
        return $dir . $info['basename'] . '-nobg.png';
    }

    /**
     * Legacy slot: the plain path-derived relative used before meta pointers.
     * Used as a fallback for routine backups created by older versions.
     *
     * @param string $file Absolute path.
     * @return string uploads-relative path.
     */
    public function legacy_relative_of( $file )
    {
        return $this->getRelativePath( $file );
    }

    // ---------------------------------------------------------------------
    // Using backups (by resolved relative pointer)
    // ---------------------------------------------------------------------

    /**
     * @param string $relative uploads-relative backup path (a stored pointer).
     * @return bool
     */
    public function exists_at( $relative )
    {
        if ( ! $relative ) {
            return false;
        }
        return file_exists( $this->absFromRelative( $relative ) );
    }

    /**
     * Restore a backup onto $target.
     *
     * @param string $relative    Stored pointer (uploads-relative backup path).
     * @param string $target      Absolute path to write the restored file to.
     * @param bool   $keep_backup When true the backup file is preserved after
     *                            restoring (non-destructive). Default false.
     * @return bool
     * @throws Exception when the backup file is missing or the copy fails.
     */
    public function restore_at( $relative, $target, $keep_backup = false )
    {
        $backup_abs = $this->absFromRelative( $relative );

        if ( ! file_exists( $backup_abs ) ) {
            throw new Exception( 'No backup found for: ' . $target );
        }

        if ( ! copy( $backup_abs, $target ) ) {
            throw new Exception( "Cannot restore file: [$target], check your error_log file for debugging." );
        }

        if ( ! $keep_backup ) {
            @unlink( $backup_abs );
        }

        return true;
    }

    /**
     * @param string $relative Stored pointer (uploads-relative backup path).
     */
    public function delete_at( $relative )
    {
        if ( ! $relative ) {
            return;
        }
        $backup_abs = $this->absFromRelative( $relative );
        if ( file_exists( $backup_abs ) ) {
            @unlink( $backup_abs );
        }
    }

    // ---------------------------------------------------------------------
    // Bulk / maintenance
    // ---------------------------------------------------------------------

    /**
     * Delete the entire backup store (used by "delete all backups" / uninstall).
     */
    public function clear()
    {
        if ( empty( trim( self::BACKUP_DIR ) ) ) {
            return;
        }

        File::rrmdir( $this->getUploadsDirectory() . self::BACKUP_DIR );
    }

    /**
     * Return IDs of all attachments that currently have a backup on disk.
     *
     * Prefers the stored pointers (_sir_backup_original / _sir_backup_cutout);
     * falls back to the legacy path-derived slot for backups created before the
     * pointer scheme.
     *
     * @return int[]
     */
    public function get_backed_up_attachment_ids()
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT p.ID, pm.meta_value AS filepath
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_wp_attached_file'
             WHERE p.post_type = 'attachment'
               AND p.post_mime_type LIKE 'image/%'
               AND p.post_status != 'trash'",
            ARRAY_A
        );

        if ( empty( $rows ) ) {
            return [];
        }

        $ids = [];

        foreach ( $rows as $row ) {
            $id = (int) $row['ID'];

            // Pointer-based (current scheme).
            $original_ptr = get_post_meta( $id, '_sir_backup_original', true );
            if ( $original_ptr && $this->exists_at( $original_ptr ) ) {
                $ids[] = $id;
                continue;
            }

            // Legacy fallback: routine backup stored at the plain path of the
            // current attached file, or the tracked pre-conversion original.
            $has_backup = $this->exists_at( $row['filepath'] );

            if ( ! $has_backup ) {
                foreach ( [ '_sir_pre_conversion_file', '_sir_pre_bgremoval_file' ] as $meta_key ) {
                    $relative = get_post_meta( $id, $meta_key, true );
                    if ( $relative && $this->exists_at( $relative ) ) {
                        $has_backup = true;
                        break;
                    }
                }
            }

            if ( $has_backup ) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    // ---------------------------------------------------------------------
    // Internals
    // ---------------------------------------------------------------------

    /**
     * Add .htaccess and index.php to prevent public access to backup files.
     */
    private function protect_directory()
    {
        $dir = $this->getUploadsDirectory() . self::BACKUP_DIR;

        $htaccess = $dir . '/.htaccess';
        if ( ! file_exists( $htaccess ) ) {
            @file_put_contents( $htaccess, "Deny from all\n" );
        }

        $index = $dir . '/index.php';
        if ( ! file_exists( $index ) ) {
            @file_put_contents( $index, "<?php\n// Silence is golden.\n" );
        }
    }

    /**
     * Given a desired uploads-relative slot, return a variant that does not yet
     * exist in the backup store, appending -1, -2, … before the extension.
     *
     * @param string $relative uploads-relative desired path.
     * @return string uploads-relative unique path.
     */
    private function uniqueRelative( $relative )
    {
        $relative = ltrim( $relative, '/' );

        if ( ! file_exists( $this->absFromRelative( $relative ) ) ) {
            return $relative;
        }

        $info = File::mb_pathinfo( $relative );
        $dir  = isset( $info['dirname'] ) && $info['dirname'] !== '.' ? trailingslashit( $info['dirname'] ) : '';
        $name = isset( $info['filename'] ) ? $info['filename'] : $relative;
        $ext  = isset( $info['extension'] ) ? '.' . $info['extension'] : '';

        $count = 1;
        do {
            $candidate = $dir . $name . '-' . $count . $ext;
            $count++;
        } while ( file_exists( $this->absFromRelative( $candidate ) ) );

        return $candidate;
    }

    /**
     * Absolute path of a backup slot from its uploads-relative path.
     */
    private function absFromRelative( $relative )
    {
        return $this->getUploadsDirectory() . trailingslashit( self::BACKUP_DIR ) . ltrim( $relative, '/' );
    }

    /**
     * uploads-relative path of an absolute uploads file.
     */
    private function getRelativePath( $file )
    {
        return ltrim( str_replace( $this->getUploadsDirectory(), '', $file ), '/' );
    }

    private function getUploadsDirectory()
    {
        return trailingslashit( wp_get_upload_dir()['basedir'] );
    }
}
