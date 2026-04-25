<?php

namespace WP_Smart_Image_Resize\Filters;

use WP_Smart_Image_Resize\Helper;

class Filter_Subscriber
{
    /**
     * The list of events to subscribe.
     * @var array
     */
    protected $filters = [
        Calculate_Srcset::class,
        Image_Source::class,
        Background_Thumbnails_Regeneration::class,
        Generated_Sizes::class,
        Filter_Processable_Regenerate_Thumbnails::class,
        Custom_Sizes::class
    ];

    /**
     * Susbcribe events.
     */
    public function subscribe()
    {
        add_filter( 'wp_update_attachment_metadata', function($meta, $id){
            $old_metadata = get_post_meta($id, '_wp_attachment_metadata', true);
            update_post_meta($id, '_old_image_meta', $old_metadata);
            return $meta;
            
        },10,2);

        // Always load Base_Filter and the processable-images filter so that
        // Bulk_Processor can instantiate Filter_Processable_Regenerate_Thumbnails
        // regardless of whether the uniformity toggle is on or off.
        require_once path_join(__DIR__, 'Base_Filter.php');
        require_once path_join(__DIR__, 'Filter_Processable_Regenerate_Thumbnails.php');

        if (! wp_sir_get_settings()['enable']) {
            return;
        }

        foreach ($this->filters as $class) {
            $class_name = Helper::get_class_short_name($class);
            $file = path_join(__DIR__, $class_name.'.php');
            if (is_readable($file)) {
                require_once $file;
            }
            if (class_exists($class)) {
                ( new $class )->listen();
            }
        }
    }
}

// Run.
( new Filter_Subscriber() )->subscribe();
