<?php

namespace WP_Smart_Image_Resize\Utilities;

use Exception;

class Backup
{
    const BACKUP_DIR = 'wp_sir_backups';

    /**
     * @param $file
     * @return bool
     * @throws Exception
     */
    public function create( $file )
    {
        if ( !file_exists( $file ) ) {
            throw new Exception( "File not found: $file" );
        }

        $backupFile = $this->getBackupFile($file);
      
        if ( !wp_mkdir_p( dirname( $backupFile ) ) ) {
            throw new Exception( "Cannot create the backup folder, check the uploads folder permissions." );
        }

        // Protect backup directory from public access.
        $this->protect_directory();

        if ( !copy( $file, $backupFile ) ) {
            throw new Exception( "Cannot create the backup, check your error_log file for debugging." );
        }

        return true;

    }

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

    public function exists( $file )
    {
        return file_exists( $this->getBackupFile( $file ) );
    }
    
    public function getBackupFile($file){
        return $this->getUploadsDirectory() . trailingslashit( self::BACKUP_DIR ) . $this->getRelativePath( $file );
    }
    
    public function delete( $file )
    {
        $backupFile = $this->getBackupFile($file);

        if ( file_exists( $backupFile ) ) {
            unlink( $backupFile );
        }
    }

    /**
     * @param $file
     * @return bool
     * @throws Exception
     */
    function restore( $file )
    {
        $backupFile = $this->getBackupFile($file);

        if ( !file_exists( $backupFile ) ) {
            throw new Exception( 'No backup found for: ' . $file );
        }

        if ( !copy( $backupFile, $file ) ) {
            throw new Exception( "Cannot restore file: [$file], check your error_log file for debugging." );
        }

        unlink( $backupFile );
        
        // TODO: remove folder if empty.
        
        return true;
    }

    function clear()
    {
        // Safety check.
        if( empty( trim( self::BACKUP_DIR ) ) ){
            return;
        }

        File::rrmdir( $this->getUploadsDirectory() . self::BACKUP_DIR );
    }

    /**
     * Return IDs of all attachments that currently have a backup file on disk.
     * Uses a DB query to get candidate file paths then checks filesystem existence.
     *
     * @return int[]
     */
    public function get_backed_up_attachment_ids() {
        global $wpdb;

        // Fetch all image attachment IDs and their stored file paths in one query.
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

        $uploads_dir = $this->getUploadsDirectory();
        $ids         = [];

        foreach ( $rows as $row ) {
            $file = $uploads_dir . $row['filepath'];
            if ( $this->exists( $file ) ) {
                $ids[] = (int) $row['ID'];
            }
        }

        return $ids;
    }

    private function getRelativePath( $file )
    {
        return str_replace( $this->getUploadsDirectory(), '', $file );
    }

    private function getUploadsDirectory()
    {
        return trailingslashit( wp_get_upload_dir()[ 'basedir' ] );
    }

}