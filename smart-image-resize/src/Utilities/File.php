<?php

namespace WP_Smart_Image_Resize\Utilities;

class File
{

    public static function mb_pathinfo($path, $options = null)
    {
        $locale = setlocale(LC_ALL, 0);

        setlocale(LC_ALL, 'en_US.UTF-8');

        if (is_null($options)) {
            $info = pathinfo($path);
        } else {
            $info = pathinfo($path, $options);
        }

        setlocale(LC_ALL, $locale);

        return $info;
    }

    public static function rrmdir($directory)
    {
        // Validate directory is within the WordPress uploads directory.
        $uploads_dir     = wp_get_upload_dir()['basedir'];
        $real_directory  = realpath($directory);
        $real_uploads    = realpath($uploads_dir);

        if ( ! $real_directory || ! $real_uploads || strpos($real_directory, $real_uploads) !== 0 ) {
            return false;
        }

        foreach (new \DirectoryIterator($directory) as $f) {
            if ($f->isDot()) {
                continue;
            }

            if ($f->isFile()) {
                unlink($f->getPathname());
            } else {
                if ($f->isDir()) {
                    static::rrmdir($f->getPathname());
                }
            }
        }
        rmdir($directory);
    }
}
